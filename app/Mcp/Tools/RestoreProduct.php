<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\WritesProducts;
use App\Models\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Description('Memulihkan produk dari arsip. Status terbit/draf kembali seperti sebelum diarsipkan.')]
#[IsDestructive(false)]
#[IsIdempotent]
class RestoreProduct extends AbilityTool
{
    use WritesProducts;

    protected string $ability = 'catalog:write';

    public function handle(Request $request): Response|ResponseFactory
    {
        $key = trim((string) $request->get('product'));
        $product = $key === '' ? null : Product::onlyTrashed()
            ->where(fn ($q) => ctype_digit($key) ? $q->whereKey((int) $key) : $q->where('slug', $key))
            ->first();

        if (! $product) {
            return Response::error('Produk arsip tidak ditemukan. Kirim ID atau slug produk yang diarsipkan.');
        }

        $product->restore();
        $this->audit($request, 'product.restored', $product, []);

        return Response::structured($this->productDetail($product->refresh()));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'product' => $schema->string()->required()->description('ID (angka) atau slug produk yang diarsipkan.'),
        ];
    }
}
