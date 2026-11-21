<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\WritesProducts;
use App\Support\Catalog\ProductRules;
use App\Support\Catalog\ProductWriter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Description('Mengubah produk yang sudah ada. Hanya kolom yang dikirim yang berubah; kolom lain tetap. Untuk varian dan tautan marketplace pakai replace-product-variants dan set-product-links. Mengubah status ke "published" membuat produk tampil di situs.')]
#[IsDestructive]
#[IsIdempotent]
class UpdateProduct extends AbilityTool
{
    use WritesProducts;

    protected string $ability = 'catalog:write';

    public function handle(Request $request): Response|ResponseFactory
    {
        $product = $this->findProduct($request);
        if (! $product) {
            return $this->notFound();
        }

        $input = Arr::except($request->all(), ['product', 'variants', 'links']);
        if ($input === []) {
            return Response::error('Tidak ada kolom yang dikirim untuk diubah.');
        }

        // gte:price_min dibandingkan dengan nilai tersimpan bila price_min tidak dikirim (sama dengan REST API)
        $compare = array_key_exists('price_max', $input) && ! array_key_exists('price_min', $input)
            ? ['price_min' => $product->price_min] : [];

        $data = $this->validateProductInput($input + $compare, ProductRules::product($product));
        $changes = ProductWriter::update($product, array_diff_key($data, $compare));

        if ($changes) {
            $this->audit($request, 'product.updated', $product, $changes);
        }

        return Response::structured([
            'changed' => array_keys($changes),
            'product' => $this->productDetail($product->refresh()),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return self::productIdentifierSchema($schema) + self::productFieldsSchema($schema);
    }
}
