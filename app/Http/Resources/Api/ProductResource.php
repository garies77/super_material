<?php

namespace App\Http\Resources\Api;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesChannel;
use Illuminate\Http\Request;

/**
 * Produk untuk API publik (PRD 8.12.7). Aturan halaman berlaku: harga disembunyikan tidak dikirim,
 * harga coret hanya bila lebih tinggi dari harga jual dan dalam masa diskon, availability dan rating
 * hanya bila tampil di halaman, dan tautan beli selalu berupa go_url.
 *
 * @mixin Product
 */
class ProductResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        /** @var Product $product */
        $product = $this->resource;

        return [
            'id' => $product->id,
            'slug' => $product->slug,
            'name' => $product->name,
            'badge' => $product->badge,
            'brand' => $this->when($this->included($request, 'brand'), fn () => $product->brand ? BrandResource::summary($product->brand) : null),
            'category' => $this->when($this->included($request, 'category'), fn () => $product->category ? CategoryResource::summary($product->category) : null),
            'summary' => $product->summary,
            'unit' => $product->unit,
            'image_url' => $product->primaryImageUrl(),
            'price' => self::price($product),
            'discount_percent' => $product->isPriceHidden() ? null : $product->maxDiscountPercentage(),
            'availability' => $this->when(filled($product->availability), $product->availability),
            'rating' => $this->when($product->hasRating(), fn () => [
                'value' => (float) $product->formattedRatingValue(),
                'count' => (int) $product->rating_count,
                'updated_at' => self::iso($product->rating_updated_at),
            ]),
            'media' => $this->when($this->included($request, 'media'), fn () => ProductMediaResource::collection($product->allMedia())->resolve($request)),
            'variants' => $this->when($this->included($request, 'variants'), fn () => self::variants($product)),
            'links' => $this->when($this->included($request, 'links'), fn () => self::links($product)),
            'canonical_url' => self::canonicalUrl($product),
            'updated_at' => self::iso($product->updated_at),
        ];
    }

    /** Ringkasan produk untuk daftar terkait dan hasil tertaut. */
    public static function summary(Product $product): array
    {
        return [
            'id' => $product->id,
            'slug' => $product->slug,
            'name' => $product->name,
            'image_url' => $product->primaryImageUrl(),
            'price' => self::price($product),
            'discount_percent' => $product->isPriceHidden() ? null : $product->maxDiscountPercentage(),
            'canonical_url' => self::canonicalUrl($product),
        ];
    }

    /**
     * @return array{mode: string, min: int, max: int, currency: string, valid_until: ?string, updated_at: ?string}|null
     */
    public static function price(Product $product): ?array
    {
        if ($product->isPriceHidden()) {
            return null;
        }

        $min = $product->effectivePriceMin();
        if (! $min) {
            return null;
        }
        $max = max($min, (int) ($product->effectivePriceMax() ?? $min));

        return [
            'mode' => $max > $min ? 'range' : 'single',
            'min' => $min,
            'max' => $max,
            'currency' => 'IDR',
            'valid_until' => $product->price_valid_until?->isFuture() ? self::iso($product->price_valid_until) : null,
            'updated_at' => self::iso($product->updated_at),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public static function variants(Product $product): array
    {
        $hidden = $product->isPriceHidden();
        $variants = $product->relationLoaded('variants') ? $product->variants : $product->variants()->get();

        return $variants->where('is_active', true)->values()->map(function (ProductVariant $variant) use ($hidden) {
            $data = [
                'id' => $variant->id,
                'slug' => $variant->slug,
                'name' => $variant->name,
                'option_group' => $variant->option_group,
                'sku' => $variant->sku,
                'image_url' => $variant->imageUrl(),
                'is_active' => true,
            ];

            if (! $hidden) {
                $compare = $variant->effectiveCompareAtPrice();
                $data += [
                    'price' => $variant->effectivePrice(),
                    'compare_at_price' => $compare,
                    'discount_percent' => $compare ? $variant->discountPercentage() : null,
                    'discount_ends_at' => $compare ? self::iso($variant->discount_ends_at) : null,
                ];
            }

            return $data;
        })->all();
    }

    /**
     * Tautan beli aktif per kanal, selalu lewat rute pelacak /go/ agar klik tercatat (PRD 8.6).
     *
     * @return array<int, array{channel: string, name: string, type: string, go_url: string}>
     */
    public static function links(Product $product, ?ProductVariant $variant = null, int $quantity = 1): array
    {
        $query = array_filter([
            'variant' => $variant?->slug,
            'qty' => $quantity > 1 ? $quantity : null,
        ]);

        return $product->buyChannels()->map(fn (SalesChannel $channel) => [
            'channel' => $channel->slug,
            'name' => $channel->name,
            'type' => $channel->type,
            'go_url' => route('go', ['product' => $product->slug, 'channel' => $channel->slug] + $query),
        ])->values()->all();
    }

    public static function canonicalUrl(Product $product): string
    {
        return filled($product->canonical_url) ? $product->canonical_url : $product->url();
    }
}
