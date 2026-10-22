<?php

namespace App\Support;

/** Deteksi bot sederhana dari user agent untuk rute /go/ dan /ad/. */
class BotDetector
{
    private const PATTERN = '/bot|crawl|spider|slurp|facebookexternalhit|preview|headless|curl|wget|python|httpclient/i';

    public static function isBot(?string $userAgent): bool
    {
        $ua = (string) $userAgent;

        return $ua === '' || preg_match(self::PATTERN, $ua) === 1;
    }
}
