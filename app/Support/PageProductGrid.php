<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Product;
use App\Models\SalesChannel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Blok "Grid produk marketplace" di Pages: kumpulan produk dengan tombol beli di satu kanal
 * (Shopee, Tokopedia, Lazada, TikTok Shop, ...). Hanya produk terbit yang punya tautan di kanal itu yang tampil,
 * jadi tombol beli tidak pernah buntu. Klik lewat /go agar tercatat di laporan klik.
 */
class PageProductGrid
{
    public const SOURCES = [
        'channel' => 'Otomatis: semua produk yang dijual di kanal ini',
        'category' => 'Dari kategori',
        'manual' => 'Pilih produk sendiri',
    ];

    public const SORTS = [
        'featured' => 'Unggulan dulu, lalu terbaru',
        'latest' => 'Terbaru',
        'name' => 'Nama (A–Z)',
        'price_asc' => 'Harga termurah',
    ];

    public const MAX_ITEMS = 48;

    public static function channel(array $data): ?SalesChannel
    {
        return SalesChannel::where('is_active', true)->find($data['channel_id'] ?? null);
    }

    /** @return Collection<int, Product> */
    public static function products(array $data, ?SalesChannel $channel = null): Collection
    {
        $channel ??= self::channel($data);
        if (! $channel) {
            return collect();
        }

        $limit = max(1, min(self::MAX_ITEMS, (int) ($data['limit'] ?? 12)));
        $query = Product::published()
            ->with(['media', 'variants', 'category', 'brand'])
            ->when(! $channel->isWhatsapp(), fn (Builder $q) => $q->whereHas('links', fn (Builder $l) => $l->where('sales_channel_id', $channel->id)));

        $source = $data['source'] ?? 'channel';

        if ($source === 'manual') {
            $ids = array_values(array_filter(array_map('intval', (array) ($data['product_ids'] ?? []))));

            return $query->whereIn('id', $ids)->get()
                ->sortBy(fn (Product $p) => array_search($p->id, $ids, true))
                ->take($limit)->values();
        }

        if ($source === 'category') {
            $ids = self::categoryWithChildren((int) ($data['category_id'] ?? 0));
            $query->whereIn('category_id', $ids ?: [0]);
        }

        match ($data['sort'] ?? 'featured') {
            'latest' => $query->latest('id'),
            'name' => $query->orderBy('name'),
            'price_asc' => $query->orderByRaw('price_min IS NULL')->orderBy('price_min'),
            default => $query->orderByDesc('is_featured')->latest('id'),
        };

        return $query->limit($limit)->get();
    }

    /** URL tombol beli: lewat /go (tercatat) dengan UTM halaman agar sumbernya terbaca di Pusat Laporan. */
    public static function buyUrl(Product $product, SalesChannel $channel, ?string $campaign): string
    {
        return route('go', [$product->slug, $channel->slug]).'?'.http_build_query(array_filter([
            'utm_source' => 'landing-page',
            'utm_medium' => 'page',
            'utm_campaign' => $campaign,
        ]));
    }

    /**
     * Data terstruktur ItemList: daftar produk yang dirujuk halaman ini (tiap item menunjuk halaman produknya,
     * yang sudah punya markup Product lengkap). Membantu Google memahami halaman kumpulan produk.
     *
     * @param  Collection<int, Product>  $products
     */
    public static function itemList(Collection $products, string $name): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $name,
            'numberOfItems' => $products->count(),
            'itemListElement' => $products->values()->map(fn (Product $p, int $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'url' => $p->url(),
                'name' => $p->name,
            ])->all(),
        ];
    }

    /** @return array<int, int> */
    private static function categoryWithChildren(int $id): array
    {
        if ($id <= 0) {
            return [];
        }

        $ids = [$id];
        $frontier = [$id];
        while ($frontier) {
            $frontier = Category::whereIn('parent_id', $frontier)->pluck('id')->diff($ids)->all();
            $ids = array_merge($ids, $frontier);
        }

        return $ids;
    }
}
