<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Resets the application to a fresh uninstalled state.
 *
 * Usage:
 *   php artisan install:reset
 *   php artisan install:reset --force   (skip confirmation prompt)
 */
class InstallReset extends Command
{
    protected $signature   = 'install:reset {--force : Skip confirmation prompt}';
    protected $description = 'Reset the application to a fresh uninstalled state (drops & recreates DB, clears all caches, resets .env flags)';

    public function handle(): int
    {
        if (! $this->option('force')) {
            if (! $this->confirm('⚠️  This will DROP the database and reset all install flags. Continue?')) {
                $this->info('Aborted.');
                return 0;
            }
        }

        $this->info('🔄 Starting fresh install reset...');

        // ── 1. Drop & recreate database ──────────────────────────────────────
        $this->task('Recreating database', function () {
            $db   = config('database.connections.mysql.database');
            $host = config('database.connections.mysql.host');
            $port = config('database.connections.mysql.port');
            $user = config('database.connections.mysql.username');
            $pass = config('database.connections.mysql.password');

            $pdo = new \PDO(
                "mysql:host={$host};port={$port};charset=utf8mb4",
                $user, $pass,
                [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
            );
            $pdo->exec("DROP DATABASE IF EXISTS `{$db}`");
            $pdo->exec("CREATE DATABASE `{$db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        });

        // ── 2. Reset .env flags ───────────────────────────────────────────────
        $this->task('Resetting .env flags', function () {
            $envPath = base_path('.env');
            $content = file_get_contents($envPath);
            $content = preg_replace('/^APP_INSTALLED=.*/m', 'APP_INSTALLED=false', $content);
            $content = preg_replace('/^APP_LICENSED=.*/m',  'APP_LICENSED=false',  $content);
            file_put_contents($envPath, $content);
        });

        // ── 3. Remove install lock & status files ─────────────────────────────
        $this->task('Removing lock & status files', function () {
            $files = [
                storage_path('framework/installed'),
                storage_path('framework/install-finalize-status.json'),
                storage_path('framework/demo-seed-status.json'),
                storage_path('framework/install-status.json'),
            ];
            foreach ($files as $f) {
                if (file_exists($f)) unlink($f);
            }
        });

        // ── 4. Clear all caches ───────────────────────────────────────────────
        $this->task('Clearing config & application cache', function () {
            // Delete bootstrap/cache/config.php manually (safest approach)
            // This ensures env() reads fresh from .env on next request
            foreach (glob(base_path('bootstrap/cache/*.php')) ?: [] as $f) {
                if (basename($f) !== 'packages.php' && basename($f) !== 'services.php') {
                    @unlink($f);
                }
            }

            // Clear file-based app cache
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

            // Clear sessions
            foreach (glob(storage_path('framework/sessions') . '/*') ?: [] as $f) {
                if (is_file($f)) @unlink($f);
            }
        });

        // ── 5. Ensure storage folders exist ──────────────────────────────────
        $this->task('Ensuring storage folders exist', function () {
            $dirs = [
                storage_path('framework/cache/data'),
                storage_path('framework/sessions'),
                storage_path('framework/views'),
                storage_path('logs'),
            ];
            foreach ($dirs as $dir) {
                if (!is_dir($dir)) mkdir($dir, 0775, true);
            }
        });

        $this->newLine();
        $this->info('🎉 Reset complete!');
        $this->newLine();
        $this->line('  👉 Now visit: <href=' . config('app.url') . '/install>' . config('app.url') . '/install</>');
        $this->newLine();

        return 0;
    }

    /**
     * Run a labeled task, show ✅ or ❌.
     */
    private function task(string $label, callable $fn): void
    {
        $this->output->write("  · {$label}... ");
        try {
            $fn();
            $this->output->writeln('<fg=green>✅</>');
        } catch (\Throwable $e) {
            $this->output->writeln('<fg=red>❌ ' . $e->getMessage() . '</>');
            throw $e;
        }
    }
}
