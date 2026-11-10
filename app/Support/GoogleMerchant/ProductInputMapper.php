<?php

namespace App\Support\GoogleMerchant;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Str;

/**
 * Menerjemahkan produk katalog menjadi ProductInput Merchant API.
 * Produk bervarian menjadi satu offer per varian aktif yang dikelompokkan lewat itemGroupId.
 */
class ProductInputMapper
{
    private const AVAILABILITY = [
        'InStock' => 'IN_STOCK',
        'LimitedAvailability' => 'LIMITED_AVAILABILITY',
        'PreOrder' => 'PREORDER',
        'OutOfStock' => 'OUT_OF_STOCK',
    ];

    /**
     * Offer yang layak tampil di Google, dikunci offer ID. Kosong bila produk tidak boleh tayang
     * (draf, harga disembunyikan, tanpa harga, atau tanpa gambar).
     *
     * @return array<string, array<string, mixed>>
     */
    public function map(Product $product): array
    {
        if (! $product->isLive() || $product->isPriceHidden()) {
            return [];
        }

        $images = $product->allImageUrls();
        $primaryImage = $product->primaryImageUrl() ?? ($images[0] ?? null);
        $variants = $product->variants->filter(fn (ProductVariant $variant) => $variant->is_active ?? true);

        if ($variants->isEmpty()) {
            $price = $product->effectivePriceMin();
            if (! $price || ! $primaryImage) {
                return [];
            }

            $offerId = $this->offerId($product->sku ?: $product->slug);

            return [$offerId => $this->productInput($offerId, $this->baseAttributes($product, $primaryImage, $images) + [
                'title' => $product->name,
                'link' => $product->url(),
                'price' => $this->price($price),
                'mpn' => $product->mpn ?: null,
                'identifierExists' => filled($product->mpn),
            ])];
        }

        $inputs = [];
        foreach ($variants as $variant) {
            $price = $variant->effectivePrice();
            $image = $variant->imageUrl() ?: $primaryImage;
            if (! $price || ! $image) {
                continue;
            }

            $offerId = $this->offerId($variant->sku ?: $product->slug.'-'.$variant->slug);
            $attributes = $this->baseAttributes($product, $image, $images) + [
                'title' => $product->name.' - '.$variant->name,
                'link' => $product->url().'?varian='.$variant->slug,
                'itemGroupId' => $this->offerId($product->sku ?: $product->slug),
                'identifierExists' => false,
            ];

            $compareAtPrice = $variant->effectiveCompareAtPrice();
            if ($compareAtPrice) {
                $attributes['price'] = $this->price($compareAtPrice);
                $attributes['salePrice'] = $this->price($price);
                if ($variant->discount_starts_at || $variant->discount_ends_at) {
                    $attributes['salePriceEffectiveDate'] = array_filter([
                        'startTime' => $variant->discount_starts_at?->toIso8601ZuluString(),
                        'endTime' => $variant->discount_ends_at?->toIso8601ZuluString(),
                    ]);
                }
            } else {
                $attributes['price'] = $this->price($price);
            }

            $inputs[$offerId] = $this->productInput($offerId, $attributes);
        }

        return $inputs;
    }

    /**
     * @param  array<int, string>  $images
     * @return array<string, mixed>
     */
    private function baseAttributes(Product $product, string $image, array $images): array
    {
        $description = trim(html_entity_decode(strip_tags((string) $product->description))) ?: $product->seoDescription();

        return array_filter([
            'description' => Str::limit(preg_replace('/\s+/u', ' ', $description), 4990) ?: $product->name,
            'imageLink' => $image,
            'additionalImageLinks' => array_slice(array_values(array_diff($images, [$image])), 0, 10) ?: null,
            'availability' => self::AVAILABILITY[$product->availability] ?? 'IN_STOCK',
            'condition' => 'NEW',
            'brand' => $product->brand?->name,
            'productTypes' => $product->category ? [$product->category->name] : null,
        ], fn ($value) => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function productInput(string $offerId, array $attributes): array
    {
        return [
            'offerId' => $offerId,
            'contentLanguage' => config('services.google_merchant.content_language'),
            'feedLabel' => config('services.google_merchant.feed_label'),
            'productAttributes' => array_filter($attributes, fn ($value) => $value !== null),
        ];
    }

    /**
     * @return array{amountMicros: string, currencyCode: string}
     */
    private function price(int $rupiah): array
    {
        return ['amountMicros' => (string) ($rupiah * 1_000_000), 'currencyCode' => 'IDR'];
    }

    /**
     * Offer ID Google maksimal 50 karakter.
     */
    private function offerId(string $value): string
    {
        return mb_substr(trim($value), 0, 50);
    }
}
