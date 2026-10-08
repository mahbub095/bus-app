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
            return redirect('/admin/login');
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
            return redirect('/admin/login');
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
            return redirect('/admin/login');
        }

        if (! $this->licenseService->isActivated()) {
            return redirect('/install');
        }

        return view('install.database');
    }

    public function saveDatabase(Request $request)
    {
        if ($this->isInstallLocked()) {
            return redirect('/admin/login');
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
            return redirect('/admin/login');
        }

        if (! $this->licenseService->isActivated()) {
            return redirect('/install');
        }

        return view('install.admin');
    }

    public function saveAdmin(Request $request)
    {
        if ($this->isInstallLocked()) {
            return redirect('/admin/login');
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
     * Shows a "click to install" confirmation page. The actual work is done
     * by runFinalize() via a plain synchronous form POST — no AJAX/fetch.
     */
    public function finalize(Request $request)
    {
        if ($this->isInstallLocked()) {
            return redirect('/admin/login');
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
     * Stashes admin credentials into the cache, fires the install:run
     * artisan command as a background process (so Apache never times out),
     * then immediately redirects back to GET /install/finalize which polls
     * the status file for progress.
     */
    public function runFinalize(Request $request): \Illuminate\Http\RedirectResponse
    {
        if ($this->isInstallLocked()) {
            return redirect('/admin/login');
        }

        if (! $this->licenseService->isActivated()) {
            return redirect('/install')->withErrors(['message' => 'License not activated.']);
        }

        if (! $request->session()->has('admin_email')) {
            return redirect('/install/admin')->withErrors([
                'message' => 'Admin credentials missing. Please re-submit the admin setup form.',
            ]);
        }

        // ── Stash session data into file-based cache so the background ──
        // process can read it (the background process has no HTTP session).
        $ttl = now()->addMinutes(10);
        cache(['install_admin_email'    => $request->session()->get('admin_email')]   , $ttl);
        cache(['install_admin_name'     => $request->session()->get('admin_name')]    , $ttl);
        cache(['install_admin_password' => $request->session()->get('admin_password')], $ttl);
        cache(['install_sql_imported'   => $request->session()->get('install.sql_imported', false)], $ttl);
        cache(['install_pending_license'=> $request->session()->get('install.pending_license')], $ttl);

        // ── Generate a one-time token so the command can't be triggered ──
        // by a random CLI call.
        $token = bin2hex(random_bytes(16));
        cache(['install_run_token' => $token], $ttl);

        // ── Reset any previous status file ──────────────────────────────
        $statusFile = \App\Console\Commands\InstallRun::statusFile();
        @file_put_contents($statusFile, json_encode([
            'status' => 'starting',
            'log'    => ['⏳ Starting background install process...'],
        ]));

        // ── Find PHP executable (same one running Apache right now) ─────
        $php = PHP_BINARY;   // e.g. C:\laragon\bin\php\php-8.x.x\php.exe
        $artisan = base_path('artisan');

        // ── Fire the artisan command as a detached background process ───
        // On Windows we use `start /B` so it detaches from Apache's process.
        // On Linux/Mac we append `> /dev/null 2>&1 &`.
        if (PHP_OS_FAMILY === 'Windows') {
            $cmd = 'start /B "" "' . $php . '" "' . $artisan . '" install:run --token=' . escapeshellarg($token) . ' > NUL 2>&1';
            pclose(popen($cmd, 'r'));
        } else {
            $cmd = '"' . $php . '" "' . $artisan . '" install:run --token=' . escapeshellarg($token) . ' > /dev/null 2>&1 &';
            exec($cmd);
        }

        // ── Redirect immediately — browser will now poll /finalize/status ─
        return redirect('/install/finalize?running=1');
    }

    /**
     * GET /install/finalize/status
     * Returns the current install status as JSON. Called every 2s by the
     * polling page (finalize.blade.php). Never blocks — just reads a file.
     */
    public function finalizeStatus(): \Illuminate\Http\JsonResponse
    {
        $file = \App\Console\Commands\InstallRun::statusFile();

        if (! file_exists($file)) {
            return response()->json([
                'status' => 'waiting',
                'log'    => ['⏳ Waiting for background process to start...'],
            ]);
        }

        $data = json_decode(file_get_contents($file), true);
        if (! is_array($data)) {
            return response()->json(['status' => 'waiting', 'log' => []]);
        }

        return response()->json($data);
    }

}

