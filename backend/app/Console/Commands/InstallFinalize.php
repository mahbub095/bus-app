<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\EnvFileWriter;
use App\Services\LicenseService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Runs all installation finalization steps in a background process:
 *   1. migrate:install (create migrations table)
 *   2. migrate (run all migrations)
 *   3. Persist license record
 *   4. Create super admin user
 *   5. Lock installer (APP_INSTALLED=true)
 *
 * Writes progress to storage/framework/install-finalize-status.json
 * so the browser can poll it.
 *
 * Called by InstallController::runFinalize() only.
 */
class InstallFinalize extends Command
{
    protected $signature   = 'install:finalize {--token= : One-time security token}';
    protected $description = 'Run installation finalization in the background';

    public static function statusFile(): string
    {
        return storage_path('framework/install-finalize-status.json');
    }

    private array $log = [];

    private function write(string $status, ?string $error = null): void
    {
        $data = ['status' => $status, 'log' => $this->log];
        if ($error) $data['error'] = $error;
        file_put_contents(self::statusFile(), json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    private function log(string $line): void
    {
        $this->log[] = $line;
        $this->write('running');
    }

    public function handle(): int
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '256M');

        // Validate one-time token
        $expected = cache('install_finalize_token');
        if (! $expected || $this->option('token') !== $expected) {
            $this->error('Invalid token.');
            return 1;
        }
        cache()->forget('install_finalize_token');

        $this->write('running');

        try {
            // ── 1. Ensure DB connection ────────────────────────────────
            $this->log('🔌 Connecting to database...');
            try {
                DB::connection()->getPdo()->query('SELECT 1');
                $this->log('✅ Database connected.');
            } catch (\Throwable $e) {
                throw new \RuntimeException('Database connection failed: ' . $e->getMessage());
            }

            // ── 2. Create migrations table if needed ───────────────────
            $this->log('⏳ Checking migrations table...');
            try {
                DB::connection()->getPdo()->query('SELECT 1 FROM migrations LIMIT 1');
                $this->log('✅ Migrations table exists.');
            } catch (\Throwable $_) {
                // Table doesn't exist — create it
                DB::statement('CREATE TABLE IF NOT EXISTS `migrations` (
                    `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `migration` varchar(255) NOT NULL,
                    `batch` int(11) NOT NULL,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
                $this->log('✅ Migrations table created.');
            }

            // ── 3. Run migrations ──────────────────────────────────────
            $sqlImported = (bool) cache('install_sql_imported', false);

            if ($sqlImported) {
                $this->log('⏭️  Skipping migrations (SQL file was imported).');
            } else {
                // Check if users table already exists (previous partial install)
                $usersExist = false;
                try {
                    DB::connection()->getPdo()->query('SELECT 1 FROM users LIMIT 1');
                    $usersExist = true;
                } catch (\Throwable $_) {}

                if ($usersExist) {
                    $this->log('⏭️  Tables already exist — skipping migrations.');
                } else {
                    $this->log('⏳ Running migrations...');
                    $migrationFiles = glob(database_path('migrations') . '/*.php');
                    sort($migrationFiles);

                    // Get already-run migrations
                    $ranMigrations = DB::table('migrations')->pluck('migration')->toArray();

                    $batch = (int)(DB::table('migrations')->max('batch') ?? 0) + 1;
                    $ran   = 0;

                    foreach ($migrationFiles as $file) {
                        $migrationName = pathinfo($file, PATHINFO_FILENAME);
                        if (in_array($migrationName, $ranMigrations, true)) continue;

                        try {
                            // Laravel migrations use "return new class extends Migration"
                            // so we get the instance by requiring the file directly.
                            $migrationInstance = require $file;

                            if (is_object($migrationInstance) && method_exists($migrationInstance, 'up')) {
                                $migrationInstance->up();
                            } else {
                                // Fallback: named class — find the newly loaded class
                                $classesBefore = get_declared_classes();
                                $migrationInstance = null;
                                foreach (array_reverse($classesBefore) as $cls) {
                                    if (is_subclass_of($cls, \Illuminate\Database\Migrations\Migration::class)) {
                                        $migrationInstance = new $cls();
                                        break;
                                    }
                                }
                                if ($migrationInstance) {
                                    $migrationInstance->up();
                                }
                            }

                            DB::table('migrations')->insert([
                                'migration' => $migrationName,
                                'batch'     => $batch,
                            ]);
                            $ran++;
                            $this->log("  ✅ {$migrationName}");
                        } catch (\Throwable $e) {
                            // Skip "already exists" errors gracefully
                            if (stripos($e->getMessage(), 'already exist') !== false
                                || stripos($e->getMessage(), 'Duplicate') !== false) {
                                DB::table('migrations')->insert([
                                    'migration' => $migrationName,
                                    'batch'     => $batch,
                                ]);
                                $ran++;
                                $this->log("  ✅ {$migrationName} (already existed)");
                            } else {
                                $this->log("  ⚠️  {$migrationName}: " . $e->getMessage());
                            }
                        }
                    }
                    $this->log("✅ Migrations complete ({$ran} ran).");
                }
            }

            // Reconnect so Eloquent sees the freshly-created tables
            try {
                DB::purge();
                DB::reconnect();
            } catch (\Throwable $_) {}

            // ── 4. Persist license ─────────────────────────────────────
            $pending = cache('install_pending_license');
            if (is_array($pending) && ! empty($pending['purchase_code'])) {
                $this->log('⏳ Saving license record...');
                try {
                    app(LicenseService::class)->persistVerifiedLicense(
                        purchaseCode: $pending['purchase_code'],
                        apiData:      $pending['api_data'] ?? [],
                    );
                    cache()->forget('install_pending_license');
                    $this->log('✅ License saved.');
                } catch (\Throwable $e) {
                    $this->log('⚠️  License save skipped: ' . $e->getMessage());
                }
            }

            // ── 5. Create / update super admin ─────────────────────────
            $adminEmail = cache('install_admin_email');
            $adminName  = cache('install_admin_name');
            $adminPass  = cache('install_admin_password');

            if (! empty($adminEmail)) {
                $this->log("⏳ Creating admin: {$adminEmail}...");
                try {
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
                    $this->log('✅ Admin account ready.');
                } catch (\Throwable $e) {
                    throw new \RuntimeException('Admin creation failed: ' . $e->getMessage());
                }
            }

            // ── 6. Lock installer ──────────────────────────────────────
            $this->log('⏳ Locking installer...');
            app(EnvFileWriter::class)->set(['APP_INSTALLED' => 'true']);
            @file_put_contents(storage_path('framework/installed'), now()->toDateTimeString());
            $this->log('✅ Installer locked.');

            // ── 7. Clear caches ────────────────────────────────────────
            try {
                // Clear file-based cache manually (no artisan calls)
                $cacheDir = storage_path('framework/cache/data');
                if (is_dir($cacheDir)) {
                    foreach (new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator($cacheDir, \FilesystemIterator::SKIP_DOTS),
                        \RecursiveIteratorIterator::CHILD_FIRST
                    ) as $f) {
                        if ($f->isFile()) @unlink($f->getPathname());
                    }
                }
                // Clear compiled views
                foreach (glob(storage_path('framework/views') . '/*.php') ?: [] as $f) {
                    @unlink($f);
                }
            } catch (\Throwable $_) {}

            // Clean up cache keys
            foreach (['install_admin_email','install_admin_name','install_admin_password','install_sql_imported'] as $key) {
                cache()->forget($key);
            }

            $this->log('');
            $this->log('🎉 Installation complete!');

            $data = ['status' => 'done', 'log' => $this->log, 'redirect' => '/install/complete'];
            file_put_contents(self::statusFile(), json_encode($data, JSON_UNESCAPED_UNICODE));

            return 0;

        } catch (\Throwable $e) {
            $this->log('❌ Error: ' . $e->getMessage());
            $this->write('failed', $e->getMessage());
            return 1;
        }
    }
}
