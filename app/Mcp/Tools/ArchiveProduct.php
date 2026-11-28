<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\WritesProducts;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Description('Mengarsipkan produk: hilang dari situs, pencarian, dan daftar katalog, tetapi datanya tetap ada dan bisa dipulihkan dengan restore-product atau lewat panel admin (filter Arsip). Produk yang masih terbit hanya bisa diarsipkan dengan force_unpublish=true; jelaskan dampaknya dan minta persetujuan pengguna dulu. Tidak ada penghapusan permanen lewat MCP.')]
#[IsDestructive]
class ArchiveProduct extends AbilityTool
{
    use WritesProducts;

    protected string $ability = 'catalog:write';

    public function handle(Request $request): Response|ResponseFactory
    {
        $product = $this->findProduct($request);
        if (! $product) {
            return $this->notFound();
        }

        if ($product->is_published && $request->get('force_unpublish') !== true) {
            return Response::error('Produk ini masih terbit. Kirim force_unpublish=true untuk tetap mengarsipkan (produk langsung hilang dari situs), atau jadikan draf dulu dengan update-product.');
        }

        $product->delete();
        $this->audit($request, 'product.archived', $product, ['was_published' => ['from' => $product->is_published, 'to' => null]]);

        return Response::structured([
            'archived' => $product->slug,
            'name' => $product->name,
            'restore_with' => 'restore-product',
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return self::productIdentifierSchema($schema) + [
            'force_unpublish' => $schema->boolean()->description('Wajib true untuk mengarsipkan produk yang masih terbit.'),
        ];
    }
}
