<?php

namespace App\Support;

use App\Models\Article;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

/**
 * Query pencarian bersama untuk halaman /cari dan live search /cari/live.
 * Hanya konten terbit; LIKE berparameter dengan % dan _ dari pengguna di-escape; judul yang diawali kata kunci tampil lebih dulu.
 */
class Search
{
    public const MIN_LENGTH = 2;

    public const TYPES = ['produk', 'artikel'];

    /** Trim, satukan spasi, batasi panjang. */
    public static function normalize(?string $q): string
    {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $q)), 0, 100);
    }

    public static function type(?string $type): ?string
    {
        return in_array($type, self::TYPES, true) ? $type : null;
    }

    public static function products(string $q): Builder
    {
        [$contains, $prefix] = self::patterns($q);
        // Di dalam whereHas pakai whereRaw (AND), bukan orWhereRaw: OR akan lolos dari syarat relasi
        $like = fn (Builder $b, string $col, string $boolean = 'or') => $b->whereRaw("{$col} LIKE ? ESCAPE '!'", [$contains], $boolean);

        return Product::published()
            ->where(fn ($w) => $like($like($like($w, 'products.name'), 'products.summary'), 'products.sku')
                ->orWhereHas('variants', fn ($v) => $like($v, 'product_variants.sku', 'and'))
                ->orWhereHas('brand', fn ($b) => $like($b, 'brands.name', 'and'))
                ->orWhereHas('category', fn ($c) => $like($c, 'categories.name', 'and')))
            ->orderByRaw("CASE WHEN products.name LIKE ? ESCAPE '!' THEN 0 WHEN products.name LIKE ? ESCAPE '!' THEN 1 ELSE 2 END", [$prefix, $contains])
            ->orderBy('products.name');
    }

    public static function articles(string $q): Builder
    {
        [$contains] = self::patterns($q);
        $like = fn (Builder $b, string $col) => $b->orWhereRaw("{$col} LIKE ? ESCAPE '!'", [$contains]);

        return Article::published()
            ->where(fn ($w) => $like($like($like($w, 'title'), 'summary'), 'content'))
            ->orderByRaw("CASE WHEN title LIKE ? ESCAPE '!' THEN 0 WHEN summary LIKE ? ESCAPE '!' THEN 1 ELSE 2 END", [$contains, $contains])
            ->latest('published_at');
    }

    /** Pola "mengandung" dan "diawali"; ESCAPE '!' berlaku sama di SQLite, MySQL, dan PostgreSQL. */
    private static function patterns(string $q): array
    {
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q);

        return ['%'.$escaped.'%', $escaped.'%'];
    }
}
