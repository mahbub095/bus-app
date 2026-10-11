<?php

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use App\Services\LicenseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

/**
 * Handles the step-by-step installation wizard.
 *
 * Steps:
 *   1. License — verifies Envato purchase code (stored in session)
 *   2. Database — tests connection, writes to .env
 *   3. Finalize — creates admin, runs migrations, locks installer
 */
class InstallController extends Controller
{
    public function __construct(protected LicenseService $licenseService) {}

    /**
     * Returns true when APP_INSTALLED=true — prevents re-running the wizard.
     */
    private function isInstallLocked(): bool
    {
        $flag = env('APP_INSTALLED', false);
        return $flag === true
            || $flag === 1
            || (is_string($flag) && in_array(strtolower($flag), ['true', '1'], true));
    }

    /**
     * Returns true when a pending license is in session OR already activated in DB.
     */
    private function hasValidLicense(): bool
    {
        return session()->has('install.pending_license')
            || $this->licenseService->isActivated();
    }

    // ─── Step 1: License ──────────────────────────────────────────────────────

    public function showLicense()
    {
        if ($this->isInstallLocked()) {
            return redirect('/admin/login');
        }
        return view('install.license');
    }

    public function verifyLicense(Request $request)
    {
        if ($this->isInstallLocked()) {
            return redirect('/admin/login');
        }

        $request->validate([
            'purchase_code' => 'required|string|max:100',
        ]);

        $result = $this->licenseService->verify(
            purchaseCode: $request->input('purchase_code'),
            persistDb:    false
        );

        if (! $result['success']) {
            return back()->withErrors(['purchase_code' => $result['message']])->withInput();
        }

        session()->put('install.pending_license', [
            'purchase_code' => trim($request->input('purchase_code')),
            'api_data'      => $result['api_data'] ?? [],
        ]);

        return redirect('/install/database')->with('success', $result['message']);
    }

    // ─── Step 2: Database ─────────────────────────────────────────────────────

    public function showDatabase()
    {
        if ($this->isInstallLocked()) {
            return redirect('/admin/login');
        }
        if (! $this->hasValidLicense()) {
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

        // Test connection
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

        // Write to .env
        app(\App\Services\EnvFileWriter::class)->set([
            'DB_HOST'     => $request->db_host,
            'DB_PORT'     => (string) $request->db_port,
            'DB_DATABASE' => $request->db_database,
            'DB_USERNAME' => $request->db_username,
            'DB_PASSWORD' => $request->db_password ?? '',
        ]);

        // Reload DB config in-memory without calling config:clear
        // (config:clear kills php artisan serve on Windows/local dev)
        config([
            'database.connections.mysql.host'     => $request->db_host,
            'database.connections.mysql.port'     => $request->db_port,
            'database.connections.mysql.database' => $request->db_database,
            'database.connections.mysql.username' => $request->db_username,
            'database.connections.mysql.password' => $request->db_password ?? '',
        ]);
        DB::purge('mysql');
        DB::reconnect('mysql');

        // Optional SQL import
        if ($request->hasFile('sql_file') && $request->file('sql_file')->isValid()) {
            try {
                $sql = file_get_contents($request->file('sql_file')->getRealPath());
                $this->importSqlFile($testPdo, $sql);
                session()->put('install.sql_imported', true);
            } catch (\Throwable $e) {
                return back()
                    ->withErrors(['sql_file' => 'SQL import failed: ' . $e->getMessage()])
                    ->withInput();
            }
        }

        return redirect('/install/finalize')->with('success', 'Database connection verified.');
    }

    // ─── Step 3: Finalize ────────────────────────────────────────────────────

    public function showFinalize(Request $request)
    {
        if ($this->isInstallLocked()) {
            return redirect('/admin/login');
        }
        if (! $this->hasValidLicense()) {
            return redirect('/install');
        }
        return view('install.finalize');
    }

    public function runFinalize(Request $request)
    {
        if ($this->isInstallLocked()) {
            return redirect('/admin/login');
        }

        // Validate admin form fields submitted directly from the finalize page
        $request->validate([
            'admin_name'     => 'required|string|max:100',
            'admin_email'    => 'required|email|max:100',
            'admin_password' => 'required|string|min:8',
        ]);

        $adminName  = $request->input('admin_name');
        $adminEmail = $request->input('admin_email');
        $adminPass  = $request->input('admin_password');
        $sqlImported    = session()->get('install.sql_imported', false);
        $pendingLicense = session()->get('install.pending_license');

        try {
            // 1. Migrations — skip if tables already exist
            if (! $sqlImported) {
                try {
                    DB::connection()->getPdo()->query('SELECT 1 FROM users LIMIT 1');
                } catch (\Throwable $_) {
                    Artisan::call('migrate', ['--force' => true]);
                    DB::purge();
                    DB::reconnect();
                }
            }

            // 2. Persist license record
            if (is_array($pendingLicense) && ! empty($pendingLicense['purchase_code'])) {
                try {
                    $this->licenseService->persistVerifiedLicense(
                        purchaseCode: $pendingLicense['purchase_code'],
                        apiData:      $pendingLicense['api_data'] ?? [],
                    );
                } catch (\Throwable $e) {
                    // Non-fatal
                }
            }

            // 3. Create / update super admin
            $existing = User::where('email', $adminEmail)->first();
            if ($existing) {
                $existing->update([
                    'name'              => $adminName,
                    'role'              => 'super_admin',
                    'menu_permissions'  => null,
                    'password'          => Hash::make($adminPass),
                    'email_verified_at' => $existing->email_verified_at ?? now(),
                ]);
            } else {
                User::create([
                    'name'              => $adminName,
                    'email'             => $adminEmail,
                    'password'          => Hash::make($adminPass),
                    'role'              => 'super_admin',
                    'menu_permissions'  => null,
                    'email_verified_at' => now(),
                ]);
            }

            // 5. Lock installer
            app(\App\Services\EnvFileWriter::class)->set(['APP_INSTALLED' => 'true']);
            @file_put_contents(storage_path('framework/installed'), now()->toDateTimeString());

            // 6. Clear caches safely (no artisan calls that kill php artisan serve)
            // Flush file-based cache manually
            try {
                \Illuminate\Support\Facades\Cache::flush();
            } catch (\Throwable $_) {}

            // Clear compiled views
            try {
                $viewCachePath = storage_path('framework/views');
                if (is_dir($viewCachePath)) {
                    foreach (glob($viewCachePath . '/*.php') as $file) {
                        @unlink($file);
                    }
                }
            } catch (\Throwable $_) {}

            // 7. Clean session
            session()->forget(['install.pending_license', 'install.sql_imported']);

            return redirect('/install/complete');

        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Install failed: ' . $e->getMessage(), [
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);
            return back()
                ->withErrors(['message' => 'Installation failed: ' . $e->getMessage()])
                ->withInput();
        }
    }

    public function complete()
    {
        return view('install.complete');
    }

    // ─── SQL import helper ───────────────────────────────────────────────────

    private function importSqlFile(\PDO $pdo, string $sql): void
    {
        $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql);
        $sql = preg_replace('/^--.*$/m', '', $sql);
        $sql = preg_replace('/^#.*$/m',  '', $sql);
        $sql = preg_replace('!/\*.*?\*/!s', '', $sql);

        $statements = explode(';', $sql);
        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || preg_match('/^(use\s|delimiter\s)/i', $stmt)) {
                continue;
            }
            try {
                $pdo->exec($stmt);
            } catch (\PDOException $e) {
                $msg = $e->getMessage();
                if (stripos($msg, 'already exist') === false && stripos($msg, 'Duplicate') === false) {
                    throw new \RuntimeException('SQL failed: ' . $msg);
                }
            }
        }
    }
}
