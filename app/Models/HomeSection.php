<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Satu seksi di halaman beranda. Kolom terindeks untuk struktur (urutan, aktif),
 * konfigurasi khusus tiap jenis seksi di kolom JSON `settings`.
 */
class HomeSection extends Model
{
    public const CACHE_KEY = 'homepage_sections';

    public const TYPES = [
        'hero' => 'Hero & Kampanye',
        'categories' => 'Kategori',
        'products' => 'Produk',
        'articles' => 'Panduan & Artikel',
        'banner' => 'Banner Promosi',
        'cta' => 'Konsultasi WhatsApp',
        'form' => 'Formulir',
    ];

    /** Nama berkas komponen di resources/views/components/home. */
    public const VIEWS = [
        'hero' => 'hero',
        'categories' => 'categories',
        'products' => 'products',
        'articles' => 'articles',
        'banner' => 'promo-banner',
        'cta' => 'cta-consultation',
        'form' => 'form',
    ];

    public const PRODUCT_MODES = [
        'featured' => 'Produk unggulan (dilengkapi produk terbaru bila kurang)',
        'manual' => 'Pilih produk sendiri',
        'category' => 'Dari satu kategori',
        'popular' => 'Paling populer (klik tombol beli terbanyak)',
        'latest' => 'Produk terbaru',
    ];

    public const ARTICLE_MODES = [
        'latest' => 'Terbaru (tanggal terbit)',
        'popular' => 'Paling populer (jumlah pembaca)',
        'manual' => 'Pilih artikel sendiri',
        'category' => 'Dari satu kategori',
    ];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['settings' => 'array', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        $forget = fn () => Cache::forget(self::CACHE_KEY);
        static::saved($forget);
        static::deleted($forget);
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('sort_order')->orderBy('id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        $value = data_get($this->settings, $key);

        return ($value === null || $value === '') ? $default : $value;
    }

    /**
     * Poin jaminan sebagai daftar [['text' => '...']]. Membuka bentuk bersarang ({"text":{"text":"..."}})
     * dan string polos agar data dari versi form lama tetap terbaca.
     */
    public static function normalizeBadges(mixed $items): array
    {
        return collect(is_array($items) ? $items : [])
            ->map(function ($item) {
                while (is_array($item)) {
                    $item = $item['text'] ?? null;
                }

                return is_scalar($item) ? trim((string) $item) : '';
            })
            ->filter()->map(fn ($text) => ['text' => $text])->values()->all();
    }

    public function view(): string
    {
        return 'components.home.'.(self::VIEWS[$this->type] ?? $this->type);
    }

    /** Lima seksi bawaan yang sama persis dengan beranda sebelum fitur kustomisasi. */
    public static function defaults(): array
    {
        return [
            ['key' => 'hero', 'type' => 'hero', 'title' => 'Hero & Kampanye', 'sort_order' => 1, 'settings' => [
                'badge' => 'Katalog bahan bangunan',
                'heading' => 'Temukan bahan bangunan',
                'heading_accent' => 'tepat untuk proyek Anda',
                'description' => null, // kosong = deskripsi situs di Pengaturan situs
                'show_search' => true,
                'search_placeholder' => 'Cari semen, keramik, baja ringan…',
                'trust_badges' => [
                    ['text' => '100% Produk Asli & SNI'],
                    ['text' => 'Transaksi Toko Resmi'],
                    ['text' => 'Konsultasi WhatsApp Cepat'],
                ],
            ]],
            ['key' => 'categories', 'type' => 'categories', 'title' => 'Kategori', 'sort_order' => 2, 'settings' => [
                'source' => 'all', 'category_ids' => [], 'columns' => 4,
            ]],
            ['key' => 'products_featured', 'type' => 'products', 'title' => 'Produk pilihan', 'sort_order' => 3, 'settings' => [
                'selection_mode' => 'featured', 'limit' => 8, 'cta_text' => 'Lihat semua produk →', 'cta_url' => '/produk',
            ]],
            ['key' => 'articles_recent', 'type' => 'articles', 'title' => 'Panduan & artikel', 'sort_order' => 4, 'settings' => [
                'sort_by' => 'latest', 'limit' => 3, 'cta_text' => 'Semua artikel →', 'cta_url' => '/artikel',
            ]],
            ['key' => 'cta_large', 'type' => 'cta', 'title' => 'Butuh material dalam jumlah besar?', 'sort_order' => 5, 'settings' => [
                'description' => 'Konsultasikan kebutuhan proyek Anda — kami bantu hitung dan rekomendasikan produk yang tepat.',
                'button_text' => 'Chat WhatsApp',
                'whatsapp_number' => null,
                'message' => 'Halo, saya ingin konsultasi kebutuhan material proyek.',
            ]],
        ];
    }

    /** Isi seksi bawaan yang belum ada; tidak menimpa seksi yang sudah diubah admin. */
    public static function seedDefaults(): void
    {
        foreach (self::defaults() as $row) {
            static::firstOrCreate(['key' => $row['key']], $row + ['is_active' => true]);
        }
    }
}
