<?php

use App\Http\Controllers\HealthController;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::get('/health', HealthController::class);
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn (Request $request) => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->report(function (PDOException $e) {
            $userId = 'system';
            try {
                if (auth()->hasUser()) {
                    $userId = (string) auth()->id();
                }
            } catch (Throwable) {
                $userId = 'system';
            }

            Log::error('Database connection error occurred', [
                'user' => $userId,
                'action' => 'database_connection',
                'reason' => $e->getMessage(),
            ]);
        });

        $exceptions->report(function (QueryException $e) {
            $userId = 'system';
            try {
                if (auth()->hasUser()) {
                    $userId = (string) auth()->id();
                }
            } catch (Throwable) {
                $userId = 'system';
            }

            Log::error('Database query error occurred', [
                'user' => $userId,
                'action' => 'database_query',
                'reason' => $e->getMessage(),
            ]);
        });
    })->create();
