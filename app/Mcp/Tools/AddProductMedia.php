<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\WritesProducts;
use App\Support\Catalog\ProductMediaUploader;
use App\Support\RemoteFile;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Description('Menambah foto (dari URL https publik) atau video YouTube ke produk. Foto diunduh server lalu diproses sama seperti unggahan panel admin: jpg/png/webp, minimal 50.000 piksel, nama berkas deskriptif, dan turunan 16:9, 4:3, 1:1. Foto dengan sort_order terkecil menjadi foto utama; tanpa sort_order, media ditaruh paling akhir. alt_text wajib dan sebaiknya menjelaskan isi foto.')]
#[IsDestructive(false)]
class AddProductMedia extends AbilityTool
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
            'url' => ['required_without:youtube_url', 'prohibits:youtube_url', 'string', 'max:1000'],
            'youtube_url' => ['required_without:url', 'string'],
            'alt_text' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $file = isset($input['url']) ? RemoteFile::download($input['url'], (int) config('api.uploads.image_max_kb')) : null;

        try {
            $payload = array_filter([
                'file' => $file,
                'youtube_url' => $input['youtube_url'] ?? null,
                'alt_text' => $input['alt_text'],
                'sort_order' => $input['sort_order'] ?? null,
            ], fn (mixed $value) => $value !== null);

            $data = Validator::make($payload, ProductMediaUploader::rules(new HttpRequest(['type' => 'image'])))
                ->setAttributeNames(['file' => 'gambar dari URL'])
                ->validate();

            $media = ProductMediaUploader::store($product, $data);
        } finally {
            if ($file) {
                @unlink($file->getPathname());
            }
        }

        $this->audit($request, 'product.media_added', $product, ['media_id' => $media->id, 'type' => $media->type, 'path' => $media->path]);

        return Response::structured([
            'media_id' => $media->id,
            'product' => $this->productDetail($product->refresh()),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return self::productIdentifierSchema($schema) + [
            'url' => $schema->string()->description('URL https gambar (jpg, png, webp; maks. sesuai batas unggah). Isi ini ATAU youtube_url.'),
            'youtube_url' => $schema->string()->description('URL video YouTube. Isi ini ATAU url.'),
            'alt_text' => $schema->string()->required()->description('Teks alternatif, mis. "Palu kambing Wolf 16 oz gagang karet".'),
            'sort_order' => $schema->integer()->min(0)->description('Urutan tampil; 0 = foto utama. Kosong = paling akhir.'),
        ];
    }
}
