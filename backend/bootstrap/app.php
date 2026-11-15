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
        \App\Console\Commands\InstallFinalize::class,
        \App\Console\Commands\InstallDemoSeed::class,
        \App\Console\Commands\InstallReset::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        // License guard runs on every web & API request except /install/*
        $middleware->append(\App\Http\Middleware\EnsureLicenseIsActivated::class);

        // Session / token lifetimes configured in admin → must land in the
        // framework config before the session store and Sanctum guard boot.
        $middleware->append(\App\Http\Middleware\ApplySessionLifetime::class);

        // Exact idle timeout for the admin panel (runs after StartSession).
        $middleware->appendToGroup('web', \App\Http\Middleware\EnforceAdminSessionTimeout::class);

        $middleware->alias([
            'admin'           => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'super_admin'     => \App\Http\Middleware\EnsureUserIsSuperAdmin::class,
            'menu_permission' => \App\Http\Middleware\EnsureUserHasMenuPermission::class,
            // Token-based counterparts used by routes/api.php (admin API).
            'api_admin'       => \App\Http\Middleware\EnsureApiUserIsAdmin::class,
            'api_menu'        => \App\Http\Middleware\EnsureApiUserHasMenuPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
