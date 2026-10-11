<?php

namespace App\Http\Controllers\Install;

use App\Http\Controllers\Controller;
use App\Services\LicenseService;
use Illuminate\Http\Request;

/**
 * Installation wizard — 3 steps:
 *   1. /install              License verification
 *   2. /install/database     DB credentials test + write to .env
 *   3. /install/finalize     Admin account creation + background install
 *      /install/complete     Success page with optional demo data import
 */
class InstallController extends Controller
{
    public function __construct(protected LicenseService $licenseService) {}

    // ─── Guards ──────────────────────────────────────────────────────────────

    private function isInstallLocked(): bool
    {
        $flag = env('APP_INSTALLED', false);
        return $flag === true
            || $flag === 1
            || (is_string($flag) && in_array(strtolower($flag), ['true', '1'], true));
    }

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
            purchaseCode: trim($request->input('purchase_code')),
            persistDb:    false,
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

    // ─── Step 2: Database ────────────────────────────────────────────────────

    public function showDatabase()
    {
        if ($this->isInstallLocked()) return redirect('/admin/login');
        if (! $this->hasValidLicense()) return redirect('/install');
        return view('install.database');
    }

    public function saveDatabase(Request $request)
    {
        if ($this->isInstallLocked()) return redirect('/admin/login');

        $request->validate([
            'db_host'     => 'required|string|max:255',
            'db_port'     => 'required|integer|min:1|max:65535',
            'db_database' => 'required|string|max:255',
            'db_username' => 'required|string|max:255',
            'db_password' => 'nullable|string|max:255',
            'sql_file'    => 'nullable|file|mimes:sql|max:200000',
        ]);

        $host  = $request->db_host;
        $port  = $request->db_port;
        $db    = $request->db_database;
        $user  = $request->db_username;
        $pass  = $request->db_password ?? '';

        // 1. Test connection via raw PDO — no Laravel DB facade involved
        try {
            $pdo = new \PDO(
                "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",
                $user, $pass,
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_TIMEOUT => 5]
            );
        } catch (\PDOException $e) {
            return back()
                ->withErrors(['db_host' => 'Connection failed: ' . $e->getMessage()])
                ->withInput();
        }

        // 2. Write credentials to .env (no config reload, no DB::purge)
        app(\App\Services\EnvFileWriter::class)->set([
            'DB_HOST'     => $host,
            'DB_PORT'     => (string) $port,
            'DB_DATABASE' => $db,
            'DB_USERNAME' => $user,
            'DB_PASSWORD' => $pass,
        ]);

        // Store sql_imported flag in session for the finalize step
        if ($request->hasFile('sql_file') && $request->file('sql_file')->isValid()) {
            try {
                $this->importSqlFile($pdo, file_get_contents($request->file('sql_file')->getRealPath()));
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
        if ($this->isInstallLocked()) return redirect('/admin/login');
        if (! $this->hasValidLicense()) return redirect('/install');
        return view('install.finalize');
    }

    /**
     * POST /install/finalize/run
     *
     * Fires the background install:finalize command which handles:
     *   - migrate (with migrate:install first)
     *   - persist license
     *   - create super admin
     *   - lock installer
     *
     * Returns JSON so the finalize page can poll for status.
     */
    public function runFinalize(Request $request): \Illuminate\Http\JsonResponse
    {
        if ($this->isInstallLocked()) {
            return response()->json(['error' => 'Already installed.'], 400);
        }

        $request->validate([
            'admin_name'     => 'required|string|max:100',
            'admin_email'    => 'required|email|max:100',
            'admin_password' => 'required|string|min:8',
        ]);

        // Store admin credentials in cache for the background command
        $ttl = now()->addMinutes(10);
        cache(['install_admin_name'     => $request->input('admin_name')],     $ttl);
        cache(['install_admin_email'    => $request->input('admin_email')],    $ttl);
        cache(['install_admin_password' => $request->input('admin_password')], $ttl);
        cache(['install_sql_imported'   => session()->get('install.sql_imported', false)], $ttl);
        cache(['install_pending_license'=> session()->get('install.pending_license')], $ttl);

        // One-time token
        $token = bin2hex(random_bytes(16));
        cache(['install_finalize_token' => $token], $ttl);

        // Reset status file
        $statusFile = \App\Console\Commands\InstallFinalize::statusFile();
        @file_put_contents($statusFile, json_encode([
            'status' => 'starting',
            'log'    => ['Starting installation...'],
        ]));

        // Launch background process
        $php     = PHP_BINARY;
        $artisan = base_path('artisan');

        if (PHP_OS_FAMILY === 'Windows') {
            $cmd = 'start /B "" "' . $php . '" "' . $artisan . '" install:finalize --token=' . $token . ' > NUL 2>&1';
            pclose(popen($cmd, 'r'));
        } else {
            exec('"' . $php . '" "' . $artisan . '" install:finalize --token=' . $token . ' > /dev/null 2>&1 &');
        }

        return response()->json(['started' => true]);
    }

    /**
     * GET /install/finalize/status
     */
    public function finalizeStatus(): \Illuminate\Http\JsonResponse
    {
        $file = \App\Console\Commands\InstallFinalize::statusFile();
        if (! file_exists($file)) {
            return response()->json(['status' => 'waiting', 'log' => []]);
        }
        $data = json_decode(file_get_contents($file), true);
        return response()->json(is_array($data) ? $data : ['status' => 'waiting', 'log' => []]);
    }

    public function complete()
    {
        return view('install.complete');
    }

    // ─── Demo seed endpoints ──────────────────────────────────────────────────

    public function startDemoSeed(Request $request): \Illuminate\Http\JsonResponse
    {
        $token = bin2hex(random_bytes(16));
        cache(['install_demo_seed_token' => $token], now()->addMinutes(5));

        @file_put_contents(
            \App\Console\Commands\InstallDemoSeed::statusFile(),
            json_encode(['status' => 'starting', 'log' => ['Starting...']])
        );

        $php     = PHP_BINARY;
        $artisan = base_path('artisan');

        if (PHP_OS_FAMILY === 'Windows') {
            $cmd = 'start /B "" "' . $php . '" "' . $artisan . '" install:demo-seed --token=' . $token . ' > NUL 2>&1';
            pclose(popen($cmd, 'r'));
        } else {
            exec('"' . $php . '" "' . $artisan . '" install:demo-seed --token=' . $token . ' > /dev/null 2>&1 &');
        }

        return response()->json(['started' => true]);
    }

    public function demoSeedStatus(): \Illuminate\Http\JsonResponse
    {
        $file = \App\Console\Commands\InstallDemoSeed::statusFile();
        if (! file_exists($file)) {
            return response()->json(['status' => 'waiting', 'log' => []]);
        }
        $data = json_decode(file_get_contents($file), true);
        return response()->json(is_array($data) ? $data : ['status' => 'waiting', 'log' => []]);
    }

    // ─── SQL import helper ────────────────────────────────────────────────────

    private function importSqlFile(\PDO $pdo, string $sql): void
    {
        $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql);
        $sql = preg_replace('/^--.*$/m', '', $sql);
        $sql = preg_replace('/^#.*$/m',  '', $sql);
        $sql = preg_replace('!/\*.*?\*/!s', '', $sql);

        foreach (explode(';', $sql) as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || preg_match('/^(use\s|delimiter\s)/i', $stmt)) continue;
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
