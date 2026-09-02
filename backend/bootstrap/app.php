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

            // SMS rate limiter: phone + IP bo'yicha cheklov — SMS bombing va xarajat DoS himoyasi
            RateLimiter::for('sms', function (Request $request) {
                $phone = (string) $request->input('phone', '');
                $key = 'sms|'.preg_replace('/\D/', '', $phone).'|'.$request->ip();

                return Limit::perMinute(3)->by($key)->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => "SMS so'rovlari chegarasi oshdi. Iltimos, 1 daqiqadan so'ng qayta urinib ko'ring.",
                    ], 429, $headers);
                });
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\SetOrganizationContextMiddleware::class);
        $middleware->append(\App\Http\Middleware\SecurityHeadersMiddleware::class);
        $middleware->alias([
            'permission' => \App\Http\Middleware\CheckPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Tizimga kirilmagan (Unauthenticated)',
                    'status' => 401,
                ], 401);
            }
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'So\'ralgan ma\'lumot yoki endpoint topilmadi',
                    'status' => 404,
                ], 404);
            }
        });

        // DIQQAT: Laravel'ning abort() helperi FAQAT 404 ni maxsus sinfga o'raydi
        // (Application::abort). abort(403, '...') oddiy HttpException tashlaydi,
        // AccessDeniedHttpException EMAS — shuning uchun bu yerda bazaviy sinf
        // ushlanadi va status kodi bo'yicha ajratiladi. Aks holda controllerlardagi
        // abort(403, "...") xabari hech qachon bu handlerga tushmaydi.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e, Request $request) {
            if ($e->getStatusCode() !== 403 || ! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage() ?: 'Sizda ushbu amalni bajarish uchun ruxsat yetarli emas',
                'status' => 403,
            ], 403);
        });
    })->create();
