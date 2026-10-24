<?php

/*
| PRD Keamanan. Nilai dapat diatur lewat environment.
*/

return [

    // Proxy/CDN/load balancer yang meneruskan header X-Forwarded-* (mis. "*" di balik Cloudflare/LB, atau daftar IP dipisah koma).
    // Tanpa ini, di balik proxy Laravel mengira request memakai HTTP: URL canonical/sitemap http://, HSTS & cookie Secure tidak aktif.
    'trusted_proxies' => env('TRUSTED_PROXIES'),

    // Di produksi: semua URL dibuat https:// dan request http dialihkan 301 ke https.
    'force_https' => (bool) env('FORCE_HTTPS', true),

    // Login panel: setelah sekian kali gagal (per email atau per alamat), login wajib lewat tautan email
    'login' => [
        'max_failures_per_email' => (int) env('SECURITY_LOGIN_MAX_FAILURES_EMAIL', 5),
        'max_failures_per_client' => (int) env('SECURITY_LOGIN_MAX_FAILURES_CLIENT', 10),
        'failure_window_minutes' => 60,
        'link_ttl_minutes' => 15,
    ],

    // Content-Security-Policy; domain analitik resmi dan embed video yang diizinkan
    'csp' => [
        'enabled' => (bool) env('SECURITY_CSP_ENABLED', true),
        'script_src' => ['https://www.googletagmanager.com', 'https://www.google-analytics.com', 'https://connect.facebook.net'],
        'connect_src' => ['https://www.google-analytics.com', 'https://*.google-analytics.com', 'https://region1.google-analytics.com', 'https://www.facebook.com'],
        'frame_src' => ['https://www.youtube.com', 'https://www.youtube-nocookie.com', 'https://www.instagram.com'],
        'img_src' => ['https:', 'data:', 'blob:'],
    ],

];
