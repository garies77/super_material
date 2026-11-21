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

#[Description('MENGGANTI seluruh varian & harga produk dengan daftar yang dikirim; varian lama yang tidak ada di daftar dihapus. Kirim [] untuk mengosongkan. Ambil varian sekarang dengan get-product dulu bila hanya ingin mengubah sebagian.')]
#[IsDestructive]
#[IsIdempotent]
class ReplaceProductVariants extends AbilityTool
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
            ['variants' => $request->get('variants')],
            ProductRules::prefixed('variants', ProductRules::variants(), optional: false),
        );
        ProductWriter::replaceVariants($product, $data['variants']);

        $this->audit($request, 'product.variants_replaced', $product, ['count' => count($data['variants'])]);

        return Response::structured($this->productDetail($product->refresh()));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return self::productIdentifierSchema($schema) + [
            'variants' => self::variantsSchema($schema)->required()->description('Daftar varian lengkap yang baru.'),
        ];
    }
}
