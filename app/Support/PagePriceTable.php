<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesChannel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Blok "Tabel harga" di Pages: daftar harga acuan per varian, dikelompokkan per kategori atau merek, dengan
 * tombol beli di satu kanal. Produk dipilih dengan aturan yang sama seperti grid produk marketplace
 * (hanya produk terbit yang punya tautan di kanal itu), jadi harga di tabel selalu ikut data produk.
 */
class PagePriceTable
{
    public const GROUPS = [
        'category' => 'Per kategori',
        'brand' => 'Per merek',
        'none' => 'Tanpa pengelompokan',
    ];

    /** Rata-rata baris per kelompok di bawah angka ini: tabel tidak dipecah (bagian bernomor berisi 1 baris terlihat kosong). */
    public const MIN_ROWS_PER_GROUP = 3;

    /**
     * Kelompok tabel. Bila kelompoknya tipis (katalog kecil dengan banyak kategori), semua baris digabung menjadi satu
     * tabel tanpa judul kelompok; nama kategori/merek tetap tampil sebagai label di tiap baris.
     *
     * @param  array<string, mixed>  $data
     * @return Collection<int, array{name: string, anchor: string, rows: Collection<int, array{product: Product, variant: ?ProductVariant, label: ?string, price: ?string, unit: ?string, compare: ?string}>}>
     */
    public static function groups(array $data, SalesChannel $channel): Collection
    {
        $products = PageProductGrid::products($data + ['limit' => PageProductGrid::MAX_ITEMS, 'sort' => 'name'], $channel);
        $groupBy = $data['group_by'] ?? 'category';
        $labelOf = fn (Product $p): ?string => $groupBy === 'brand' ? $p->brand?->name : $p->category?->name;
        $byName = fn (Collection $items) => $items->sortBy(fn (Product $p) => mb_strtolower($p->name))->values();

        $groups = $products
            ->groupBy(fn (Product $p) => $groupBy === 'none' ? '' : ($labelOf($p) ?? ($groupBy === 'brand' ? 'Merek lainnya' : 'Lainnya')))
            ->sortKeys(SORT_NATURAL | SORT_FLAG_CASE)
            ->map(fn (Collection $items, string $name) => self::group($name, $byName($items), $labelOf));

        $rowCount = $groups->sum(fn (array $g) => $g['rows']->count());
        if ($groups->count() > 1 && $rowCount / $groups->count() < self::MIN_ROWS_PER_GROUP) {
            $sorted = $products->sortBy(fn (Product $p) => mb_strtolower(($labelOf($p) ?? "\u{10FFFF}").'|'.$p->name))->values();

            return collect([self::group('', $sorted, $labelOf)]);
        }

        return $groups->values();
    }

    /**
     * @param  Collection<int, Product>  $products
     * @return array{name: string, anchor: string, rows: Collection<int, array<string, mixed>>}
     */
    private static function group(string $name, Collection $products, \Closure $labelOf): array
    {
        return [
            'name' => $name,
            'anchor' => 'harga-'.(str($name)->slug()->value() ?: 'produk'),
            'rows' => $products->flatMap(fn (Product $p) => self::rows($p)->map(fn (array $row) => $row + ['label' => $labelOf($p)]))->values(),
        ];
    }

    /** Waktu perubahan data produk terbaru di tabel, untuk keterangan "diperbarui". */
    public static function updatedAt(Collection $groups): ?Carbon
    {
        return $groups->flatMap(fn (array $g) => $g['rows'])->map(fn (array $r) => $r['variant']?->updated_at ?? $r['product']->updated_at)->max();
    }

    /** @return Collection<int, array{product: Product, variant: ?ProductVariant, price: ?string, unit: ?string, compare: ?string}> */
    private static function rows(Product $product): Collection
    {
        $variants = $product->variants->filter(fn (ProductVariant $v) => $v->is_active ?? true)->sortBy('sort_order')->values();

        $unit = filled($product->unit) ? '/ '.$product->unit : null;

        if ($product->isPriceHidden()) {
            return collect([['product' => $product, 'variant' => null, 'price' => 'Hubungi kami', 'unit' => null, 'compare' => null]]);
        }

        if ($variants->isEmpty()) {
            $min = $product->effectivePriceMin();
            $max = $product->effectivePriceMax();
            $price = $min ? self::money($min).($max && $max > $min ? ' – '.self::money($max) : '') : null;

            return collect([['product' => $product, 'variant' => null, 'price' => $price, 'unit' => $price ? $unit : null, 'compare' => null]]);
        }

        return $variants->map(fn (ProductVariant $v) => [
            'product' => $product,
            'variant' => $v,
            'price' => ($price = $v->effectivePrice()) ? self::money($price) : null,
            'unit' => $price ? $unit : null,
            'compare' => $v->discountPercentage() ? self::money((int) $v->effectiveCompareAtPrice()) : null,
        ]);
    }

    private static function money(int $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }
}
