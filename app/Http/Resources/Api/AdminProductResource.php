<?php

namespace App\Http\Resources\Api;

use App\Models\Faq;
use App\Models\Product;
use App\Models\ProductLink;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

/**
 * Produk untuk API admin: semua status dan data mentah (harga acuan, varian nonaktif,
 * URL kanal asli) untuk otomatisasi. Hanya dapat diakses dengan ability catalog:read.
 *
 * @mixin Product
 */
class AdminProductResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        /** @var Product $product */
        $product = $this->resource;

        return [
            'id' => $product->id,
            'slug' => $product->slug,
            'name' => $product->name,
            'status' => $product->is_published ? 'published' : 'draft',
            'badge' => $product->badge,
            'category' => $product->category ? CategoryResource::summary($product->category) : null,
            'brand' => $product->brand ? BrandResource::summary($product->brand) : null,
            'sku' => $product->sku,
            'mpn' => $product->mpn,
            'unit' => $product->unit,
            'summary' => $product->summary,
            'description' => $this->when($this->included($request, 'detail'), $product->description),
            'specifications' => $this->when($this->included($request, 'detail'), $product->specifications ?: (object) []),
            'price_display' => $product->price_display,
            'price_min' => $product->price_min !== null ? (int) $product->price_min : null,
            'price_max' => $product->price_max !== null ? (int) $product->price_max : null,
            'price' => ProductResource::price($product),
            'price_valid_until' => self::iso($product->price_valid_until),
            'price_review_needed' => (bool) $product->price_review_needed,
            'availability' => $product->availability,
            'rating_value' => $product->rating_value,
            'rating_count' => $product->rating_count,
            'rating_updated_at' => $product->rating_updated_at?->toDateString(),
            'whatsapp_number' => $product->whatsapp_number,
            'shipping_note' => $product->shipping_note,
            'guarantee_note' => $product->guarantee_note,
            'show_quantity' => (bool) $product->show_quantity,
            'is_featured' => (bool) $product->is_featured,
            'meta_title' => $product->meta_title,
            'meta_description' => $product->meta_description,
            'canonical_url' => $product->canonical_url,
            'noindex' => (bool) $product->noindex,
            'variants' => $product->variants->map(fn (ProductVariant $v) => [
                'id' => $v->id,
                'slug' => $v->slug,
                'name' => $v->name,
                'option_group' => $v->option_group,
                'sku' => $v->sku,
                'price' => $v->effectivePrice(),
                'compare_at_price' => $v->compare_at_price,
                'effective_compare_at_price' => $v->effectiveCompareAtPrice(),
                'discount_percent' => $v->discountPercentage(),
                'discount_starts_at' => self::iso($v->discount_starts_at),
                'discount_ends_at' => self::iso($v->discount_ends_at),
                'image_url' => $v->imageUrl(),
                'is_active' => (bool) $v->is_active,
            ])->values()->all(),
            'media' => ProductMediaResource::collection($product->media)->resolve($request),
            'links' => $product->links->map(fn (ProductLink $l) => [
                'channel' => $l->channel?->slug,
                'url' => $l->url,
                'message' => $l->message,
                'is_active' => (bool) $l->is_active,
                'go_url' => $l->channel ? route('go', ['product' => $product->slug, 'channel' => $l->channel->slug]) : null,
            ])->values()->all(),
            'faqs' => $this->when($this->included($request, 'detail'), fn () => $product->faqs->map(fn (Faq $f) => ['question' => $f->question, 'answer' => $f->answer])->values()->all()),
            'public_url' => $product->url(),
            'created_at' => self::iso($product->created_at),
            'updated_at' => self::iso($product->updated_at),
        ];
    }
}
