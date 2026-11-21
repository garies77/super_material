<?php

namespace App\Mcp\Tools;

use App\Http\Resources\Api\AdminProductResource;
use App\Models\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Detail lengkap satu produk (semua status): deskripsi, spesifikasi, varian & harga, media, tautan marketplace, FAQ, dan SEO. Sama dengan GET /api/v1/admin/products/{id}.')]
#[IsReadOnly]
class GetProduct extends AbilityTool
{
    protected string $ability = 'catalog:read';

    /** @var array<int, string> */
    private const RELATIONS = ['category', 'brand', 'variants', 'media', 'links.channel', 'faqs'];

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $this->validated($request, [
            'slug' => ['required_without:id', 'nullable', 'string'],
            'id' => ['required_without:slug', 'nullable', 'integer'],
        ]);

        $product = Product::query()
            ->when($validated['id'] ?? null, fn ($q, int $id) => $q->whereKey($id), fn ($q) => $q->where('slug', $validated['slug']))
            ->with(self::RELATIONS)
            ->first();

        if (! $product) {
            return Response::error('Produk tidak ditemukan. Cari dulu dengan search-products.');
        }

        $resourceRequest = new HttpRequest;
        $resourceRequest->attributes->set('api_includes', ['detail']);

        return Response::structured(AdminProductResource::make($product)->resolve($resourceRequest));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'slug' => $schema->string()->description('Slug produk, mis. dari search-products.'),
            'id' => $schema->integer()->description('ID produk; dipakai bila slug kosong.'),
        ];
    }
}
