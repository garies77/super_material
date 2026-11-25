<?php

namespace App\Support;

/**
 * Mengenali tautan video YouTube, Instagram, TikTok, dan Facebook lalu menyusun URL iframe embed-nya.
 * Semua pemutar memakai iframe resmi platform, tanpa skrip pihak ketiga.
 */
class VideoEmbed
{
    /** Pola post/reel Instagram; grup 1 = jenis, grup 2 = shortcode. */
    private const INSTAGRAM_PATTERN = '#^https://(?:www\.)?instagram\.com/(?:[\w.]+/)?(p|reel|reels|tv)/([\w-]+)#i';

    /** Pola video TikTok; grup 1 = ID numerik video. */
    private const TIKTOK_PATTERN = '#^https://(?:www\.|m\.)?tiktok\.com/@[\w.-]+/video/(\d+)#i';

    /** Pola video Facebook: /videos/, /watch?v=, /reel/, dan fb.watch. */
    private const FACEBOOK_PATTERN = '#^https://(?:(?:www\.|m\.|web\.)?facebook\.com/(?:[\w.-]+/videos/(?:[\w.-]+/)?\d+|watch/?\?(?:.*&)?v=\d+|reel/\d+)|fb\.watch/[\w-]+)#i';

    /**
     * @return array{provider: 'youtube'|'instagram'|'tiktok'|'facebook', src: string, portrait: bool}|null
     */
    public static function fromUrl(?string $url): ?array
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if ($youtubeId = YouTube::videoId($url)) {
            return ['provider' => 'youtube', 'src' => YouTube::embedUrl($youtubeId), 'portrait' => str_contains(strtolower($url), '/shorts/')];
        }

        if (preg_match(self::INSTAGRAM_PATTERN, $url, $match)) {
            $kind = strtolower($match[1]) === 'p' ? 'p' : 'reel';

            return ['provider' => 'instagram', 'src' => "https://www.instagram.com/{$kind}/{$match[2]}/embed/", 'portrait' => true];
        }

        if (preg_match(self::TIKTOK_PATTERN, $url, $match)) {
            return ['provider' => 'tiktok', 'src' => "https://www.tiktok.com/player/v1/{$match[1]}?rel=0", 'portrait' => true];
        }

        if (preg_match(self::FACEBOOK_PATTERN, $url, $match)) {
            $isReel = str_contains(strtolower($match[0]), '/reel/');

            return ['provider' => 'facebook', 'src' => 'https://www.facebook.com/plugins/video.php?'.http_build_query(['href' => $match[0], 'show_text' => 'false']), 'portrait' => $isReel];
        }

        return null;
    }

    public static function isSupported(?string $url): bool
    {
        return self::fromUrl($url) !== null;
    }
}
