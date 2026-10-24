<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan untuk seluruh respons (PRD Keamanan Pilar 5).
 * CSP berbasis nonce: hanya skrip inline yang membawa nonce request ini yang dijalankan.
 */
class ApplySecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Nonce dibuat sebelum view dirender; @vite, Livewire, dan Filament membacanya lewat Vite::cspNonce()
        $nonce = Vite::useCspNonce();

        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        // HSTS hanya di produksi lewat HTTPS agar domain lokal (.test) tidak ikut terkunci
        if ($request->isSecure() && app()->isProduction()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }

        // Halaman dokumentasi API memuat UI Stoplight dari unpkg.com, di luar CSP situs
        if (config('security.csp.enabled') && ! $headers->has('Content-Security-Policy') && ! $request->is('api/docs*')) {
            $headers->set('Content-Security-Policy', $this->csp($nonce));
        }

        return $response;
    }

    private function csp(string $nonce): string
    {
        $c = config('security.csp');
        $dev = app()->isLocal() ? ' http://localhost:* ws://localhost:* http://127.0.0.1:* ws://127.0.0.1:*' : ''; // server Vite saat npm run dev

        return implode('; ', [
            "default-src 'self'",
            // 'unsafe-eval' tetap: Alpine/Livewire mengevaluasi ekspresi x-* dan blok @script
            "script-src 'self' 'nonce-{$nonce}' 'unsafe-eval' ".implode(' ', $c['script_src']).$dev,
            // Gaya inline tetap diizinkan: atribut style="" dari Filament/Livewire tidak bisa diberi nonce
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com".$dev,
            "font-src 'self' data: https://fonts.gstatic.com",
            "img-src 'self' ".implode(' ', $c['img_src']),
            "media-src 'self' https:",
            // Pratinjau FileUpload Filament mengambil berkas lewat fetch dari URL disk publik (bisa beda origin, mis. CDN)
            "connect-src 'self' ".$this->storageOrigin().' '.implode(' ', $c['connect_src']).$dev,
            "frame-src 'self' ".implode(' ', $c['frame_src']),
            "frame-ancestors 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "script-src-attr 'none'",
        ]);
    }

    private function storageOrigin(): string
    {
        $parts = parse_url((string) config('filesystems.disks.public.url'));

        return isset($parts['scheme'], $parts['host'])
            ? $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '')
            : '';
    }
}
