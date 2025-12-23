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
    ->withMiddleware(function (Middleware $middleware) {
        // Global middleware
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        
        // Web middleware group (includes CSRF protection)
        $middleware->web(append: [
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
        ]);
        
        // API middleware group
        $middleware->api(prepend: [
            \App\Http\Middleware\LogApiRequests::class,
            \App\Http\Middleware\ThrottleWithLogging::class,
        ]);
        
        // Middleware aliases
        $middleware->alias([
            'admin' => \App\Http\Middleware\AdminAuthenticate::class,
            'admin.permission' => \App\Http\Middleware\CheckAdminPermission::class,
            'throttle.logged' => \App\Http\Middleware\ThrottleWithLogging::class,
            'fraud.detect' => \App\Http\Middleware\DetectFraud::class,
            'verify.signature' => \App\Http\Middleware\VerifyRequestSignature::class,
            'captcha' => \App\Http\Middleware\VerifyCaptcha::class,
            'financial.security' => \App\Http\Middleware\FinancialTransactionSecurity::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Handle unauthenticated API requests with JSON response
        $exceptions->respond(function (\Illuminate\Http\Response $response, \Throwable $exception, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') && $exception instanceof \Illuminate\Auth\AuthenticationException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.'
                ], 401);
            }
            
            return $response;
        });
    })->create();
