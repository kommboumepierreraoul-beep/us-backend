<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        channels: __DIR__.'/../routes/channels.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        $exceptions->render(function (ThrottleRequestsException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $retryAfter = (int) ($exception->getHeaders()['Retry-After'] ?? 0);

            return response()->json([
                'success' => false,
                'message' => 'Trop de tentatives. Veuillez patienter avant de reessayer.',
                'errors' => [
                    'rate_limit' => [
                        $retryAfter > 0
                            ? "Reessayez dans {$retryAfter} secondes."
                            : 'Reessayez dans quelques instants.',
                    ],
                ],
                'meta' => [
                    'request_id' => $request->headers->get('X-Request-Id'),
                    'retry_after' => $retryAfter,
                ],
            ], 429, $exception->getHeaders());
        });
    })->create();
