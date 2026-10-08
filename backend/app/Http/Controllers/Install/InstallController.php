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

        return redirect('/install/finalize')->with([
            'admin_name'     => $request->name,
            'admin_email'    => $request->email,
            'admin_password' => $request->password,
        ]);
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
        if ($this->isInstallLocked()) {
            return response()->json([
                'success'      => true,
                'already_done' => true,
                'redirect_url' => url('/admin'),
                'log'          => ['Install already locked. Redirecting to dashboard...'],
            ]);
        }

        if (! $this->licenseService->isActivated()) {
            return response()->json([
                'success' => false,
                'error'   => 'License not activated. Please return to Step 1.',
                'log'     => ['License activation check FAILED.'],
            ], 403);
        }

        if (! $request->session()->has('admin_email')) {
            return response()->json([
                'success' => false,
                'error'   => 'Admin credentials missing from session. Please complete Step 3.',
                'log'     => ['Admin credentials missing.'],
            ], 400);
        }

        $log = [];
        $createdUserId = null;

        try {
            // ── Reload .env values now that DB credentials have been written ─
            Artisan::call('config:clear');
            $log[] = '✅ Application config reloaded.';

            // ═════════════════════════════════════════════════════════════════
            // Decide whether to run migrations / seeders.
            // ═════════════════════════════════════════════════════════════════
            $sqlAlreadyImported = (bool) $request->session()->get('install.sql_imported', false);

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
                Artisan::call('migrate', ['--force' => true]);
                $log[] = '✅ Database migrations completed.';
            } else {
                $log[] = '⏭️  Skipping migrations (pre-imported SQL dump detected — migrations table exists).';
            }

            // ── Persist the verified license (DB table now exists) ──────────
            $pending = $request->session()->get('install.pending_license');
            if (is_array($pending) && ! empty($pending['purchase_code']) && is_array($pending['api_data'] ?? null)) {
                $this->licenseService->persistVerifiedLicense(
                    purchaseCode: $pending['purchase_code'],
                    apiData:      $pending['api_data']
                );
                $request->session()->forget('install.pending_license');
                $log[] = '✅ Envato license record persisted to database.';
            } else {
                $log[] = '✅ (Default / bypass license — no CodeCanyon API record to persist.)';
            }

            // ── Run seeders ─────────────────────────────────────────────────
            if ($shouldRunMigrations) {
                $log[] = '⏳ Running database seeders (demo data)...';
                Artisan::call('db:seed', ['--force' => true]);
                $log[] = '✅ Seeders completed.';
            } else {
                $log[] = '⏭️  Skipping seeders (pre-imported full SQL dump detected).';
            }

            // ── Create / promote SUPER ADMIN user ──────────────────────────
            $adminEmail = $request->session()->get('admin_email');
            $adminName  = $request->session()->get('admin_name');
            $adminPass  = $request->session()->get('admin_password');

            $superAdmin = null;

            if (! empty($adminEmail)) {
                $existing = User::where('email', $adminEmail)->first();
                if (! $existing) {
                    $log[] = '⏳ Creating SUPER ADMIN user: ' . $adminEmail . ' ...';
                    $superAdmin = User::create([
                        'name'              => $adminName,
                        'email'             => $adminEmail,
                        'password'          => Hash::make($adminPass),
                        'role'              => 'super_admin',
                        'menu_permissions'  => null,  // super admin = ALL menus
                        'email_verified_at' => now(),
                    ]);
                    $log[] = '✅ SUPER ADMIN ' . $adminEmail . ' created (role = super_admin, all permissions granted).';
                } else {
                    $log[] = '⏳ Existing user found. Promoting to SUPER ADMIN: ' . $adminEmail . ' ...';
                    $existing->update([
                        'name'              => $adminName ?? $existing->name,
                        'role'              => 'super_admin',
                        'menu_permissions'  => null,
                        'email_verified_at' => $existing->email_verified_at ?? now(),
                    ]);
                    if (! empty($adminPass)) {
                        $existing->update(['password' => Hash::make($adminPass)]);
                    }
                    $superAdmin = $existing->fresh();
                    $log[] = '✅ ' . $adminEmail . ' promoted to SUPER ADMIN (role = super_admin, all permissions).';
                }
                $createdUserId = $superAdmin?->id;
            }

            // ── Lock the installer (APP_INSTALLED=true in .env) ─────────────
            $log[] = '⏳ Locking install wizard...';
            app(\App\Services\EnvFileWriter::class)->set([
                'APP_INSTALLED' => 'true',
            ]);
            $markerFile = storage_path('framework/installed');
            @file_put_contents($markerFile, date('Y-m-d H:i:s'));
            $log[] = '✅ Installer locked. APP_INSTALLED=true + storage/framework/installed marker created.';

            // ── Clear all caches ─────────────────────────────────────────────
            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            $log[] = '✅ All caches cleared (config, route, view, app cache).';

            // ── Auto-login the SUPER ADMIN into the web session ────────────
            if ($superAdmin instanceof User) {
                Auth::login($superAdmin, true); // remember = true
                $request->session()->regenerate();
                $log[] = '✅ Authenticated session started for ' . $superAdmin->email . ' (remember_me=ON).';
            }

            // ── Flush install-specific session keys ─────────────────────────
            $request->session()->forget([
                'admin_name', 'admin_email', 'admin_password',
                'install.pending_license',
                'install.sql_imported',
                'install.sql_imported_stats',
            ]);

            $log[] = '';
            $log[] = '🎉 Installation complete! Redirecting to admin dashboard...';

            return response()->json([
                'success'      => true,
                'log'          => $log,
                'redirect_url' => url('/admin'),
                'admin_email'  => $superAdmin?->email ?? $adminEmail,
                'admin_name'   => $superAdmin?->name  ?? $adminName,
            ]);

        } catch (\Throwable $e) {
            $log[] = '❌ FATAL ERROR during installation:';
            $log[] = '   ' . get_class($e) . ': ' . $e->getMessage();
            $log[] = '   in ' . $e->getFile() . ':' . $e->getLine();

            return response()->json([
                'success'        => false,
                'error'          => $e->getMessage(),
                'exception'      => get_class($e),
                'log'            => $log,
                'created_user_id'=> $createdUserId,
            ], 500);
        }
    }
}
