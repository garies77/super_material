<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\WritesProducts;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Description('Mengatur urutan foto/video produk. Kirim SEMUA media_id produk (dari get-product) dalam urutan baru; yang pertama menjadi foto utama.')]
#[IsDestructive(false)]
#[IsIdempotent]
class ReorderProductMedia extends AbilityTool
{
    use WritesProducts;

    protected string $ability = 'catalog:write';

    public function handle(Request $request): Response|ResponseFactory
    {
        $product = $this->findProduct($request);
        if (! $product) {
            return $this->notFound();
        }

        $input = $this->validated($request, [
            'media_ids' => ['required', 'array', 'min:1'],
            'media_ids.*' => ['integer', 'distinct'],
        ]);

        $order = array_map('intval', $input['media_ids']);
        $existing = $product->media()->pluck('id')->all();
        if (count($order) !== count($existing) || array_diff($existing, $order) !== []) {
            return Response::error('media_ids harus berisi semua media produk ini tepat satu kali. Media produk sekarang: '.implode(', ', $existing).'.');
        }

        DB::transaction(function () use ($product, $order) {
            foreach ($order as $position => $mediaId) {
                $product->media()->whereKey($mediaId)->update(['sort_order' => $position]);
            }
            $product->touch();
        });

        $this->audit($request, 'product.media_reordered', $product, ['media_ids' => $order]);

        return Response::structured($this->productDetail($product->refresh()));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return self::productIdentifierSchema($schema) + [
            'media_ids' => $schema->array()->items($schema->integer())->required()
                ->description('Semua media_id produk dalam urutan baru; yang pertama menjadi foto utama.'),
        ];
    }
}
