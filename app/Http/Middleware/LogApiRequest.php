<?php

namespace App\Http\Middleware;

use App\Support\Api\ClientHash;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/** Pemantauan per endpoint: jumlah permintaan, status 4xx/5xx, latensi (PRD 8.12.8). */
class LogApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('api_started_at', microtime(true));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $started = (float) $request->attributes->get('api_started_at', microtime(true));

        Log::channel('api')->info('api.request', [
            'method' => $request->method(),
            'route' => $request->route()?->uri() ?? $request->path(),
            'status' => $response->getStatusCode(),
            'duration_ms' => round((microtime(true) - $started) * 1000, 1),
            'cache' => $response->headers->get('X-Cache'),
            'token_id' => $request->user()?->currentAccessToken()?->id,
            'client' => substr((string) ClientHash::forRequest($request), 0, 16),
        ]);
    }
}
