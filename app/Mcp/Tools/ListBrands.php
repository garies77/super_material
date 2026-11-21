<?php

namespace App\Mcp\Tools;

use App\Models\Brand;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Daftar semua merek beserta jumlah produknya. Slug-nya dipakai untuk filter search-products.')]
#[IsReadOnly]
class ListBrands extends AbilityTool
{
    protected string $ability = 'catalog:read';

    public function handle(Request $request): ResponseFactory
    {
        $brands = Brand::query()
            ->withCount(['products', 'products as published_products_count' => fn ($q) => $q->published()])
            ->orderBy('name')
            ->get();

        return Response::structured([
            'brands' => $brands->map(fn (Brand $brand) => [
                'slug' => $brand->slug,
                'name' => $brand->name,
                'products' => $brand->products_count,
                'published_products' => $brand->published_products_count,
            ])->values()->all(),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
