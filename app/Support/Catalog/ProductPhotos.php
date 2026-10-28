<?php

namespace App\Support\Catalog;

use App\Models\Media;
use App\Models\Product;
use App\Models\ProductMedia;

/**
 * Satu galeri foto produk di panel admin. Sumber kebenaran tetap tabel product_media (dibaca situs,
 * JSON-LD, sitemap gambar); kolom lama image/gallery hanya dibaca sebagai cadangan produk lama.
 * Foto pertama = foto utama dan juga disalin ke kolom image untuk thumbnail daftar produk.
 */
class ProductPhotos
{
    /** @return array<int, string> path foto berurutan untuk state FileUpload */
    public static function paths(Product $product): array
    {
        return $product->allMedia()
            ->filter(fn (ProductMedia $media) => $media->isImage() && filled($media->path))
            ->pluck('path')->unique()->values()->all();
    }

    /**
     * Samakan foto di product_media dengan urutan galeri. Teks alternatif yang sudah ada dipertahankan;
     * foto baru memakai teks alternatif Pustaka Media atau nama produk. Video tidak disentuh, hanya
     * diurutkan setelah foto.
     *
     * @param  array<int, string>  $paths
     */
    public static function sync(Product $product, array $paths): void
    {
        $paths = array_values(array_unique(array_filter($paths, fn ($path) => is_string($path) && $path !== '')));
        $existing = $product->media()->where('type', 'image')->get()->keyBy('path');
        $libraryAlt = Media::whereIn('path', $paths)->pluck('alt_text', 'path');

        $product->media()->where('type', 'image')->whereNotIn('path', $paths)->delete();

        foreach ($paths as $order => $path) {
            $media = $existing->get($path) ?? new ProductMedia(['type' => 'image', 'provider' => 'upload', 'path' => $path]);
            $media->alt_text = $media->alt_text ?: ($libraryAlt->get($path) ?: $product->name);
            $media->sort_order = $order;
            $product->media()->save($media);
        }

        $product->media()->where('type', 'video')->orderBy('sort_order')->get()
            ->each(fn (ProductMedia $video, int $index) => $video->update(['sort_order' => count($paths) + $index]));

        // Kolom lama: image = foto utama (thumbnail daftar, cadangan); gallery dikosongkan agar tidak ada sumber ganda
        $product->forceFill(['image' => $paths[0] ?? null, 'gallery' => null])->saveQuietly();
        $product->unsetRelation('media');
    }
}
