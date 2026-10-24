<?php

/*
| CORS API (PRD 8.12.8): hanya asal yang terdaftar di API_CORS_ORIGINS (dipisah koma).
| Tanpa konfigurasi, tidak ada asal lintas domain yang diizinkan; API admin tidak terbuka untuk asal bebas.
*/

return [

    'paths' => ['api/v1/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('API_CORS_ORIGINS', ''))))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'Idempotency-Key', 'If-None-Match', 'If-Modified-Since'],

    'exposed_headers' => ['ETag', 'Last-Modified', 'X-RateLimit-Limit', 'X-RateLimit-Remaining', 'Retry-After', 'Deprecation', 'Sunset'],

    'max_age' => 3600,

    'supports_credentials' => false,

];
