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
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => \App\Http\Middleware\EnsureUserIsActive::class,
            'role' => \App\Http\Middleware\EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // QR ya kadi iliyobadilishwa au ya mtu asiyekuwepo: ukurasa wa "haijathibitishwa".
        $invalidCard = function (\Throwable $e, \Illuminate\Http\Request $request) {
            if ($request->is('verify/card/*')) {
                return response()->view('cards.verify', [], 403)->header('X-Robots-Tag', 'noindex, nofollow');
            }
        };

        $exceptions->render(fn (\Illuminate\Routing\Exceptions\InvalidSignatureException $e, $request) => $invalidCard($e, $request));
        $exceptions->render(fn (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) => $invalidCard($e, $request));
    })->create();
