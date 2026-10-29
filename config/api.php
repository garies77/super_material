<?php

/*
| REST API v1 (PRD 8.12). Nilai batas laju, paginasi, cache, dan masa token dapat diatur lewat environment.
*/

return [

    'rate_limits' => [
        // Permintaan per menit: publik per alamat (hash), admin per token (PRD 8.12.8)
        'public' => (int) env('API_RATE_LIMIT_PUBLIC', 60),
        'admin' => (int) env('API_RATE_LIMIT_ADMIN', 120),
    ],

    'per_page' => [
        'default' => 20,
        'public_max' => 50,
        'admin_max' => 100,
    ],

    'cache' => [
        // Cache respons API publik di server; dibersihkan observer model saat konten berubah
        'ttl' => (int) env('API_CACHE_TTL', 300),
        // Cache-Control max-age untuk klien dan CDN
        'max_age' => (int) env('API_CACHE_MAX_AGE', 60),
    ],

    'tokens' => [
        'default_expiration_days' => (int) env('API_TOKEN_EXPIRATION_DAYS', 90),
    ],

    'idempotency' => [
        'ttl_hours' => 24,
    ],

    'uploads' => [
        'image_max_kb' => (int) env('API_UPLOAD_IMAGE_MAX_KB', 5120),
        'video_max_kb' => (int) env('API_UPLOAD_VIDEO_MAX_KB', 51200),
        'import_max_kb' => (int) env('API_UPLOAD_IMPORT_MAX_KB', 10240),
    ],

    /*
    | Ability token (PRD 8.12.3). Hak efektif = irisan ability token dan peran pengguna.
    | Tidak ada ability untuk branding, pengguna, peran, redirect, atau iklan.
    */
    'abilities' => [
        'catalog:read' => 'Membaca produk, varian, kategori, merek, tautan kanal, termasuk draf',
        'catalog:write' => 'Membuat dan mengubah produk, varian, media, tautan kanal',
        'import:write' => 'Menjalankan impor CSV atau Excel',
        'reports:read' => 'Membaca laporan (klik, ringkasan, produk, trafik)',
        'articles:read' => 'Membaca artikel termasuk draf',
    ],

    // Ability tulis meminta konfirmasi password saat token dibuat
    'write_abilities' => ['catalog:write', 'import:write'],

    // Peran pengguna → ability yang boleh dipakai, mengikuti hak akses panel Filament
    'role_abilities' => [
        'admin' => ['catalog:read', 'catalog:write', 'import:write', 'reports:read', 'articles:read'],
        'editor' => ['catalog:read', 'catalog:write', 'import:write', 'articles:read'],
        'author' => ['articles:read'],
    ],

];
