<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        \App\Console\Commands\InstallRun::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        // License guard runs on every web & API request except /install/*
        $middleware->append(\App\Http\Middleware\EnsureLicenseIsActivated::class);

        $middleware->alias([
            'admin'           => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'super_admin'     => \App\Http\Middleware\EnsureUserIsSuperAdmin::class,
            'menu_permission' => \App\Http\Middleware\EnsureUserHasMenuPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
