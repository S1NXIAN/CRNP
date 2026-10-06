<?php

use Illuminate\Foundation\Application;
use App\Http\Middleware\EnsureKitchenSecret;
use App\Http\Middleware\EnsureStaffRole;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The role guard behind `/cashier` and `/admin`: it reads the signed-in
        // account's role from `staff/{uid}` per request (short cache, evicted on
        // any staff write) and refuses a Google account absent from the list.
        $middleware->alias([
            'staff.role' => EnsureStaffRole::class,
            'kitchen.secret' => EnsureKitchenSecret::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
