<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Runtime mail config ===\n";
echo "default:      " . config('mail.default') . "\n";
echo "host:         " . config('mail.mailers.smtp.host') . "\n";
echo "port:         " . config('mail.mailers.smtp.port') . "\n";
echo "username:     " . config('mail.mailers.smtp.username') . "\n";
echo "scheme:       " . (config('mail.mailers.smtp.scheme') ?? 'NULL') . "\n";
echo "from.address: " . config('mail.from.address') . "\n";
echo "from.name:    " . config('mail.from.name') . "\n";

echo "\n=== bootstrap/cache/config.php exists? ===\n";
echo file_exists(base_path('bootstrap/cache/config.php')) ? "YES (cached)\n" : "NO (reading .env live)\n";

echo "\n=== Sending test mail ===\n";
$testEmail = $argv[1] ?? config('mail.from.address');
echo "To: $testEmail\n";

try {
    \Illuminate\Support\Facades\Mail::raw('Test from JourneyGo. SMTP is working.', function($m) use ($testEmail) {
        $m->to($testEmail)->subject('JourneyGo SMTP Test');
    });
    echo "✅ Mail sent successfully!\n";
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
