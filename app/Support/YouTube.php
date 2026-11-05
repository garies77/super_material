<?php

namespace App\Support;

/**
 * Satu tempat untuk mengenali tautan YouTube dan menyusun URL embed/thumbnail.
 */
class YouTube
{
    /** Pola tautan YouTube; grup 1 = ID video 11 karakter. */
    public const ID_PATTERN = '%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?|shorts|live)/|.*[?&]v=)|youtu\.be/)([\w-]{11})%i';

    /** Validasi form: URL https YouTube (watch, shorts, embed, youtu.be). */
    public const URL_RULE = '#^https://(www\.|m\.)?(youtube\.com/(watch\?(.*&)?v=|shorts/|embed/|live/)|youtu\.be/)[\w-]{11}#i';

    public static function videoId(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        return preg_match(self::ID_PATTERN, $url, $match) ? $match[1] : null;
    }

    public static function isYouTubeUrl(?string $url): bool
    {
        return self::videoId($url) !== null;
    }

    public static function embedUrl(string $videoId, ?int $start = null): string
    {
        $url = "https://www.youtube-nocookie.com/embed/{$videoId}";

        return $start ? $url.'?start='.$start : $url;
    }

    public static function watchUrl(string $videoId): string
    {
        return "https://www.youtube.com/watch?v={$videoId}";
    }

    public static function thumbnailUrl(string $videoId): string
    {
        return "https://i.ytimg.com/vi/{$videoId}/hqdefault.jpg";
    }
}
