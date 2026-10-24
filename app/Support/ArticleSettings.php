<?php

namespace App\Support;

use App\Models\Article;
use App\Models\Setting;

/**
 * Pengaturan global tampilan artikel (layout, lebar, visibilitas, urutan) dan media sosial artikel.
 * Disimpan di tabel settings dengan kunci sendiri, terpisah dari media sosial Footer Builder.
 * Bawaan sama dengan tampilan artikel sebelumnya.
 */
class ArticleSettings
{
    public const KEY = 'article_settings';

    public const SOCIAL_KEY = 'article_social';

    public const LAYOUTS = ['sidebar_right' => 'Sidebar kanan', 'sidebar_left' => 'Sidebar kiri', 'standard' => 'Standar (tanpa sidebar)'];

    /** Batas lebar (px) agar layout tetap responsif. */
    public const LIMITS = ['content_width' => [560, 960], 'sidebar_width' => [240, 420], 'gap' => [16, 80]];

    public const COMPONENTS = [
        'breadcrumb' => 'Breadcrumb',
        'category' => 'Kategori artikel',
        'title' => 'Judul artikel',
        'author' => 'Nama penulis',
        'published' => 'Tanggal publikasi',
        'updated' => 'Tanggal pembaruan',
        'reading_time' => 'Waktu baca',
        'image' => 'Gambar utama',
        'summary' => 'Ringkasan',
        'toc' => 'Daftar isi',
        'content' => 'Konten artikel',
        'tags' => 'Topik terkait (tag)',
        'author_box' => 'Informasi penulis di akhir artikel',
        'faq' => 'FAQ artikel',
        'related_products' => 'Produk terkait',
        'related_articles' => 'Artikel terkait',
    ];

    public const HEADER_BLOCKS = ['meta' => 'Penulis & tanggal', 'image' => 'Gambar utama', 'summary' => 'Ringkasan', 'toc' => 'Daftar isi'];

    public const SIDEBAR_WIDGETS = ['campaign' => 'Kampanye sidebar', 'popular' => 'Terpopuler', 'consult' => 'Banner konsultasi', 'categories' => 'Kategori terkait'];

    public const SOCIAL_POSITIONS = [
        'after_title' => 'Di bawah judul',
        'after_meta' => 'Setelah penulis & tanggal',
        'end' => 'Di akhir artikel',
        'sidebar' => 'Sidebar',
    ];

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'layout' => 'sidebar_right',
            'content_width' => 740,
            'sidebar_width' => 340,
            'gap' => 40,
            'show' => array_fill_keys(array_keys(self::COMPONENTS), true),
            'show_widgets' => array_fill_keys(array_keys(self::SIDEBAR_WIDGETS), true),
            'header_order' => array_map(fn ($k) => ['key' => $k], array_keys(self::HEADER_BLOCKS)),
            'sidebar_order' => array_map(fn ($k) => ['key' => $k], array_keys(self::SIDEBAR_WIDGETS)),
        ];
    }

    /** @return array<string, mixed> */
    public static function socialDefaults(): array
    {
        // Sekali saja saat belum pernah disimpan: bawa URL Instagram yang sebelumnya dipakai artikel
        $instagram = collect(SiteLayout::socials())->firstWhere('platform', 'instagram')['url'] ?? null;

        return [
            'label' => 'Bagikan',
            'show_copy' => true,
            'positions' => ['after_meta', 'end'],
            'items' => [
                ['platform' => 'whatsapp', 'type' => 'share', 'url' => null, 'visible' => true],
                ['platform' => 'facebook', 'type' => 'share', 'url' => null, 'visible' => true],
                ['platform' => 'instagram', 'type' => 'profile', 'url' => $instagram, 'visible' => true],
                ['platform' => 'threads', 'type' => 'share', 'url' => null, 'visible' => false],
                ['platform' => 'x', 'type' => 'share', 'url' => null, 'visible' => false],
                ['platform' => 'linkedin', 'type' => 'share', 'url' => null, 'visible' => false],
                ['platform' => 'telegram', 'type' => 'share', 'url' => null, 'visible' => false],
                ['platform' => 'pinterest', 'type' => 'share', 'url' => null, 'visible' => false],
            ],
        ];
    }

    /** Pengaturan tersimpan digabung dengan bawaan; nilai di luar batas dipotong. @return array<string, mixed> */
    public static function get(): array
    {
        $d = self::defaults();
        $s = array_replace($d, self::read(self::KEY));

        $s['layout'] = isset(self::LAYOUTS[$s['layout']]) ? $s['layout'] : $d['layout'];
        foreach (self::LIMITS as $key => [$min, $max]) {
            $s[$key] = max($min, min($max, (int) $s[$key]));
        }
        $s['show'] = array_replace($d['show'], array_map('boolval', (array) $s['show']));
        $s['show_widgets'] = array_replace($d['show_widgets'], array_map('boolval', (array) $s['show_widgets']));
        $s['header_order'] = self::order($s['header_order'], self::HEADER_BLOCKS);
        $s['sidebar_order'] = self::order($s['sidebar_order'], self::SIDEBAR_WIDGETS);
        $s['has_sidebar'] = $s['layout'] !== 'standard';

        return $s;
    }

    /** @return array<string, mixed> */
    public static function social(): array
    {
        $raw = self::read(self::SOCIAL_KEY);

        return $raw ? array_replace(self::socialDefaults(), $raw) : self::socialDefaults();
    }

    /**
     * Posisi bar media sosial yang benar-benar dipakai. Sidebar tanpa layout bersidebar dialihkan ke akhir
     * artikel; posisi yang sama tidak pernah dirender dua kali.
     *
     * @return array<int, string>
     */
    public static function socialPositions(bool $hasSidebar): array
    {
        $positions = array_values(array_intersect((array) self::social()['positions'], array_keys(self::SOCIAL_POSITIONS)));
        if (! $hasSidebar && in_array('sidebar', $positions, true)) {
            $positions = array_diff($positions, ['sidebar']);
            $positions[] = 'end';
        }

        return array_values(array_unique($positions));
    }

    /**
     * Tautan media sosial siap render untuk artikel ini, sesuai urutan; yang disembunyikan atau tidak valid dibuang.
     *
     * @return array<int, array{platform: string, label: string, color: string, type: string, href: string}>
     */
    public static function socialLinks(Article $article): array
    {
        $text = $article->title.' - '.Setting::brandName();

        return collect(self::social()['items'] ?? [])
            ->filter(fn ($i) => ($i['visible'] ?? false) && isset(SocialPlatforms::ALL[$i['platform'] ?? '']))
            ->map(function ($i) use ($article, $text) {
                $p = SocialPlatforms::ALL[$i['platform']];
                $type = ($i['type'] ?? 'share') === 'share' && SocialPlatforms::canShare($i['platform']) ? 'share' : 'profile';
                $href = $type === 'share'
                    ? SocialPlatforms::shareUrl($i['platform'], $article->url(), $text)
                    : (SocialPlatforms::isValidProfileUrl($i['platform'], $i['url'] ?? null) ? trim($i['url']) : null);

                return $href ? ['platform' => $i['platform'], 'label' => $p['label'], 'color' => $p['color'], 'type' => $type, 'href' => $href] : null;
            })
            ->filter()->values()->all();
    }

    /** Urutan tersimpan; kunci baru yang belum ada ditambahkan di akhir, kunci tak dikenal dibuang. */
    private static function order(mixed $saved, array $known): array
    {
        $keys = collect((array) $saved)->map(fn ($i) => is_array($i) ? ($i['key'] ?? null) : $i)
            ->filter(fn ($k) => isset($known[$k]))->unique()->values()->all();

        return array_values(array_unique(array_merge($keys, array_keys($known))));
    }

    private static function read(string $key): array
    {
        $decoded = json_decode((string) Setting::get($key), true);

        return is_array($decoded) ? $decoded : [];
    }
}
