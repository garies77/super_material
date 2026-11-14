<?php

namespace App\Support;

use App\Models\LinkPage;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/**
 * Kustomisasi tampilan Link Bio (seperti Linktree). Semua pilihan disimpan di kolom JSON link_pages.design,
 * jadi menambah tata letak, gaya tombol, atau font baru cukup di konstanta di sini (plus CSS-nya di view).
 * Nilai selalu disaring ulang saat dibaca: warna wajib hex, pilihan wajib ada di daftar, agar aman masuk ke <style>.
 */
class LinkBioDesign
{
    public const LAYOUTS = [
        'classic' => 'Classic',
        'hero' => 'Hero',
        'banner' => 'Banner',
        'cutout' => 'Cutout',
        'shape' => 'Shape',
    ];

    public const LAYOUT_HINTS = [
        'classic' => 'Foto bulat kecil di atas nama.',
        'hero' => 'Foto lebar penuh di atas, memudar ke warna latar.',
        'banner' => 'Gambar banner di atas, foto bulat menimpa tepinya.',
        'cutout' => 'Foto besar tanpa bingkai; cocok untuk PNG tanpa latar.',
        'shape' => 'Foto besar dengan bentuk unik.',
    ];

    public const SHAPES = [
        'circle' => 'Bulat',
        'rounded' => 'Kotak tumpul',
        'oval' => 'Oval',
        'loop' => 'Loop',
        'flower' => 'Bunga',
        'burst' => 'Burst',
    ];

    public const TITLE_SIZES = ['normal' => 'Normal', 'large' => 'Besar'];

    public const BACKGROUNDS = ['solid' => 'Warna polos', 'gradient' => 'Gradasi', 'image' => 'Gambar'];

    public const BUTTON_STYLES = [
        'fill' => 'Isi penuh',
        'soft' => 'Isi + bayangan lembut',
        'hard' => 'Isi + bayangan tegas',
        'outline' => 'Garis tepi',
    ];

    /** Warna cepat untuk tombol: warna merek marketplace/platform yang sering dipakai, plus warna Jaya Intero. */
    public const BUTTON_SWATCHES = [
        '#EE4D2D' => 'Shopee',
        '#03AC0E' => 'Tokopedia',
        '#0F146D' => 'Lazada',
        '#000000' => 'TikTok Shop',
        '#25D366' => 'WhatsApp',
        '#1877F2' => 'Facebook',
        '#E4405F' => 'Instagram',
        '#FF0000' => 'YouTube',
        '#0B3D91' => 'Biru Jaya Intero',
        '#FFC20E' => 'Kuning Jaya Intero',
    ];

    /** Warna ikon media sosial: ikut warna teks, warna asli tiap platform, atau satu warna pilihan. */
    public const SOCIAL_STYLES = ['text' => 'Ikut warna teks', 'brand' => 'Warna asli merek', 'custom' => 'Warna pilihan sendiri'];

    public const BUTTON_RADII = ['square' => 'Kotak', 'rounded' => 'Tumpul', 'pill' => 'Kapsul'];

    /** Kunci => [label, nama keluarga Google Fonts]. */
    public const FONTS = [
        'jakarta' => ['Plus Jakarta Sans', 'Plus Jakarta Sans'],
        'montserrat' => ['Montserrat', 'Montserrat'],
        'outfit' => ['Outfit', 'Outfit'],
        'poppins' => ['Poppins', 'Poppins'],
        'inter' => ['Inter', 'Inter'],
        'playfair' => ['Playfair Display (klasik)', 'Playfair Display'],
    ];

    /** Tema siap pakai; memilih tema mengisi semua pengaturan desain, lalu masing-masing bisa diubah. */
    public const PRESETS = [
        'light' => [
            'label' => 'Terang',
            'bg_type' => 'solid', 'bg_color' => '#F6F7F9', 'bg_color_2' => '#E2E8F0',
            'text_color' => '#0B0F19', 'button_style' => 'soft', 'button_radius' => 'rounded',
            'button_color' => '#FFFFFF', 'button_text_color' => '#121826', 'accent_color' => '#F59309', 'font' => 'jakarta',
        ],
        'dark' => [
            'label' => 'Gelap',
            'bg_type' => 'solid', 'bg_color' => '#0B0F19', 'bg_color_2' => '#1E2535',
            'text_color' => '#FFFFFF', 'button_style' => 'fill', 'button_radius' => 'rounded',
            'button_color' => '#1E2535', 'button_text_color' => '#FFFFFF', 'accent_color' => '#F59309', 'font' => 'jakarta',
        ],
        'brand' => [
            'label' => 'Warna merek situs',
            'bg_type' => 'solid', 'bg_color' => null, 'bg_color_2' => null,
            'text_color' => null, 'button_style' => 'fill', 'button_radius' => 'rounded',
            'button_color' => '#FFFFFF', 'button_text_color' => '#121826', 'accent_color' => '#121826', 'font' => 'outfit',
        ],
        'jaya' => [
            'label' => 'Jaya Intero (biru-kuning)',
            'bg_type' => 'gradient', 'bg_color' => '#0B3D91', 'bg_color_2' => '#061F4A',
            'text_color' => '#FFFFFF', 'button_style' => 'hard', 'button_radius' => 'pill',
            'button_color' => '#FFC20E', 'button_text_color' => '#0B1B3A', 'accent_color' => '#FFFFFF', 'font' => 'montserrat',
        ],
        'minimal' => [
            'label' => 'Minimalis',
            'bg_type' => 'solid', 'bg_color' => '#FFFFFF', 'bg_color_2' => '#FFFFFF',
            'text_color' => '#111111', 'button_style' => 'outline', 'button_radius' => 'square',
            'button_color' => '#111111', 'button_text_color' => '#111111', 'accent_color' => '#111111', 'font' => 'inter',
        ],
        'sunset' => [
            'label' => 'Senja',
            'bg_type' => 'gradient', 'bg_color' => '#FF7E5F', 'bg_color_2' => '#FEB47B',
            'text_color' => '#FFFFFF', 'button_style' => 'soft', 'button_radius' => 'pill',
            'button_color' => '#FFFFFF', 'button_text_color' => '#7A2E12', 'accent_color' => '#7A2E12', 'font' => 'poppins',
        ],
    ];

    private const COLOR_KEYS = ['bg_color', 'bg_color_2', 'text_color', 'button_color', 'button_text_color', 'accent_color'];

    /** @return array<string, string> */
    public static function presetOptions(): array
    {
        return array_map(fn (array $preset) => $preset['label'], self::PRESETS);
    }

    /**
     * Pengaturan lengkap dari satu preset (warna merek situs dibaca dari Pengaturan situs).
     *
     * @return array<string, string|null>
     */
    public static function preset(?string $key): array
    {
        $preset = self::PRESETS[$key] ?? self::PRESETS['light'];
        unset($preset['label']);

        if ($key === 'brand') {
            $primary = Setting::themeColor('theme_primary');
            $preset['bg_color'] = $primary;
            $preset['bg_color_2'] = $primary;
            $preset['text_color'] = Setting::contrast($primary, '#000000') >= Setting::contrast($primary, '#ffffff') ? '#111111' : '#FFFFFF';
        }

        return [
            'layout' => 'classic',
            'shape' => 'circle',
            'title_size' => 'normal',
            'social_style' => 'text',
            'social_color' => null,
            'bg_image_path' => null,
        ] + $preset;
    }

    /**
     * Pengaturan siap pakai untuk halaman: preset dari kolom theme ditimpa design yang tersimpan, lalu disaring.
     *
     * @param  array<string, mixed>|null  $design
     * @return array<string, string|null>
     */
    public static function resolve(?string $theme, ?array $design): array
    {
        $base = self::preset($theme);
        $merged = array_merge($base, array_filter((array) $design, fn ($v) => $v !== null && $v !== ''));

        $pick = fn (string $key, array $allowed) => array_key_exists((string) $merged[$key], $allowed) ? $merged[$key] : $base[$key];

        $clean = [
            'layout' => $pick('layout', self::LAYOUTS),
            'shape' => $pick('shape', self::SHAPES),
            'title_size' => $pick('title_size', self::TITLE_SIZES),
            'bg_type' => $pick('bg_type', self::BACKGROUNDS),
            'button_style' => $pick('button_style', self::BUTTON_STYLES),
            'button_radius' => $pick('button_radius', self::BUTTON_RADII),
            'font' => $pick('font', self::FONTS),
            'social_style' => $pick('social_style', self::SOCIAL_STYLES),
            'bg_image_path' => self::safePath($merged['bg_image_path'] ?? null),
        ];

        foreach (self::COLOR_KEYS as $key) {
            $clean[$key] = self::color($merged[$key] ?? null) ?? self::color($base[$key]) ?? '#FFFFFF';
        }

        // Warna ikon pilihan sendiri; kosong = warna teks
        $clean['social_color'] = self::color($merged['social_color'] ?? null) ?? $clean['text_color'];

        return $clean;
    }

    public static function forPage(LinkPage $page): array
    {
        return self::resolve($page->theme, $page->design);
    }

    public static function color(mixed $value): ?string
    {
        $value = trim((string) $value);

        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtoupper($value) : null;
    }

    /** Teks di atas warna tertentu: hitam atau putih, mana yang kontrasnya lebih tinggi (WCAG). */
    public static function onColor(string $hex): string
    {
        return Setting::contrast($hex, '#000000') >= Setting::contrast($hex, '#ffffff') ? '#111111' : '#FFFFFF';
    }

    public static function fontFamily(string $font): string
    {
        return self::FONTS[$font][1] ?? self::FONTS['jakarta'][1];
    }

    public static function fontUrl(string $font): string
    {
        return 'https://fonts.googleapis.com/css2?family='.str_replace(' ', '+', self::fontFamily($font)).':wght@400;500;600;700;800&display=swap';
    }

    /**
     * URL aman untuk dipakai di url("…") CSS: tanpa kutip, kurung, atau spasi.
     */
    public static function cssUrl(?string $url): ?string
    {
        return $url && preg_match('/^[^"\'()<>\s\\\\]+$/', $url) ? $url : null;
    }

    public static function storageUrl(?string $path): ?string
    {
        $path = self::safePath($path);

        return $path ? Storage::disk('public')->url($path) : null;
    }

    private static function safePath(mixed $path): ?string
    {
        return is_string($path) && preg_match('#^[A-Za-z0-9._\-/]+$#', $path) && ! str_contains($path, '..') ? $path : null;
    }

    /**
     * Variabel CSS halaman. Semua nilai sudah disaring di resolve().
     *
     * @param  array<string, string|null>  $design
     */
    public static function cssVariables(array $design, ?string $backgroundImageUrl = null): string
    {
        $background = match ($design['bg_type']) {
            'gradient' => "linear-gradient(180deg, {$design['bg_color']} 0%, {$design['bg_color_2']} 100%)",
            'image' => ($url = self::cssUrl($backgroundImageUrl))
                ? "linear-gradient(rgba(0,0,0,.35), rgba(0,0,0,.35)), url(\"{$url}\") center / cover no-repeat"
                : $design['bg_color'],
            default => $design['bg_color'],
        };

        $radius = ['square' => '6px', 'rounded' => '16px', 'pill' => '999px'][$design['button_radius']];

        $vars = [
            '--lb-bg' => $design['bg_color'],
            '--lb-background' => $background,
            '--lb-text' => $design['text_color'],
            '--lb-btn' => $design['button_color'],
            '--lb-btn-text' => $design['button_text_color'],
            '--lb-accent' => $design['accent_color'],
            '--lb-accent-text' => self::onColor($design['accent_color']),
            '--lb-radius' => $radius,
            '--lb-font' => "'".self::fontFamily($design['font'])."', ui-sans-serif, system-ui, sans-serif",
        ];

        return ':root{'.collect($vars)->map(fn ($v, $k) => "{$k}:{$v}")->implode(';').'}';
    }
}
