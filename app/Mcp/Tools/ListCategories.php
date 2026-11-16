<?php

namespace App\Mcp\Tools;

use App\Models\Category;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Daftar semua kategori produk (termasuk yang nonaktif) beserta induknya dan jumlah produk langsung di kategori itu. Slug-nya dipakai untuk filter search-products.')]
#[IsReadOnly]
class ListCategories extends AbilityTool
{
    protected string $ability = 'catalog:read';

    public function handle(Request $request): ResponseFactory
    {
        $categories = Category::query()
            ->with('parent:id,slug')
            ->withCount(['products', 'products as published_products_count' => fn ($q) => $q->published()])
            ->orderBy('sort_order')->orderBy('name')
            ->get();

        return Response::structured([
            'categories' => $categories->map(fn (Category $category) => [
                'slug' => $category->slug,
                'name' => $category->name,
                'parent' => $category->parent?->slug,
                'is_active' => (bool) $category->is_active,
                'products' => $category->products_count,
                'published_products' => $category->published_products_count,
            ])->values()->all(),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
