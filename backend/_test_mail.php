<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Mail;

$testEmail = $argv[1] ?? null;
if (!$testEmail) {
    echo "Usage: php _test_mail.php your@email.com\n";
    exit(1);
}

echo "=== Mail Config ===\n";
echo "default:      " . config('mail.default') . "\n";
echo "host:         " . config('mail.mailers.smtp.host') . "\n";
echo "port:         " . config('mail.mailers.smtp.port') . "\n";
echo "username:     " . config('mail.mailers.smtp.username') . "\n";
echo "scheme:       " . (config('mail.mailers.smtp.scheme') ?? 'NULL (auto)') . "\n";
echo "from.address: " . config('mail.from.address') . "\n";
echo "from.name:    " . config('mail.from.name') . "\n\n";

echo "Sending test email to: $testEmail\n";

try {
    Mail::raw('This is a test email from JourneyGo admin panel.', function ($msg) use ($testEmail) {
        $msg->to($testEmail)->subject('JourneyGo Mail Test');
    });
    echo "✅ Email sent successfully!\n";
} catch (\Exception $e) {
    echo "❌ Failed: " . $e->getMessage() . "\n";
}
