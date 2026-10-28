<?php

namespace App\Filament\Support;

use Closure;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\View;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Panel "misi kelengkapan" di samping form: skor SEO dihitung dari isi form yang sedang diketik (belum disimpan),
 * memakai SeoScore yang sama dengan kolom "Skor SEO" di tabel daftar. Tiap pemeriksaan tampil sebagai misi
 * berbahasa sederhana yang bisa diklik untuk menggulir ke field-nya; 100% dirayakan dengan confetti.
 *
 * Field pemicu cukup diberi ->afterStateUpdated(SeoScorePanel::refresh()) agar hanya panel
 * yang di-render ulang, bukan seluruh form.
 */
class SeoScorePanel
{
    public const KEY = 'seoScorePanel';

    /** Label pemeriksaan SeoScore => [teks misi, field tujuan]. */
    public const PRODUCT_MISSIONS = [
        'Teks alternatif semua gambar terisi' => ['🖼️ Isi nama produk agar teks alternatif foto terisi otomatis', 'name'],
        'Nama file gambar deskriptif' => ['📝 Pakai foto dengan nama file yang jelas (bukan IMG_0023)', 'photos'],
        'Gambar utama ≥ 800 px' => ['📸 Pasang foto utama minimal 800 px', 'photos'],
        'Merek, SKU, dan harga terisi' => ['🏷️ Lengkapi merek, SKU, dan harga', 'brand_id'],
        'Masa berlaku harga masih berlaku' => ['📅 Isi masa berlaku harga yang belum lewat', 'price_valid_until'],
        'Status ketersediaan terisi' => ['📦 Pilih status ketersediaan stok', 'availability'],
        'Gambar Open Graph tersedia dan bukan logo' => ['🔗 Siapkan foto untuk dibagikan di WhatsApp & Google', 'photos'],
        'Judul dan deskripsi meta terisi atau dapat dibuat otomatis' => ['✍️ Tulis ringkasan singkat produk', 'summary'],
    ];

    public const ARTICLE_MISSIONS = [
        'Keterangan gambar utama terisi' => ['💬 Tulis keterangan foto utama', 'image_caption'],
        'Nama file gambar deskriptif' => ['📝 Pakai foto dengan nama file yang jelas (bukan IMG_0023)', 'image'],
        'Gambar utama ≥ 1200 px lebar' => ['📸 Pasang foto utama minimal 1200 px lebar', 'image'],
        'Penulis terisi' => ['👤 Pilih penulis artikel', 'author_id'],
        'Kategori atau tag terisi' => ['🗂️ Pilih kategori atau tag', 'categories'],
        'Gambar Open Graph tersedia dan bukan logo' => ['🔗 Siapkan foto untuk dibagikan di WhatsApp & Google', 'image'],
        'Judul ≤ 110 karakter' => ['📏 Persingkat judul (maks. 110 karakter)', 'title'],
        'Judul dan deskripsi meta terisi atau dapat dibuat otomatis' => ['✍️ Tulis ringkasan singkat artikel', 'summary'],
    ];

    /**
     * @param  Closure  $score  dievaluasi seperti closure Filament (Get, record, …); mengembalikan hasil SeoScore
     * @param  array<string, array{0: string, 1: string}>  $missions  PRODUCT_MISSIONS atau ARTICLE_MISSIONS
     */
    public static function make(Closure $score, array $missions = []): View
    {
        return View::make('filament.seo-score-panel')->key(self::KEY)
            ->viewData(fn (View $component) => ['result' => $component->evaluate($score), 'missions' => $missions]);
    }

    /**
     * Callback afterStateUpdated untuk field pemicu: hanya panel yang di-render ulang. Panel dicari lewat key
     * di skema akar karena Filament dapat membangun ulang komponen form (referensi langsung bisa basi).
     */
    public static function refresh(): Closure
    {
        return function (Component $component): void {
            $component->getRootContainer()->getComponent(self::KEY, withHidden: true)?->partiallyRender();
        };
    }

    /**
     * Path tersimpan dari state FileUpload ([uuid => path]); unggahan yang belum disimpan dilewati
     * karena nama & ukuran berkas finalnya baru diketahui setelah simpan.
     *
     * @return array<int, string>
     */
    public static function storedPaths(mixed $state): array
    {
        return array_values(array_filter((array) $state, fn ($file) => is_string($file) && $file !== ''));
    }

    public static function hasPendingUpload(mixed $state): bool
    {
        return collect((array) $state)->contains(fn ($file) => $file instanceof TemporaryUploadedFile);
    }
}
