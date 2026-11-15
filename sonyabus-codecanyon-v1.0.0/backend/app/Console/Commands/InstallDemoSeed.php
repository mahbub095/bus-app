<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Runs demo seeders via `php artisan db:seed` in a background process.
 * This command is launched by InstallController::startDemoSeed() as a
 * detached background process so it never blocks php artisan serve.
 *
 * Progress is written to storage/framework/demo-seed-status.json
 * and polled by the browser via GET /install/demo-seed/status.
 *
 * Usage (called internally by the wizard only):
 *   php artisan install:demo-seed --token=<one-time-token>
 */
class InstallDemoSeed extends Command
{
    protected $signature   = 'install:demo-seed {--token= : One-time security token}';
    protected $description = 'Run demo seeders in background during installation';

    // ── Status file helpers ───────────────────────────────────────────────────

    public static function statusFile(): string
    {
        return storage_path('framework/demo-seed-status.json');
    }

    private array $lines = [];

    private function addLine(string $line): void
    {
        $this->lines[] = $line;
        $this->flush('running');
    }

    private function flush(string $status, ?string $error = null): void
    {
        $data = ['status' => $status, 'log' => $this->lines];
        if ($error !== null) {
            $data['error'] = $error;
        }
        file_put_contents(self::statusFile(), json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    // ── Main ──────────────────────────────────────────────────────────────────

    public function handle(): int
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '256M');

        // Validate one-time token
        $expected = cache('install_demo_seed_token');
        if (! $expected || $this->option('token') !== $expected) {
            $this->error('Invalid or missing token.');
            return 1;
        }
        cache()->forget('install_demo_seed_token');

        $this->flush('running');
        $this->addLine('⏳ Starting demo data import...');

        try {
            $this->addLine('⏳ Running php artisan db:seed...');

            // Safe here — this command runs in its own background PHP process,
            // completely separate from php artisan serve.
            $exitCode = Artisan::call('db:seed', ['--force' => true]);
            $output   = trim(Artisan::output());

            if ($output) {
                foreach (explode("\n", $output) as $line) {
                    $line = trim($line);
                    if ($line !== '') {
                        $this->addLine('   ' . $line);
                    }
                }
            }

            if ($exitCode === 0) {
                $this->addLine('✅ All seeders completed.');
            } else {
                $this->addLine('⚠️  Seeder finished with exit code ' . $exitCode . '. Some data may be missing.');
            }

            $this->addLine('');
            $this->addLine('🎉 Demo data imported successfully!');
            $this->flush('done');

            return 0;

        } catch (\Throwable $e) {
            $this->addLine('❌ Error: ' . $e->getMessage());
            $this->flush('failed', $e->getMessage());
            return 1;
        }
    }
}
