<?php

namespace App\Http\Resources\Api;

use App\Models\Faq;
use App\Models\Product;
use App\Support\Api\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Detail produk: spesifikasi, media, varian, FAQ, produk dan artikel terkait (PRD 8.12.5).
 * Kode kustom (HTML/CSS/JS) produk tidak pernah dikirim lewat API.
 *
 * @mixin Product
 */
class ProductDetailResource extends ProductResource
{
    public function toArray(Request $request): array
    {
        /** @var Product $product */
        $product = $this->resource;

        $specifications = collect(is_array($product->specifications) ? $product->specifications : [])
            ->map(fn ($value, $name) => ['name' => (string) $name, 'value' => is_scalar($value) ? (string) $value : json_encode($value)])
            ->values()->all();

        return array_merge(parent::toArray($request), [
            'sku' => $product->sku,
            'mpn' => $product->mpn,
            'description' => HtmlSanitizer::clean($product->description),
            'specifications' => $specifications,
            'shipping_note' => $product->shipping_note,
            'guarantee_note' => $product->guarantee_note,
            'show_quantity' => (bool) $product->show_quantity,
            'faqs' => $product->faqs->map(fn (Faq $faq) => ['question' => $faq->question, 'answer' => $faq->answer])->values()->all(),
            'related_products' => $this->relatedProducts->map(fn (Product $p) => ProductResource::summary($p))->values()->all(),
            'related_articles' => $this->relatedArticles->map(fn ($a) => ArticleResource::summary($a))->values()->all(),
        ]);
    }

    public Collection $relatedProducts;

    public Collection $relatedArticles;

    public function __construct($resource, ?Collection $relatedProducts = null, ?Collection $relatedArticles = null)
    {
        parent::__construct($resource);
        $this->relatedProducts = $relatedProducts ?? collect();
        $this->relatedArticles = $relatedArticles ?? collect();
    }
}
