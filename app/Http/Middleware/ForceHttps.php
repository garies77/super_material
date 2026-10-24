<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Produksi: request http dialihkan permanen ke https (setelah trusted proxy diterapkan, sehingga
 * request yang sudah https di balik CDN tidak ikut dialihkan berulang). Health check /up dikecualikan.
 */
class ForceHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->isProduction() && config('security.force_https') && ! $request->isSecure() && ! $request->is('up')) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
