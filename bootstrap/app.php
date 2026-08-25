<?php

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Login uchun rate limiter: IP + login kombinatsiyasi bo'yicha
            // daqiqada 5 urinish — brute-force va AD lockout DoS himoyasi.
            RateLimiter::for('login', function (Request $request) {
                $identity = (string) $request->input('username', $request->input('email', ''));
                $key = strtolower($identity).'|'.$request->ip();

                return Limit::perMinute(5)->by($key)->response(function (Request $request, array $headers) {
                    if ($request->expectsJson()) {
                        return response()->json([
                            'message' => "Juda ko'p urinish. Iltimos, 1 daqiqadan keyin qayta urinib ko'ring.",
                        ], 429, $headers);
                    }

                    return redirect()
                        ->route('login')
                        ->with('error', "Juda ko'p urinish. Iltimos, 1 daqiqadan keyin qayta urinib ko'ring.")
                        ->withHeaders($headers);
                });
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\SetOrganizationContextMiddleware::class);
        $middleware->alias([
            'permission' => \App\Http\Middleware\CheckPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
