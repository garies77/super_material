<?php

namespace App\Support\Seo;

/**
 * Konversi durasi video antara isian admin ("4:32", "1:02:10", "272"), detik, dan ISO 8601 (PT4M32S).
 */
class VideoDuration
{
    public static function parse(mixed $input): ?int
    {
        if (is_int($input)) {
            return $input >= 0 ? $input : null;
        }

        $input = trim((string) $input);
        if ($input === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $input)) {
            return (int) $input;
        }

        if (! preg_match('/^(?:(\d+):)?(\d{1,2}):(\d{2})$/', $input, $m)) {
            return null;
        }

        [$hours, $minutes, $seconds] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        if ($seconds > 59 || ($m[1] !== '' && $minutes > 59)) {
            return null;
        }

        return $hours * 3600 + $minutes * 60 + $seconds;
    }

    /** 272 → "4:32", 3730 → "1:02:10". */
    public static function format(?int $seconds): ?string
    {
        if ($seconds === null) {
            return null;
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $rest = $seconds % 60;

        return $hours > 0
            ? sprintf('%d:%02d:%02d', $hours, $minutes, $rest)
            : sprintf('%d:%02d', $minutes, $rest);
    }

    /** 272 → "PT4M32S"; 0 → "PT0S". */
    public static function iso(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $rest = $seconds % 60;

        $iso = 'PT'.($hours ? $hours.'H' : '').($minutes ? $minutes.'M' : '').($rest ? $rest.'S' : '');

        return $iso === 'PT' ? 'PT0S' : $iso;
    }

    /** "PT1H2M10S" (format YouTube API) → 3730. */
    public static function fromIso(?string $iso): ?int
    {
        if (! $iso || ! preg_match('/^P(?:(\d+)D)?T?(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', $iso, $m)) {
            return null;
        }

        return ((int) ($m[1] ?? 0)) * 86400 + ((int) ($m[2] ?? 0)) * 3600 + ((int) ($m[3] ?? 0)) * 60 + (int) ($m[4] ?? 0);
    }
}
