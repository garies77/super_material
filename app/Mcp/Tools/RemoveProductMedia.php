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

#[Description('Melepas satu foto/video dari produk berdasarkan media_id dari get-product. Berkasnya tetap tersimpan di Pustaka Media sehingga bisa dipasang lagi dari panel admin.')]
#[IsDestructive]
class RemoveProductMedia extends AbilityTool
{
    use WritesProducts;

    protected string $ability = 'catalog:write';

    public function handle(Request $request): Response|ResponseFactory
    {
        $product = $this->findProduct($request);
        if (! $product) {
            return $this->notFound();
        }

        $input = $this->validated($request, ['media_id' => ['required', 'integer']]);
        $media = $product->media()->whereKey($input['media_id'])->first();
        if (! $media) {
            return Response::error('Media tidak ditemukan di produk ini. Lihat daftar media_id lewat get-product.');
        }

        $media->delete();
        $product->touch();

        $this->audit($request, 'product.media_removed', $product, ['media_id' => $media->id, 'type' => $media->type, 'path' => $media->path]);

        return Response::structured($this->productDetail($product->refresh()));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return self::productIdentifierSchema($schema) + [
            'media_id' => $schema->integer()->required()->description('ID media dari get-product (media[].id).'),
        ];
    }
}
