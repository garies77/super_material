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

/**
 * Berbeda dengan POST /api/v1/admin/products (langsung terbit), produk dari asisten AI dibuat sebagai DRAF
 * kecuali status "published" dikirim eksplisit, agar tim bisa meninjau dulu sebelum tampil di situs.
 */
#[Description('Membuat produk baru. Dibuat sebagai DRAF (belum tampil di situs) kecuali status="published" dikirim. Varian, tautan marketplace, dan FAQ boleh dikirim sekaligus. Validasi sama dengan panel admin; data tidak valid ditolak seluruhnya.')]
#[IsDestructive(false)]
class CreateProduct extends AbilityTool
{
    use WritesProducts;

    protected string $ability = 'catalog:write';

    public function handle(Request $request): Response|ResponseFactory
    {
        $input = $request->all();
        $input['status'] ??= 'draft';

        $data = $this->validateProductInput($input, ProductRules::product());
        $product = ProductWriter::create($data);

        $this->audit($request, 'product.created', $product, ['fields' => array_keys($data)]);

        return Response::structured($this->productDetail($product));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        $fields = self::productFieldsSchema($schema);
        $fields['name'] = $fields['name']->required();

        return $fields + [
            'variants' => self::variantsSchema($schema)->description('Varian & harga jual (opsional).'),
            'links' => self::linksSchema($schema)->description('Tautan beli per kanal (opsional).'),
        ];
    }
}
