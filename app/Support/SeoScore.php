<?php

namespace App\Support;

use App\Models\Article;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/**
 * Skor kelengkapan SEO 0–100 (PRD 8.7 & 9.10.5): delapan pemeriksaan berbobot sama.
 * Skor membantu kualitas data dan tidak menjamin hasil kaya atau peringkat tertentu.
 */
class SeoScore
{
    /** @return array{score: int, checks: array<string, bool>} */
    public static function forProduct(Product $p): array
    {
        $media = $p->allMedia()->filter->isImage();
        $priceOk = $p->isPriceHidden() || $p->effectivePriceMin() !== null;

        return self::result([
            'Teks alternatif semua gambar terisi' => $media->isNotEmpty() && $media->every(fn ($m) => filled($m->alt_text)),
            'Nama file gambar deskriptif' => $media->isNotEmpty() && $media->every(fn ($m) => self::descriptiveName((string) $m->path)),
            'Gambar utama ≥ 800 px' => self::minSide($media->first()?->path, 800),
            'Merek, SKU, dan harga terisi' => filled($p->brand_id) && filled($p->sku) && $priceOk,
            'Masa berlaku harga masih berlaku' => $p->isPriceHidden() || ($p->price_valid_until && $p->price_valid_until->isFuture()),
            'Status ketersediaan terisi' => filled($p->availability),
            'Gambar Open Graph tersedia dan bukan logo' => self::ogOk($p->primaryImageUrl()),
            'Judul dan deskripsi meta terisi atau dapat dibuat otomatis' => filled($p->meta_title ?: $p->name) && filled($p->meta_description ?: $p->summary),
        ]);
    }

    /** @return array{score: int, checks: array<string, bool>} */
    public static function forArticle(Article $a): array
    {
        return self::result([
            'Keterangan gambar utama terisi' => filled($a->image_caption),
            'Nama file gambar deskriptif' => filled($a->image) && self::descriptiveName($a->image),
            'Gambar utama ≥ 1200 px lebar' => self::minSide($a->image, 1200, 'width'),
            'Penulis terisi' => filled($a->author_id),
            // Relasi yang sudah dimuat (mis. dari isi form yang belum disimpan) dipakai apa adanya
            'Kategori atau tag terisi' => self::hasRelated($a, 'categories') || self::hasRelated($a, 'tags'),
            'Gambar Open Graph tersedia dan bukan logo' => self::ogOk($a->primaryImageUrl()),
            'Judul ≤ 110 karakter' => mb_strlen($a->title) <= 110,
            'Judul dan deskripsi meta terisi atau dapat dibuat otomatis' => filled($a->meta_title ?: $a->title) && filled($a->meta_description ?: $a->summary),
        ]);
    }

    private static function hasRelated(Article $article, string $relation): bool
    {
        return $article->relationLoaded($relation) ? $article->getRelation($relation)->isNotEmpty() : $article->{$relation}()->exists();
    }

    private static function result(array $checks): array
    {
        return [
            'score' => (int) round(count(array_filter($checks)) / count($checks) * 100),
            'checks' => $checks,
        ];
    }

    /** Menolak nama generik: IMG_0023, image1, 1, DSC..., Screenshot..., atau hanya angka/hash. */
    private static function descriptiveName(string $path): bool
    {
        $name = strtolower(pathinfo($path, PATHINFO_FILENAME));

        return ! preg_match('/^(img|dsc|image|foto|photo|screenshot|untitled|download)[-_ ]?\d*/', $name)
            && ! preg_match('/^[0-9a-f\d_-]{8,}$/', $name)
            && preg_match('/[a-z]{3,}/', $name) === 1;
    }

    /** Ukuran tidak bisa diperiksa untuk URL eksternal: dianggap lolos. */
    private static function minSide(?string $path, int $min, string $mode = 'longest'): bool
    {
        if (blank($path)) {
            return false;
        }
        if (str_starts_with($path, 'http')) {
            return true;
        }
        $disk = Storage::disk('public');
        if (! $disk->exists($path) || ! ($size = @getimagesize($disk->path($path)))) {
            return false;
        }

        return ($mode === 'width' ? $size[0] : max($size[0], $size[1])) >= $min;
    }

    private static function ogOk(?string $url): bool
    {
        return filled($url) && $url !== Setting::logoUrl();
    }
}
