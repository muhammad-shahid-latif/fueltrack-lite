<?php

use App\Http\Middleware\EnforceStaffAccess;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\NormalizeNumberInput;
use App\Http\Middleware\RecognizeTurboPrefetch;
use App\Http\Middleware\ResolveBrand;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(prepend: [RecognizeTurboPrefetch::class], append: [ResolveBrand::class, NormalizeNumberInput::class]);

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
            'staff.access' => EnforceStaffAccess::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
