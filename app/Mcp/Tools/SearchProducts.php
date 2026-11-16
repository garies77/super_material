<?php

namespace App\Mcp\Tools;

use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Resources\Api\BrandResource;
use App\Http\Resources\Api\CategoryResource;
use App\Http\Resources\Api\ProductResource;
use App\Models\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Cari produk katalog (termasuk draf) berdasarkan nama/SKU, status, kategori, merek, atau rentang harga. Mengembalikan ringkasan; pakai get-product untuk detail lengkap.')]
#[IsReadOnly]
class SearchProducts extends AbilityTool
{
    protected string $ability = 'catalog:read';

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'query' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:published,draft'],
            'category' => ['nullable', 'string'],
            'brand' => ['nullable', 'string'],
            'price_min' => ['nullable', 'integer', 'min:0'],
            'price_max' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'between:1,50'],
        ]);

        $query = Product::query()->with(['category', 'brand']);
        ProductController::applyFilters($query, array_filter($validated, fn ($value) => $value !== null), hidePrices: false);

        if (isset($validated['status'])) {
            $query->where('is_published', $validated['status'] === 'published');
        }
        if (filled($validated['query'] ?? null)) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $validated['query']).'%';
            $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('sku', 'like', $like));
        }

        $total = (clone $query)->count();
        $products = $query->latest('updated_at')->orderByDesc('id')->limit($validated['limit'] ?? 20)->get();

        return Response::structured([
            'total' => $total,
            'products' => $products->map(fn (Product $product) => [
                'id' => $product->id,
                'slug' => $product->slug,
                'name' => $product->name,
                'status' => $product->is_published ? 'published' : 'draft',
                'sku' => $product->sku,
                'category' => $product->category ? CategoryResource::summary($product->category) : null,
                'brand' => $product->brand ? BrandResource::summary($product->brand) : null,
                'price' => ProductResource::price($product),
                'price_min' => $product->price_min !== null ? (int) $product->price_min : null,
                'price_max' => $product->price_max !== null ? (int) $product->price_max : null,
                'price_display' => $product->price_display,
                'availability' => $product->availability,
                'public_url' => $product->url(),
                'updated_at' => ProductResource::iso($product->updated_at),
            ])->values()->all(),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Potongan nama atau SKU produk.'),
            'status' => $schema->string()->enum(['published', 'draft'])->description('Kosongkan untuk semua status.'),
            'category' => $schema->string()->description('Slug kategori (termasuk subkategorinya). Lihat list-categories.'),
            'brand' => $schema->string()->description('Slug merek; beberapa dipisah koma. Lihat list-brands.'),
            'price_min' => $schema->integer()->min(0)->description('Harga minimum dalam rupiah.'),
            'price_max' => $schema->integer()->min(0)->description('Harga maksimum dalam rupiah.'),
            'limit' => $schema->integer()->min(1)->max(50)->default(20)->description('Jumlah produk, 1–50.'),
        ];
    }
}
