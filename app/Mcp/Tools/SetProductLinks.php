<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\WritesProducts;
use App\Support\Catalog\ProductRules;
use App\Support\Catalog\ProductWriter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Description('MENGATUR tautan beli (tombol "Beli di …") produk. Daftar yang dikirim menggantikan semua tautan: kanal yang tidak dikirim DIHAPUS. URL harus berada di domain resmi kanal (mis. shopee.co.id untuk shopee). Ambil tautan sekarang dengan get-product dulu bila hanya ingin menambah satu kanal.')]
#[IsDestructive]
#[IsIdempotent]
class SetProductLinks extends AbilityTool
{
    use WritesProducts;

    protected string $ability = 'catalog:write';

    public function handle(Request $request): Response|ResponseFactory
    {
        $product = $this->findProduct($request);
        if (! $product) {
            return $this->notFound();
        }

        $data = $this->validateProductInput(
            ['links' => $request->get('links')],
            ProductRules::prefixed('links', ProductRules::links(), optional: false),
        );
        ProductWriter::replaceLinks($product, $data['links']);

        $this->audit($request, 'product.links_replaced', $product, ['channels' => array_column($data['links'], 'channel')]);

        return Response::structured($this->productDetail($product->refresh()));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return self::productIdentifierSchema($schema) + [
            'links' => self::linksSchema($schema)->required()->description('Daftar tautan lengkap yang baru.'),
        ];
    }
}
