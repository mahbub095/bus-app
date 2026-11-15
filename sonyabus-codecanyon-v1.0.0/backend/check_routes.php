<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Route;

$known = [];
foreach (Route::getRoutes() as $route) {
    if ($route->getName()) {
        $known[] = $route->getName();
    }
}
$known = array_flip($known);

$paths = [
    __DIR__ . '/resources/views',
    __DIR__ . '/resources/js',
    __DIR__ . '/app',
    __DIR__ . '/routes',
];

$missing = [];
$found = 0;

foreach ($paths as $root) {
    if (!is_dir($root)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        if (!$file->isFile()) {
            continue;
        }
        $ext = $file->getExtension();
        if (!in_array($ext, ['php', 'js', 'blade.php'], true)) {
            continue;
        }
        $content = file_get_contents($file->getPathname());
        if (preg_match_all("/route\(\s*['\"]([A-Za-z0-9._\-]+)['\"]/", $content, $m)) {
            foreach (array_unique($m[1]) as $name) {
                $found++;
                if (!isset($known[$name])) {
                    $missing[$name][] = str_replace(__DIR__ . DIRECTORY_SEPARATOR, '', $file->getPathname());
                }
            }
        }
    }
}

echo "Named routes registered: " . count($known) . "\n";
echo "route() references scanned: {$found}\n\n";

if (!$missing) {
    echo "OK - every route() reference resolves to a registered route.\n";
    exit(0);
}

foreach ($missing as $name => $files) {
    echo "MISSING: {$name}\n";
    foreach (array_unique($files) as $f) {
        echo "   - {$f}\n";
    }
}
