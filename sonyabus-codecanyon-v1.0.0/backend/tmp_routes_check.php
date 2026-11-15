<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$routes = app('router')->getRoutes();
$byMenu = [];
$missing = [];

foreach ($routes as $route) {
    $name = $route->getName();
    if (! $name || ! str_starts_with($name, 'admin.')) {
        continue;
    }
    $middleware = $route->gatherMiddleware();
    $perm = null;
    foreach ($middleware as $m) {
        if (is_string($m) && str_starts_with($m, 'menu_permission:')) {
            $perm = substr($m, strlen('menu_permission:'));
        }
    }
    $byMenu[$perm ?? 'NONE'][] = $name;
}

ksort($byMenu);
foreach ($byMenu as $perm => $names) {
    echo "== {$perm} (" . count($names) . ") ==\n";
    sort($names);
    foreach ($names as $n) {
        echo "   {$n}\n";
    }
}

$allowed = ['coach-services','bookings','cancel-requests','stations','buses','routes','schedules','promotions','users','reports','license','site-settings','gateways','profile'];
$used = array_keys($byMenu);
$unknown = array_diff(array_filter($used, fn ($p) => $p !== 'NONE'), $allowed);
echo "\nUnknown permission keys on routes: " . (empty($unknown) ? 'none' : implode(', ', $unknown)) . "\n";
