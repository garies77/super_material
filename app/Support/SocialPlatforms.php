<?php

namespace App\Support;

/**
 * Platform media sosial yang didukung tombol artikel. Tambah platform baru cukup di sini (plus ikonnya
 * di partials/social-icon): pola URL berbagi (null bila platform tidak punya) dan domain profil yang sah.
 */
class SocialPlatforms
{
    /** @var array<string, array{label: string, color: string, share: ?string, hosts: array<int, string>}> */
    public const ALL = [
        'whatsapp' => ['label' => 'WhatsApp', 'color' => '#25D366', 'share' => 'https://api.whatsapp.com/send?text={text}%20{url}', 'hosts' => ['wa.me', 'whatsapp.com']],
        'facebook' => ['label' => 'Facebook', 'color' => '#1877F2', 'share' => 'https://www.facebook.com/sharer/sharer.php?u={url}', 'hosts' => ['facebook.com', 'fb.com']],
        'instagram' => ['label' => 'Instagram', 'color' => '#E4405F', 'share' => null, 'hosts' => ['instagram.com']],
        'threads' => ['label' => 'Threads', 'color' => '#111111', 'share' => 'https://www.threads.net/intent/post?text={text}%20{url}', 'hosts' => ['threads.net', 'threads.com']],
        'x' => ['label' => 'X', 'color' => '#111111', 'share' => 'https://twitter.com/intent/tweet?text={text}&url={url}', 'hosts' => ['x.com', 'twitter.com']],
        'linkedin' => ['label' => 'LinkedIn', 'color' => '#0A66C2', 'share' => 'https://www.linkedin.com/sharing/share-offsite/?url={url}', 'hosts' => ['linkedin.com']],
        'pinterest' => ['label' => 'Pinterest', 'color' => '#E60023', 'share' => 'https://pinterest.com/pin/create/button/?url={url}&description={text}', 'hosts' => ['pinterest.com', 'pin.it']],
        'telegram' => ['label' => 'Telegram', 'color' => '#229ED9', 'share' => 'https://t.me/share/url?url={url}&text={text}', 'hosts' => ['t.me', 'telegram.me']],
    ];

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_map(fn ($p) => $p['label'], self::ALL);
    }

    public static function canShare(string $platform): bool
    {
        return filled(self::ALL[$platform]['share'] ?? null);
    }

    public static function shareUrl(string $platform, string $url, string $text): ?string
    {
        $pattern = self::ALL[$platform]['share'] ?? null;

        return $pattern ? strtr($pattern, ['{url}' => rawurlencode($url), '{text}' => rawurlencode($text)]) : null;
    }

    /** URL profil harus https dan berada di domain resmi platform tersebut. */
    public static function isValidProfileUrl(string $platform, ?string $url): bool
    {
        $url = trim((string) $url);
        if (! str_starts_with(strtolower($url), 'https://')) {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        foreach (self::ALL[$platform]['hosts'] ?? [] as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }
}
