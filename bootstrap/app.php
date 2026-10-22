<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->preventRequestsDuringMaintenance(except: [
            'robots.txt',
            'up',
        ]);
        $middleware->web(
            prepend: [\App\Http\Middleware\HandleRedirects::class],
            append: [\App\Http\Middleware\AddRobotsTag::class],
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Mode pemeliharaan: 503 selalu membawa Retry-After agar Googlebot menunda, bukan menghapus (PRD 9.11.6, SEO-08)
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response) {
            if ($response->getStatusCode() === 503 && ! $response->headers->has('Retry-After')) {
                $response->headers->set('Retry-After', '3600');
            }

            return $response;
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
