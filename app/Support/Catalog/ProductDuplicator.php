<?php

namespace App\Support\Catalog;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Salin produk lengkap (spesifikasi, foto & video, varian, tautan kanal, FAQ) sebagai draf baru,
 * untuk produk mirip seperti ukuran/warna lain. Berkas foto tidak disalin: Pustaka Media memakai path yang sama.
 * Kode unik (SKU, MPN) dan data milik produk asli (rating, statistik, URL kanonis) dikosongkan.
 */
class ProductDuplicator
{
    public static function duplicate(Product $product): Product
    {
        // Muat ulang dari database: record dari tabel daftar membawa atribut agregat (mis. links_count) yang bukan kolom
        $product = $product->newQuery()->findOrFail($product->getKey());

        return DB::transaction(function () use ($product): Product {
            // Pembuat & status tinjauan milik salinan sendiri: diisi ulang saat dibuat (HasReviewWorkflow).
            // Kode custom hanya ikut disalin oleh pengguna berizin; GuardsCustomCode menolak penyimpanannya oleh yang lain.
            $copy = $product->replicate([
                'slug', 'sku', 'mpn', 'canonical_url', 'rating_value', 'rating_count', 'rating_updated_at', 'price_review_needed',
                'created_by', 'review_status', 'review_note', 'submitted_by', 'submitted_at',
                ...(auth()->user()?->canManageCustomHtml() === false ? Product::CUSTOM_CODE_FIELDS : []),
            ]);
            $copy->name = Str::limit($product->name, 240, '').' (salinan)';
            $copy->slug = self::uniqueSlug($product->slug);
            $copy->is_published = false;
            $copy->is_featured = false;
            $copy->save();

            foreach ($product->media()->get() as $media) {
                $copy->media()->save($media->replicate());
            }

            foreach ($product->variants()->get() as $variant) {
                $copy->variants()->save($variant->replicate(['sku']));
            }

            foreach ($product->links()->get() as $link) {
                $copy->links()->save($link->replicate());
            }

            foreach ($product->faqs()->get() as $faq) {
                $copy->faqs()->save($faq->replicate());
            }

            return $copy;
        });
    }

    private static function uniqueSlug(string $slug): string
    {
        $base = Str::limit($slug, 230, '').'-salinan';
        $candidate = $base;

        for ($n = 2; Product::where('slug', $candidate)->exists(); $n++) {
            $candidate = "{$base}-{$n}";
        }

        return $candidate;
    }
}
