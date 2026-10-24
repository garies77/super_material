<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Konfigurasi Navbar dan Footer dari dashboard (disimpan di tabel settings sebagai JSON).
 * Bawaan meniru tampilan sebelumnya sehingga situs tidak berubah sampai admin menyuntingnya.
 * Di rute pratinjau tampilan, konfigurasi yang belum disimpan dibaca dari sesi Admin/Editor.
 */
class SiteLayout
{
    public const NAVBAR_KEY = 'navbar_menu';

    public const FOOTER_KEY = 'footer_config';

    public const SOCIALS = [
        'instagram' => 'Instagram', 'facebook' => 'Facebook', 'tiktok' => 'TikTok', 'youtube' => 'YouTube',
        'x' => 'X (Twitter)', 'linkedin' => 'LinkedIn', 'whatsapp' => 'WhatsApp',
    ];

    /** @return array<int, array<string, mixed>> */
    public static function navbar(): array
    {
        $items = self::read(self::NAVBAR_KEY)['items'] ?? [
            ['label' => 'Produk', 'link_type' => 'internal', 'url' => '/produk', 'is_active' => true, 'children' => []],
            ['label' => 'Artikel', 'link_type' => 'internal', 'url' => '/artikel', 'is_active' => true, 'children' => []],
        ];

        return self::activeLinks($items, withChildren: true);
    }

    /** @return array<string, mixed> */
    public static function footer(): array
    {
        $config = (self::read(self::FOOTER_KEY) ?? []) + self::footerDefaults();

        $config['columns'] = collect($config['columns'] ?? [])
            ->filter(fn ($c) => $c['is_active'] ?? true)
            ->map(fn ($c) => ['links' => self::activeLinks($c['links'] ?? [])] + $c)
            ->values()->all();
        $config['bottom_links'] = self::activeLinks($config['bottom_links'] ?? []);
        $config['socials'] = self::socials();
        $config['has_contact_column'] = collect($config['columns'])->contains(fn ($c) => ($c['type'] ?? null) === 'contact');
        $config['copyright'] = strtr((string) $config['copyright'], ['{year}' => date('Y'), '{site}' => Setting::brandName()]);

        return $config;
    }

    /**
     * Satu sumber tautan media sosial (diisi di Footer Builder), dipakai footer dan artikel.
     * Tautan kosong atau tidak aman tidak ikut.
     *
     * @return array<int, array{platform: string, url: string}>
     */
    public static function socials(): array
    {
        return collect((self::read(self::FOOTER_KEY) ?? [])['socials'] ?? [])
            ->filter(fn ($s) => isset(self::SOCIALS[$s['platform'] ?? '']) && filled($s['url'] ?? null) && preg_match(LinkTarget::SAFE_URL, $s['url']))
            ->map(fn ($s) => ['platform' => $s['platform'], 'url' => trim($s['url'])])
            ->values()->all();
    }

    public static function socialUrl(string $platform): ?string
    {
        return collect(self::socials())->firstWhere('platform', $platform)['url'] ?? null;
    }

    /** Bawaan sama dengan footer sebelumnya: logo + tagline + alamat, kolom Kategori dan Informasi. */
    public static function footerDefaults(): array
    {
        return [
            'show_logo' => true,
            'description' => null,
            'columns' => [
                ['title' => 'Kategori', 'type' => 'categories', 'is_active' => true, 'links' => []],
                ['title' => 'Informasi', 'type' => 'pages', 'is_active' => true, 'links' => []],
            ],
            'address' => Setting::get('contact_address'),
            'phone' => null,
            'email' => null,
            'socials' => [],
            'copyright' => '© {year} {site}. Harga bersifat acuan; harga final mengikuti marketplace/penjual.',
            'bottom_links' => [],
            'bottom_align' => 'center',
        ];
    }

    public static function isCurrent(?string $url): bool
    {
        if (! $url || ! str_starts_with($url, '/')) {
            return false;
        }

        $path = '/'.ltrim(request()->path(), '/');

        return $url === '/' ? $path === '/' : ($path === $url || str_starts_with($path, rtrim($url, '/').'/'));
    }

    /** Hanya item aktif dengan tujuan valid; URL hasil resolusi disertakan sebagai 'href'. */
    private static function activeLinks(array $items, bool $withChildren = false): array
    {
        return collect($items)
            ->filter(fn ($i) => ($i['is_active'] ?? true) && filled($i['label'] ?? null))
            ->map(function ($i) use ($withChildren) {
                $i['href'] = LinkTarget::url($i);
                $i['external'] = LinkTarget::isExternal($i);
                if ($withChildren) {
                    $i['children'] = self::activeLinks($i['children'] ?? []);
                }

                return $i;
            })
            ->filter(fn ($i) => $i['href'] || ! empty($i['children']))
            ->values()->all();
    }

    private static function read(string $key): ?array
    {
        if (request()->routeIs('layout.preview') && is_array($preview = session('layout_preview.'.$key))) {
            return $preview;
        }

        $raw = Setting::get($key);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($decoded) ? $decoded : null;
    }
}
