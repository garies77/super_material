<?php

namespace App\Support;

use Illuminate\Support\Str;

/** Menentukan apakah tautan menuju domain di luar situs (mitra, marketplace, WhatsApp). */
class ExternalUrl
{
    public static function is(?string $url): bool
    {
        $url = (string) $url;
        if ($url === '' || Str::startsWith($url, '/') && ! Str::startsWith($url, '//')) {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $own = strtolower((string) parse_url(url('/'), PHP_URL_HOST));

        return $host !== '' && $host !== $own && ! Str::endsWith($host, '.'.$own);
    }
}
