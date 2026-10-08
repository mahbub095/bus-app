<?php

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use App\Services\LicenseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use App\Models\User;

/**
 * Handles the step-by-step installation wizard.
 *
 * Steps:
 *   1. License verification — calls Envato API, validates purchase code.
 *      The license record is NOT persisted here yet because the DB may not
 *      be configured. Instead purchase_code + api_data are saved to session
 *      and flushed to DB in the finalize step after migrations have run.
 *   2. Database connection — tests PDO, writes credentials to .env.
 *      Optionally accepts an uploaded .sql file to pre-import existing data.
 *   3. Admin account creation — saved to session for the finalize step.
 *   4. Final setup — runs migrations, persists the pending license record,
 *      creates the super-admin user, clears caches, locks the wizard.
 */
class InstallController extends Controller
{
    public function __construct(protected LicenseService $licenseService) {}

    /**
     * If the wizard is already locked (APP_INSTALLED=true), redirect all
     * install routes to the admin dashboard. This prevents re-running the
     * installer on a live system.
     */
    private function isInstallLocked(): bool
    {
        $flag = env('APP_INSTALLED', false);

        return $flag === true
            || $flag === 1
            || (is_string($flag) && strtolower($flag) === 'true')
            || (is_string($flag) && $flag === '1');
    }

    // -------------------------------------------------------------------------
    // Step 1 — License
    // -------------------------------------------------------------------------

    /**
     * Show the installation welcome / license entry page.
     */
    public function showLicense()
    {
        if ($this->isInstallLocked()) {
            return redirect('/admin');
        }

        if ($this->licenseService->isActivated()) {
            return redirect('/install/database');
        }

        return view('install.license');
    }

    /**
     * Verify the submitted purchase code against the Envato API (or local
     * dev bypass) and store the result in the session. We deliberately
     * DO NOT save to the DB here because the DB is not guaranteed to exist.
     */
    public function verifyLicense(Request $request)
    {
        if ($this->isInstallLocked()) {
            return redirect('/admin');
        }

        $request->validate([
            'purchase_code' => 'required|string|max:100',
        ]);

        // persistDb=false — don't write to license table yet; save to session
        $result = $this->licenseService->verify(
            purchaseCode: $request->input('purchase_code'),
            persistDb:    false
        );

        if (! $result['success']) {
            return back()->withErrors(['purchase_code' => $result['message']])->withInput();
        }

        // Stash the purchase code + API response for the finalize step.
        $request->session()->put('install.pending_license', [
            'purchase_code' => trim($request->input('purchase_code')),
            'api_data'      => $result['api_data'],
        ]);

        return redirect('/install/database')->with('success', $result['message']);
    }

    // -------------------------------------------------------------------------
    // Step 2 — Database (with optional SQL import)
    // -------------------------------------------------------------------------

    public function showDatabase()
    {
        if ($this->isInstallLocked()) {
            return redirect('/admin');
        }

        if (! $this->licenseService->isActivated()) {
            return redirect('/install');
        }

        return view('install.database');
    }

    public function saveDatabase(Request $request)
    {
        if ($this->isInstallLocked()) {
            return redirect('/admin');
        }

        $request->validate([
            'db_host'     => 'required|string|max:255',
            'db_port'     => 'required|integer|min:1|max:65535',
            'db_database' => 'required|string|max:255',
            'db_username' => 'required|string|max:255',
            'db_password' => 'nullable|string|max:255',
            'sql_file'    => 'nullable|file|mimes:sql|max:200000',
        ]);

        // Test connection before writing
        try {
            $testPdo = new \PDO(
                "mysql:host={$request->db_host};port={$request->db_port};dbname={$request->db_database}",
                $request->db_username,
                $request->db_password ?? ''
            );
            $testPdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch (\PDOException $e) {
            return back()
                ->withErrors(['db_host' => 'Database connection failed: ' . $e->getMessage()])
                ->withInput();
        }

        // Write database credentials to .env
        app(\App\Services\EnvFileWriter::class)->set([
            'DB_HOST'     => $request->db_host,
            'DB_PORT'     => (string) $request->db_port,
            'DB_DATABASE' => $request->db_database,
            'DB_USERNAME' => $request->db_username,
            'DB_PASSWORD' => $request->db_password ?? '',
        ]);

        // Reload DB config so Schema / DB facade uses the new credentials
        Artisan::call('config:clear');

        // ─── Optional SQL file import ─────────────────────────────────────
        $importedTables = 0;
        $importedQueries = 0;

        if ($request->hasFile('sql_file') && $request->file('sql_file')->isValid()) {
            try {
                $sqlPath = $request->file('sql_file')->getRealPath();
                $sqlContent = file_get_contents($sqlPath);

                if ($sqlContent === false || trim($sqlContent) === '') {
                    return back()
                        ->withErrors(['sql_file' => 'SQL file is empty or unreadable.'])
                        ->withInput();
                }

                $importResult = $this->importSqlFile($testPdo, $sqlContent);
                $importedTables  = $importResult['tables'];
                $importedQueries = $importResult['queries'];

                $request->session()->put('install.sql_imported', true);
                $request->session()->put('install.sql_imported_stats', [
                    'tables'  => $importedTables,
                    'queries' => $importedQueries,
                ]);

                $successMsg = "Database connected. SQL imported: {$importedQueries} queries executed, {$importedTables} tables found.";
            } catch (\Throwable $e) {
                return back()
                    ->withErrors(['sql_file' => 'SQL import failed: ' . $e->getMessage()])
                    ->withInput();
            }
        } else {
            $successMsg = 'Database connection verified.';
        }

        return redirect('/install/admin')->with('success', $successMsg);
    }

    /**
     * Execute a raw SQL dump against the given PDO connection.
     * Strips comments, splits by semicolon, and runs each statement.
     *
     * @return array{tables: int, queries: int}
     */
    private function importSqlFile(\PDO $pdo, string $sqlContent): array
    {
        // Remove BOM if present
        $sqlContent = preg_replace('/^\xEF\xBB\xBF/', '', $sqlContent);

        // Remove single-line comments  (-- ...  and # ...)
        $sqlContent = preg_replace('/^--.*$/m', '', $sqlContent);
        $sqlContent = preg_replace('/^#.*$/m',  '', $sqlContent);

        // Remove multi-line /* ... */ comments
        $sqlContent = preg_replace('!/\*.*?\*/!s', '', $sqlContent);

        // Split into individual statements by the ";" delimiter, being careful
        // not to split inside string literals (track quote state manually).
        $statements = [];
        $current    = '';
        $inSingle   = false;
        $inDouble   = false;
        $inBacktick = false;
        $len        = strlen($sqlContent);

        for ($i = 0; $i < $len; $i++) {
            $char = $sqlContent[$i];
            $prev = $i > 0 ? $sqlContent[$i - 1] : '';

            // Toggle quote states (unless escaped)
            if ($prev !== '\\') {
                if ($char === "'" && ! $inDouble && ! $inBacktick) {
                    $inSingle = ! $inSingle;
                } elseif ($char === '"' && ! $inSingle && ! $inBacktick) {
                    $inDouble = ! $inDouble;
                } elseif ($char === '`' && ! $inSingle && ! $inDouble) {
                    $inBacktick = ! $inBacktick;
                }
            }

            if ($char === ';' && ! $inSingle && ! $inDouble && ! $inBacktick) {
                $trimmed = trim($current);
                if ($trimmed !== '') {
                    $statements[] = $trimmed;
                }
                $current = '';
            } else {
                $current .= $char;
            }
        }

        $last = trim($current);
        if ($last !== '') {
            $statements[] = $last;
        }

        $executed = 0;
        foreach ($statements as $stmt) {
            $trimmedStmt = trim($stmt);
            if ($trimmedStmt === '') {
                continue;
            }

            // Skip common no-op / USE / DELIMITER lines
            if (preg_match('/^(use\s+|delimiter\s)/i', $trimmedStmt)) {
                continue;
            }

            try {
                $pdo->exec($trimmedStmt);
                $executed++;
            } catch (\PDOException $e) {
                // Ignore "table already exists" / "duplicate key" style errors
                // so that partial dumps can still be applied. Other errors
                // bubble up and abort the import.
                $msg = $e->getMessage();
                if (stripos($msg, 'already exist') === false
                    && stripos($msg, 'Duplicate') === false) {
                    throw new \RuntimeException(
                        'Query failed: ' . $e->getMessage() . ' — SQL: ' . substr($trimmedStmt, 0, 200)
                    );
                }
            }
        }

        // Count how many tables exist in the target DB after import
        try {
            $stmt = $pdo->query('SHOW TABLES');
            $tables = $stmt ? $stmt->rowCount() : 0;
        } catch (\Throwable $e) {
            $tables = 0;
        }

        return [
            'tables'  => (int) $tables,
            'queries' => $executed,
        ];
    }

    // -------------------------------------------------------------------------
    // Step 3 — Admin account
    // -------------------------------------------------------------------------

    public function showAdmin()
    {
        if ($this->isInstallLocked()) {
            return redirect('/admin');
        }

        if (! $this->licenseService->isActivated()) {
            return redirect('/install');
        }

        return view('install.admin');
    }

    public function saveAdmin(Request $request)
    {
        if ($this->isInstallLocked()) {
            return redirect('/admin');
        }

        $request->validate([
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|max:100',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // ⚠️ IMPORTANT: Use PERSISTENT session keys (not ->with() flash data)
        // because the next GET /install/finalize renders a page which then
        // makes a SECOND AJAX request to run the final steps. A Laravel
        // flash() would only survive one request and be missing from the
        // /install/finalize/run POST — leading to "Admin credentials missing".
        $request->session()->put('admin_name',     $request->name);
        $request->session()->put('admin_email',    $request->email);
        $request->session()->put('admin_password', $request->password);

        return redirect('/install/finalize');
    }

    // -------------------------------------------------------------------------
    // Step 4 — Finalize
    // -------------------------------------------------------------------------

    /**
     * GET /install/finalize
     * Shows the installation progress screen. The actual work (migrate / seed /
     * create super-admin / lock installer) is done by runFinalize() below via
     * AJAX from this view so we can render a friendly animated progress UI.
     */
    public function finalize(Request $request)
    {
        if ($this->isInstallLocked()) {
            return redirect('/admin');
        }

        if (! $this->licenseService->isActivated()) {
            return redirect('/install');
        }

        if (! $request->session()->has('admin_email')) {
            return redirect('/install/admin')->withErrors([
                'message' => 'Please complete the admin setup step first.',
            ]);
        }

        return view('install.finalize');
    }

    /**
     * POST /install/finalize/run
     * Executes the actual install steps. Returns JSON so that the progress
     * screen can advance each task visually. On success, the response also
     * contains a redirect URL to the admin dashboard, and the super-admin
     * user is automatically signed in via session auth.
     */
    public function runFinalize(Request $request): \Illuminate\Http\JsonResponse
    {
        // ═════════════════════════════════════════════════════════════════════
        // ⚠️  GLOBAL SAFETY NETS (run BEFORE any Laravel logic)
        // ═════════════════════════════════════════════════════════════════════
        // These prevent the classic "Network error / Failed to fetch" symptom
        // in the browser that happens when PHP emits a warning, dies on a
        // FATAL error, or exhausts memory — all of which produce either no
        // HTTP response at all or a truncated / malformed one that fetch()
        // interprets as a network-layer failure.

        // 1) Allow up to 10 minutes for slow migrations / seeders.
        @set_time_limit(600);
        @ini_set('max_execution_time', '600');
        @ini_set('memory_limit',        '512M');
        @ini_set('implicit_flush',      '0');

        // 2) Swallow any accidental pre-response echo / PHP notice output.
        //    Without this, a single "PHP Warning:  ... in /vendor/..." line
        //    prepended to the JSON payload breaks JSON.parse → frontend
        //    shows "Unexpected server response", and on some Apache setups
        //    an aborted mid-stream output even looks like a TCP RST to the
        //    browser → fetch .catch() fires → "Network error".
        $outBufLevelStart = ob_get_level();
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        ob_start();

        // 3) Catch PHP FATAL errors (E_ERROR / E_CORE_ERROR / etc.) which
        //    `catch (\Throwable)` CANNOT see. On crash we flush all buffers
        //    and print a clean JSON response the browser's fetch() can read
        //    normally (will hit .then(), not .catch()).
        $fatalShutdownRef = function () use (&$outBufLevelStart): void {
            $err = error_get_last();
            if (! $err) return;
            $fatals = [\E_ERROR, \E_CORE_ERROR, \E_COMPILE_ERROR, \E_USER_ERROR,
                       \E_RECOVERABLE_ERROR, \E_PARSE];
            if (! in_array($err['type'], $fatals, true)) return;

            // Nuke every buffer to make sure our JSON is pristine.
            while (ob_get_level() > 0) @ob_end_clean();

            header('Content-Type: application/json; charset=utf-8', true, 500);
            echo json_encode([
                'success'   => false,
                'error'     => 'PHP Fatal Error: ' . $err['message'],
                'exception' => 'FatalError@' . $err['file'] . ':' . $err['line'],
                'log'       => [
                    '❌ PHP FATAL ERROR (caught by shutdown handler):',
                    '   ' . $err['message'],
                    '   Location: ' . $err['file'] . ':' . $err['line'],
                    '💡 Common causes on local: out of memory during seeder, or a',
                    '   missing table that was expected but SQL import skipped it.',
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        };
        register_shutdown_function($fatalShutdownRef);

        // Scoped helper: flushes ALL output buffers, then returns a clean
        // JsonResponse with ONLY `application/json` content-type — no stray
        // output bytes allowed.  This is the #1 fix for "Failed to fetch".
        $cleanJson = static function (array $payload, int $status = 200): \Illuminate\Http\JsonResponse {
            while (ob_get_level() > 0) @ob_end_clean();
            return response()->json($payload, $status, [
                'Content-Type'                => 'application/json; charset=utf-8',
                'X-Content-Type-Options'      => 'nosniff',
                'Cache-Control'               => 'no-store, no-cache, must-revalidate',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        };

        // ── Local mutable state (declared here so catch block can see) ──
        $log             = [];
        $createdUserId   = null;
        $superAdminLocal = null;
        $adminEmailLocal = null;
        $adminNameLocal  = null;
        $snap            = null;

        try {
            // ═════════════════════════════════════════════════════════════════
            // PRE-CHECKS (also inside try — any error here is still JSON)
            // ═════════════════════════════════════════════════════════════════
            if ($this->isInstallLocked()) {
                return $cleanJson([
                    'success'      => true,
                    'already_done' => true,
                    'redirect_url' => url('/admin'),
                    'log'          => ['Install already locked. Redirecting to dashboard...'],
                ]);
            }

            if (! $this->licenseService->isActivated()) {
                return $cleanJson([
                    'success' => false,
                    'error'   => 'License not activated. Please return to Step 1.',
                    'log'     => ['License activation check FAILED.'],
                ], 403);
            }

            if (! $request->session()->has('admin_email')) {
                $fallback = null;
                try {
                    $fallback = User::whereIn('role', ['super_admin', 'admin'])
                        ->orderByRaw("FIELD(role, 'super_admin', 'admin')")
                        ->first();
                } catch (\Throwable $e) { /* users table might not exist yet */ }

                if (! $fallback) {
                    return $cleanJson([
                        'success' => false,
                        'error'   => 'Admin credentials missing from session. Please complete Step 3 (Admin Setup) first.',
                        'log'     => [
                            '⚠️  Session admin_email key missing.',
                            'ℹ️   No existing admin/super_admin user found in users table either.',
                            '💡 Fix: Go back to /install/admin, fill the form, submit again.',
                        ],
                    ], 400);
                }
            }

            // ═════════════════════════════════════════════════════════════════
            // SESSION SNAPSHOT BEFORE ANY CONFIG CLEAR / ARTISAN CALLS
            // ═════════════════════════════════════════════════════════════════
            $snap = [
                'admin_email'        => $request->session()->get('admin_email'),
                'admin_name'         => $request->session()->get('admin_name'),
                'admin_password'     => $request->session()->get('admin_password'),
                'sql_imported'       => (bool) $request->session()->get('install.sql_imported', false),
                'sql_imported_stats' => $request->session()->get('install.sql_imported_stats'),
                'pending_license'    => $request->session()->get('install.pending_license'),
            ];

            // ── Reload .env + RECONNECT DB ───────────────────────────────
            // NOTE: Artisan::call('config:clear') wipes the in-memory config
            // cache, including the database PDO singleton.  On subsequent DB
            // queries Laravel *should* lazily reconnect, but in practice on
            // Windows + Laragon + mysqlnd the old PDO handle is kept around
            // in a "gone away" state and Schema / DB queries throw warnings
            // or even FATAL.  We force a clean reconnect.
            Artisan::call('config:clear');
            $log[] = '✅ Application config reloaded.';

            try {
                DB::purge();     // forget old PDO handle(s)
                DB::reconnect(); // open a brand-new PDO connection from fresh config
                DB::connection()->getPdo()->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                DB::connection()->getPdo()->query('SELECT 1');  // warm-up ping
                $log[] = '✅ Database connection re-established (fresh PDO after config:clear).';
            } catch (\Throwable $e) {
                $log[] = '⚠️  DB reconnect threw (continuing, Laravel will retry lazily): ' . $e->getMessage();
            }

            // ═════════════════════════════════════════════════════════════════
            // Decide whether to run migrations / seeders.
            // ═════════════════════════════════════════════════════════════════
            $sqlAlreadyImported = $snap['sql_imported'];

            $migrationsTableExists = false;
            try {
                $migrationsTableExists = Schema::hasTable('migrations');
            } catch (\Throwable $e) {
                $migrationsTableExists = false;
            }

            $shouldRunMigrations = true;
            if ($sqlAlreadyImported && $migrationsTableExists) {
                $shouldRunMigrations = false;
            }

            // ── Run migrations ──────────────────────────────────────────────
            if ($shouldRunMigrations) {
                $log[] = '⏳ Running database migrations...';
                try {
                    $migExit = Artisan::call('migrate', ['--force' => true]);
                    $migOutput = Artisan::output();
                    if ($migExit === 0) {
                        $log[] = '✅ Database migrations completed.';
                        if (trim($migOutput) !== '') {
                            foreach (explode("\n", trim($migOutput)) as $mo) {
                                $mo = trim($mo);
                                if ($mo !== '') $log[] = '   ↳ ' . $mo;
                            }
                        }
                    } else {
                        $log[] = '⚠️  Migrations exited with code ' . $migExit . ' — continuing.';
                        if (trim($migOutput) !== '') {
                            foreach (explode("\n", trim($migOutput)) as $mo) {
                                $mo = trim($mo);
                                if ($mo !== '') $log[] = '   ↳ ' . $mo;
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    $log[] = '⚠️  Migrations threw an error — attempting to continue:';
                    $log[] = '   ↳ ' . get_class($e) . ': ' . $e->getMessage();
                }
            } else {
                $log[] = '⏭️  Skipping migrations (pre-imported SQL dump detected — migrations table exists).';
            }

            // After migrations run: DB connection may have been internally
            // closed by the artisan subprocess.  Re-verify / reconnect before
            // we write to users / license tables below.
            try {
                DB::connection()->getPdo()->query('SELECT 1');
            } catch (\Throwable $e) {
                try {
                    DB::purge();
                    DB::reconnect();
                } catch (\Throwable $e2) { /* swallow, will fail later with nicer message */ }
            }

            // ── Persist the verified license (DB table now exists) ──────────
            $pending = $snap['pending_license'];
            if (is_array($pending) && ! empty($pending['purchase_code']) && is_array($pending['api_data'] ?? null)) {
                try {
                    $this->licenseService->persistVerifiedLicense(
                        purchaseCode: $pending['purchase_code'],
                        apiData:      $pending['api_data']
                    );
                    try { $request->session()->forget('install.pending_license'); } catch (\Throwable $_) {}
                    $log[] = '✅ Envato license record persisted to database.';
                } catch (\Throwable $e) {
                    $log[] = '⚠️  License persist skipped (non-fatal): ' . get_class($e) . ' — ' . $e->getMessage();
                }
            } else {
                $log[] = '✅ (Default / bypass license — no CodeCanyon API record to persist.)';
            }

            // ── Run seeders ─────────────────────────────────────────────────
            $log[] = '⏳ Running database seeders (demo data)...';
            try {
                $seedExit = Artisan::call('db:seed', ['--force' => true]);
                $seedOutput = Artisan::output();
                if ($seedExit === 0) {
                    $log[] = '✅ Seeders completed.';
                    if (trim($seedOutput) !== '') {
                        foreach (explode("\n", trim($seedOutput)) as $so) {
                            $so = trim($so);
                            if ($so !== '') $log[] = '   ↳ ' . $so;
                        }
                    }
                } else {
                    $log[] = '⚠️  Seeders returned exit code ' . $seedExit . ' — continuing anyway.';
                    if (trim($seedOutput) !== '') {
                        foreach (explode("\n", trim($seedOutput)) as $so) {
                            $so = trim($so);
                            if ($so !== '') $log[] = '   ↳ ' . $so;
                        }
                    }
                }
            } catch (\Throwable $e) {
                $log[] = '⚠️  Seeders threw an error — continuing (non-fatal):';
                $log[] = '   ↳ ' . get_class($e) . ': ' . $e->getMessage();
            }

            // DB re-connect / ping after seeder Artisan subprocess
            try {
                DB::connection()->getPdo()->query('SELECT 1');
            } catch (\Throwable $e) {
                try {
                    DB::purge();
                    DB::reconnect();
                } catch (\Throwable $e2) { /* swallow */ }
            }

            // ── Create / promote SUPER ADMIN user ──────────────────────────
            $adminEmailLocal = $snap['admin_email'];
            $adminNameLocal  = $snap['admin_name'];
            $adminPass       = $snap['admin_password'];

            $superAdminLocal = null;

            if (empty($adminEmailLocal)) {
                $log[] = '⚠️  Admin credentials empty in snapshot — searching DB for existing admin...';
                try {
                    $fb = User::whereIn('role', ['super_admin', 'admin'])
                        ->orderByRaw("FIELD(role, 'super_admin', 'admin')")
                        ->first();
                    if ($fb instanceof User) {
                        $superAdminLocal = $fb;
                        $adminEmailLocal = $fb->email;
                        $adminNameLocal  = $fb->name;
                        $log[] = '✅ Fallback success: found existing ' . strtoupper($fb->role) . ' → ' . $fb->email . ' (will auto-sign-in).';
                    }
                } catch (\Throwable $e) {
                    $log[] = 'ℹ️   Could not search users table: ' . $e->getMessage();
                }
            }

            if (! empty($adminEmailLocal)) {
                $existing = null;
                try {
                    $existing = User::where('email', $adminEmailLocal)->first();
                } catch (\Throwable $e) {
                    $log[] = 'ℹ️   Users table query failed — assuming new user: ' . $e->getMessage();
                }

                if (! $existing) {
                    $log[] = '⏳ Creating SUPER ADMIN user: ' . $adminEmailLocal . ' ...';
                    try {
                        $superAdminLocal = User::create([
                            'name'              => $adminNameLocal ?? 'Super Admin',
                            'email'             => $adminEmailLocal,
                            'password'          => Hash::make($adminPass ?? 'ChangeMe123!'),
                            'role'              => 'super_admin',
                            'menu_permissions'  => null,
                            'email_verified_at' => now(),
                        ]);
                        $createdUserId = $superAdminLocal?->id;
                        $log[] = '✅ SUPER ADMIN ' . $adminEmailLocal . ' created.';
                    } catch (\Throwable $e) {
                        $log[] = '⚠️  User::create failed — searching for existing record instead: ' . $e->getMessage();
                        try {
                            $superAdminLocal = User::where('email', $adminEmailLocal)->first();
                            $createdUserId   = $superAdminLocal?->id;
                            if ($superAdminLocal) $log[] = '✅ Found user record after all → ' . $superAdminLocal->email;
                        } catch (\Throwable $e2) {
                            $log[] = '❌ Could not recover user creation: ' . $e2->getMessage();
                        }
                    }
                } else {
                    $log[] = '⏳ Existing user found. Promoting to SUPER ADMIN: ' . $adminEmailLocal . ' ...';
                    try {
                        $existing->update([
                            'name'              => $adminNameLocal ?? $existing->name,
                            'role'              => 'super_admin',
                            'menu_permissions'  => null,
                            'email_verified_at' => $existing->email_verified_at ?? now(),
                        ]);
                        if (! empty($adminPass)) {
                            $existing->update(['password' => Hash::make($adminPass)]);
                        }
                        $superAdminLocal = $existing->fresh();
                        $createdUserId   = $superAdminLocal?->id;
                        $log[] = '✅ ' . $adminEmailLocal . ' promoted to SUPER ADMIN.';
                    } catch (\Throwable $e) {
                        $superAdminLocal = $existing->fresh();
                        $createdUserId   = $superAdminLocal?->id;
                        $log[] = '⚠️  Promote update threw but user already exists — continuing anyway: ' . $e->getMessage();
                    }
                }
            }

            // ── Lock the installer ─────────────────────────────────────────
            $log[] = '⏳ Locking install wizard...';
            try {
                app(\App\Services\EnvFileWriter::class)->set(['APP_INSTALLED' => 'true']);
                $markerFile = storage_path('framework/installed');
                @file_put_contents($markerFile, date('Y-m-d H:i:s'));
                $log[] = '✅ Installer locked. APP_INSTALLED=true + storage/framework/installed marker created.';
            } catch (\Throwable $e) {
                $log[] = '⚠️  Lock step had an error — install still continues: ' . $e->getMessage();
            }

            // ── Clear all caches ─────────────────────────────────────────────
            try {
                Artisan::call('cache:clear');
                Artisan::call('config:clear');
                Artisan::call('route:clear');
                Artisan::call('view:clear');
                $log[] = '✅ All caches cleared (config, route, view, app cache).';
            } catch (\Throwable $e) {
                $log[] = '⚠️  Cache clear non-fatal error: ' . $e->getMessage();
            }

            // ── DB session / Auth reconnect after the 2nd config:clear above ─
            try {
                DB::connection()->getPdo()->query('SELECT 1');
            } catch (\Throwable $e) {
                try {
                    DB::purge();
                    DB::reconnect();
                } catch (\Throwable $e2) { /* swallow */ }
            }

            // ── Auto-login the SUPER ADMIN into the web session ────────────
            if ($superAdminLocal instanceof User) {
                try {
                    // Force a fresh session driver instance to work around the
                    // in-memory config we just wiped.  Without this, on many
                    // Laravel setups session()->save() silently fails → the
                    // Auth::login below is never persisted → the final
                    // redirect to /admin hits /admin/login → confused user.
                    try {
                        $request->session()->save();
                    } catch (\Throwable $_) {}
                    try {
                        app('session')->driver()->start();
                    } catch (\Throwable $_) {}

                    Auth::login($superAdminLocal, true);
                    $request->session()->regenerate();
                    $log[] = '✅ Authenticated session started for ' . $superAdminLocal->email . ' (remember_me=ON).';
                } catch (\Throwable $e) {
                    $log[] = '⚠️  Auto-login failed (non-fatal, you can sign-in manually): ' . $e->getMessage();
                }
            }

            // ── Flush install-specific session keys ─────────────────────────
            try {
                $request->session()->forget([
                    'admin_name', 'admin_email', 'admin_password',
                    'install.pending_license',
                    'install.sql_imported',
                    'install.sql_imported_stats',
                ]);
            } catch (\Throwable $_) {}

            $log[] = '';
            $log[] = '🎉 Installation complete! Redirecting to admin dashboard...';

            return $cleanJson([
                'success'      => true,
                'log'          => $log,
                'redirect_url' => url('/admin'),
                'admin_email'  => $superAdminLocal?->email ?? $adminEmailLocal,
                'admin_name'   => $superAdminLocal?->name  ?? $adminNameLocal,
            ]);

        } catch (\Throwable $e) {
            $log[] = '❌ FATAL ERROR during installation:';
            $log[] = '   ' . get_class($e) . ': ' . $e->getMessage();
            $log[] = '   in ' . $e->getFile() . ':' . $e->getLine();

            // Append stack trace lines (first 8) so user can paste to debug
            $trace = explode("\n", $e->getTraceAsString());
            foreach (array_slice($trace, 0, 8) as $tl) {
                $log[] = '   ↳ ' . $tl;
            }

            return $cleanJson([
                'success'         => false,
                'error'           => $e->getMessage(),
                'exception'       => get_class($e),
                'log'             => $log,
                'created_user_id' => $createdUserId,
            ], 500);
        }
    }
}
