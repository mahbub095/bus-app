<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\LicenseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Runs the full installation in the background.
 * Writes progress to storage/framework/install-status.json so the
 * browser can poll it and show live progress without waiting for Apache.
 *
 * Usage (called internally by InstallController, not by hand):
 *   php artisan install:run --token=<token>
 */
class InstallRun extends Command
{
    protected $signature   = 'install:run {--token= : Security token written by the controller}';
    protected $description = 'Run the installation steps in the background (called by the install wizard)';

    /** Path to the status file the browser polls. */
    public static function statusFile(): string
    {
        return storage_path('framework/install-status.json');
    }

    /** Write / merge fields into the status JSON. */
    private function writeStatus(array $data): void
    {
        $current = [];
        $file    = self::statusFile();
        if (file_exists($file)) {
            $current = json_decode(file_get_contents($file), true) ?? [];
        }
        $merged = array_merge($current, $data);
        file_put_contents($file, json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /** Append a log line. */
    private function log(string $line): void
    {
        $file    = self::statusFile();
        $current = [];
        if (file_exists($file)) {
            $current = json_decode(file_get_contents($file), true) ?? [];
        }
        $current['log'][] = $line;
        file_put_contents($file, json_encode($current, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    public function handle(): int
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '512M');

        // ── Validate token (prevent direct CLI abuse) ──────────────────
        $expectedToken = cache('install_run_token');
        $givenToken    = $this->option('token');
        if (! $expectedToken || $givenToken !== $expectedToken) {
            $this->error('Invalid or missing install token.');
            return 1;
        }
        cache()->forget('install_run_token');

        // ── Initialise status file ─────────────────────────────────────
        file_put_contents(self::statusFile(), json_encode([
            'status'     => 'running',
            'started_at' => now()->toDateTimeString(),
            'log'        => [],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        try {
            // ── 1. DB connect ──────────────────────────────────────────
            $this->log('⏳ Connecting to database...');
            DB::purge();
            DB::reconnect();
            DB::connection()->getPdo()->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            DB::connection()->getPdo()->query('SELECT 1');
            $this->log('✅ Database connection established.');

            // ── 2. Migrations ──────────────────────────────────────────
            $sqlImported         = (bool) cache('install_sql_imported', false);
            $migrationsTableExists = false;
            try { $migrationsTableExists = Schema::hasTable('migrations'); } catch (\Throwable $_) {}

            if ($sqlImported && $migrationsTableExists) {
                $this->log('⏭️  Skipping migrations (SQL already imported).');
            } else {
                $this->log('⏳ Running database migrations...');
                try {
                    $exit   = Artisan::call('migrate', ['--force' => true]);
                    $output = trim(Artisan::output());
                    $this->log($exit === 0 ? '✅ Migrations completed.' : '⚠️  Migrations exit=' . $exit);
                    if ($output) {
                        foreach (explode("\n", $output) as $line) {
                            if (trim($line)) $this->log('   ↳ ' . trim($line));
                        }
                    }
                } catch (\Throwable $e) {
                    $this->log('⚠️  Migration error (continuing): ' . $e->getMessage());
                }
            }

            // Reconnect after migrate subprocess
            try {
                DB::connection()->getPdo()->query('SELECT 1');
            } catch (\Throwable $_) {
                DB::purge(); DB::reconnect();
            }

            // ── 3. Persist license ─────────────────────────────────────
            $pending = cache('install_pending_license');
            if (is_array($pending) && ! empty($pending['purchase_code'])) {
                try {
                    app(LicenseService::class)->persistVerifiedLicense(
                        purchaseCode: $pending['purchase_code'],
                        apiData:      $pending['api_data'] ?? [],
                    );
                    cache()->forget('install_pending_license');
                    $this->log('✅ License record persisted to database.');
                } catch (\Throwable $e) {
                    $this->log('⚠️  License persist skipped: ' . $e->getMessage());
                }
            } else {
                $this->log('✅ No pending license record (bypass/default license).');
            }

            // ── 4. Seeders ─────────────────────────────────────────────
            $this->log('⏳ Running database seeders...');
            try {
                $exit   = Artisan::call('db:seed', ['--force' => true]);
                $output = trim(Artisan::output());
                $this->log($exit === 0 ? '✅ Seeders completed.' : '⚠️  Seeders exit=' . $exit);
                if ($output) {
                    foreach (explode("\n", $output) as $line) {
                        if (trim($line)) $this->log('   ↳ ' . trim($line));
                    }
                }
            } catch (\Throwable $e) {
                $this->log('⚠️  Seeder error (continuing): ' . $e->getMessage());
            }

            // Reconnect after seed subprocess
            try {
                DB::connection()->getPdo()->query('SELECT 1');
            } catch (\Throwable $_) {
                DB::purge(); DB::reconnect();
            }

            // ── 5. Super admin user ────────────────────────────────────
            $adminEmail = cache('install_admin_email');
            $adminName  = cache('install_admin_name');
            $adminPass  = cache('install_admin_password');

            $superAdmin = null;

            if (! empty($adminEmail)) {
                $this->log('⏳ Creating super admin: ' . $adminEmail . ' ...');
                try {
                    $existing = User::where('email', $adminEmail)->first();
                    if (! $existing) {
                        $superAdmin = User::create([
                            'name'              => $adminName ?? 'Super Admin',
                            'email'             => $adminEmail,
                            'password'          => Hash::make($adminPass ?? 'ChangeMe123!'),
                            'role'              => 'super_admin',
                            'menu_permissions'  => null,
                            'email_verified_at' => now(),
                        ]);
                        $this->log('✅ Super admin created: ' . $adminEmail);
                    } else {
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
                        $this->log('✅ Existing user promoted to super admin: ' . $adminEmail);
                    }
                } catch (\Throwable $e) {
                    $this->log('⚠️  Admin user error: ' . $e->getMessage());
                }
            }

            // ── 6. Lock installer ──────────────────────────────────────
            $this->log('⏳ Locking installer...');
            try {
                app(\App\Services\EnvFileWriter::class)->set(['APP_INSTALLED' => 'true']);
                @file_put_contents(storage_path('framework/installed'), now()->toDateTimeString());
                $this->log('✅ Installer locked. APP_INSTALLED=true');
            } catch (\Throwable $e) {
                $this->log('⚠️  Lock error: ' . $e->getMessage());
            }

            // ── 7. Clear caches ────────────────────────────────────────
            try {
                Artisan::call('cache:clear');
                Artisan::call('config:clear');
                Artisan::call('route:clear');
                Artisan::call('view:clear');
                $this->log('✅ Caches cleared.');
            } catch (\Throwable $e) {
                $this->log('⚠️  Cache clear error: ' . $e->getMessage());
            }

            // ── Clean up cache keys ────────────────────────────────────
            foreach (['install_admin_email','install_admin_name','install_admin_password','install_sql_imported'] as $key) {
                cache()->forget($key);
            }

            $this->log('');
            $this->log('🎉 Installation complete!');

            $this->writeStatus([
                'status'       => 'done',
                'finished_at'  => now()->toDateTimeString(),
                'admin_email'  => $superAdmin?->email ?? $adminEmail,
                'admin_name'   => $superAdmin?->name  ?? $adminName,
            ]);

            return 0;

        } catch (\Throwable $e) {
            $this->log('❌ FATAL: ' . get_class($e) . ': ' . $e->getMessage());
            $this->log('   in ' . $e->getFile() . ':' . $e->getLine());
            $this->writeStatus(['status' => 'failed', 'error' => $e->getMessage()]);
            return 1;
        }
    }
}
