<?php

use App\Http\Middleware\AddRobotsTag;
use App\Http\Middleware\ApiIdempotency;
use App\Http\Middleware\ApiPublicCache;
use App\Http\Middleware\ApplySecurityHeaders;
use App\Http\Middleware\EnsureApiAbility;
use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\HandleRedirects;
use App\Http\Middleware\LogApiRequest;
use App\Support\Api\ApiError;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->preventRequestsDuringMaintenance(except: [
            'robots.txt',
            'up',
        ]);
        $middleware->web(
            prepend: [HandleRedirects::class],
            append: [AddRobotsTag::class],
        );
        $middleware->api(prepend: [LogApiRequest::class]);
        $middleware->append(ApplySecurityHeaders::class);
        // Setelah TrustProxies (bawaan, paling awal) agar X-Forwarded-Proto dari proxy tepercaya sudah terbaca
        $middleware->append(ForceHttps::class);
        $middleware->alias([
            'api.ability' => EnsureApiAbility::class,
            'api.cache' => ApiPublicCache::class,
            'api.idempotent' => ApiIdempotency::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Mode pemeliharaan: 503 selalu membawa Retry-After agar Googlebot menunda, bukan menghapus (PRD 9.11.6, SEO-08)
        $exceptions->respond(function (Response $response) {
            if ($response->getStatusCode() === 503 && ! $response->headers->has('Retry-After')) {
                $response->headers->set('Retry-After', '3600');
            }

            return $response;
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Bentuk galat API seragam {"error": {...}} (PRD 8.12.4)
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/v1/*') && ! $request->is('api/v1')) {
                return null;
            }

            return match (true) {
                $e instanceof ValidationException => ApiError::response(422, 'Data tidak valid.', 'validation_failed', $e->errors()),
                $e instanceof AuthenticationException => ApiError::response(401),
                $e instanceof AuthorizationException => ApiError::response(403),
                $e instanceof ModelNotFoundException => ApiError::response(404),
                $e instanceof HttpExceptionInterface => ApiError::response(
                    $e->getStatusCode(),
                    in_array($e->getStatusCode(), [404, 405, 429, 503], true) ? null : ($e->getMessage() ?: null),
                    null,
                    [],
                    $e->getHeaders(),
                ),
                default => config('app.debug') ? null : ApiError::response(500),
            };
        });
    })->create();
