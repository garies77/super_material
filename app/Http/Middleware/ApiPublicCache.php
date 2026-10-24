<?php

namespace App\Http\Middleware;

use App\Support\Api\ApiCache;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Respons API publik: cache server, Cache-Control, ETag, Last-Modified, dan 304 (PRD 8.12.8).
 */
class ApiPublicCache
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        $key = ApiCache::key($request);
        $cached = Cache::get($key);

        if (is_array($cached)) {
            $response = response($cached['content'], 200, ['Content-Type' => 'application/json']);
            if ($cached['last_modified']) {
                $response->headers->set('Last-Modified', $cached['last_modified']);
            }
            $response->headers->set('X-Cache', 'HIT');
        } else {
            $response = $next($request);
            if ($response->getStatusCode() !== 200) {
                return $response;
            }

            Cache::put($key, [
                'content' => $response->getContent(),
                'last_modified' => $response->headers->get('Last-Modified'),
            ], config('api.cache.ttl', 300));
            $response->headers->set('X-Cache', 'MISS');
        }

        $response->setEtag(sha1((string) $response->getContent()));
        $response->setPublic();
        $response->setMaxAge(config('api.cache.max_age', 60));
        $response->headers->set('Vary', 'Accept-Encoding');
        $response->isNotModified($request);

        return $response;
    }
}
