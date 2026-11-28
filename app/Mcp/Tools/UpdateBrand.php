<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\WritesTaxonomy;
use App\Models\Brand;
use App\Support\Catalog\TaxonomyRules;
use App\Support\Catalog\TaxonomyWriter;
use App\Support\RemoteImage;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Description('Mengubah nama, slug, atau logo merek. Hanya kolom yang dikirim yang berubah.')]
#[IsDestructive]
#[IsIdempotent]
class UpdateBrand extends AbilityTool
{
    use WritesTaxonomy;

    protected string $ability = 'taxonomy:write';

    public function handle(Request $request): Response|ResponseFactory
    {
        /** @var Brand|null $brand */
        $brand = $this->findBySlugOrId($request, 'brand', Brand::class);
        if (! $brand) {
            return Response::error('Merek tidak ditemukan. Lihat list-brands, lalu kirim ID atau slug-nya di argumen "brand".');
        }

        $input = Arr::except($request->all(), ['brand', 'logo_url']);
        if ($input === [] && blank($request->get('logo_url'))) {
            return Response::error('Tidak ada kolom yang dikirim untuk diubah.');
        }

        $data = $this->validateTaxonomy($input, TaxonomyRules::brand($brand));
        if (filled($request->get('logo_url'))) {
            $data['logo'] = RemoteImage::store((string) $request->get('logo_url'), 'logo_url', 'brands', $brand->slug, minPixels: 10000);
        }

        $changes = TaxonomyWriter::saveBrand($brand, $data);
        if ($changes) {
            $this->auditTaxonomy($request, 'brand.updated', $brand, $changes);
        }

        return Response::structured([
            'changed' => array_keys($changes),
            'brand' => $this->brandDetail($brand->refresh()),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'brand' => $schema->string()->required()->description('ID (angka) atau slug merek yang diubah, dari list-brands.'),
        ] + self::brandFieldsSchema($schema);
    }
}
