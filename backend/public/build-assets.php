<?php
/**
 * One-time asset copy script.
 * Visit http://bus-app.test/build-assets.php once to populate public/build/assets/
 * DELETE this file after running it.
 */

$base    = dirname(__DIR__);
$dest    = __DIR__ . '/build/assets/';
$success = [];
$errors  = [];

$files = [
    // source (relative to $base)  =>  dest filename
    'resources/js/app.js'                       => 'app.js',
    'resources/js/admin/layout.js'              => 'layout.js',
    'resources/js/admin/dashboard-overview.js'  => 'dashboard-overview.js',
    'resources/js/admin/bookings.js'            => 'bookings.js',
    'resources/js/admin/buses.js'               => 'buses.js',
    'resources/js/admin/cancel-requests.js'     => 'cancel-requests.js',
    'resources/js/admin/coach-services.js'      => 'coach-services.js',
    'resources/js/admin/gateway-settings.js'    => 'gateway-settings.js',
    'resources/js/admin/reports.js'             => 'reports.js',
    'resources/js/admin/report-detail.js'       => 'report-detail.js',
    'resources/js/admin/routes.js'              => 'routes.js',
    'resources/js/admin/site-settings.js'       => 'site-settings.js',
    'resources/js/admin/users.js'               => 'users.js',
];

if (!is_dir($dest)) {
    mkdir($dest, 0755, true);
}

foreach ($files as $src => $name) {
    $srcPath  = $base . '/' . $src;
    $destPath = $dest . $name;
    if (file_exists($srcPath)) {
        if (copy($srcPath, $destPath)) {
            $success[] = $name;
        } else {
            $errors[] = "Failed to copy $src";
        }
    } else {
        $errors[] = "Source not found: $src";
    }
}

// Write a minimal app.css (Tailwind via CDN — proper build needs npm)
file_put_contents($dest . 'app.css', '/* Built via copy script — run npm run build for full Tailwind CSS */');
$success[] = 'app.css (stub)';

echo "<pre style='font-family:monospace; padding:20px; background:#0f172a; color:#94a3b8;'>";
echo "<strong style='color:#6ee7b7'>✅ Copied " . count($success) . " files to public/build/assets/</strong>\n\n";
foreach ($success as $f) echo "  ✓ $f\n";
if ($errors) {
    echo "\n<strong style='color:#fca5a5'>❌ Errors:</strong>\n";
    foreach ($errors as $e) echo "  ✗ $e\n";
}
echo "\n<strong style='color:#fcd34d'>⚠ DELETE this file now: public/build-assets.php</strong>";
echo "\n<strong style='color:#fcd34d'>  Run 'npm install && npm run build' later for proper Tailwind CSS.</strong>";
echo "</pre>";
