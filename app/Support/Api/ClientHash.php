<?php

namespace App\Support\Api;

use Illuminate\Http\Request;

/**
 * Hash alamat klien yang berganti setiap hari: cukup untuk batas laju dan log,
 * tetapi IP mentah tidak pernah disimpan (PRD 8.12.3 & 8.12.8).
 */
class ClientHash
{
    public static function forRequest(Request $request): ?string
    {
        $ip = $request->ip();
        if (! $ip) {
            return null;
        }

        return hash_hmac('sha256', $ip.'|'.now()->format('Y-m-d'), (string) config('app.key'));
    }
}
