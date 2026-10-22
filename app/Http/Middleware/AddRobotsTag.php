<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header X-Robots-Tag (PRD 9.11.4) untuk respons yang tidak boleh masuk indeks:
 * hasil pencarian internal dan berkas non-HTML seperti peta situs XML.
 * Halaman HTML lain memakai <meta name="robots"> dari layout.
 */
class AddRobotsTag
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->headers->has('X-Robots-Tag')) {
            return $response;
        }

        $contentType = (string) $response->headers->get('Content-Type');

        if ($request->routeIs('search') || str_contains($contentType, 'xml')) {
            $response->headers->set('X-Robots-Tag', 'noindex');
        }

        return $response;
    }
}
