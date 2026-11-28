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

#[Description('Membuat merek baru. Cek list-brands dulu agar tidak dobel.')]
#[IsDestructive(false)]
class CreateBrand extends AbilityTool
{
    use WritesTaxonomy;

    protected string $ability = 'taxonomy:write';

    public function handle(Request $request): Response|ResponseFactory
    {
        $data = $this->validateTaxonomy(Arr::except($request->all(), ['logo_url']), TaxonomyRules::brand());
        if (filled($request->get('logo_url'))) {
            $data['logo'] = RemoteImage::store((string) $request->get('logo_url'), 'logo_url', 'brands', $data['slug'] ?? $data['name'], minPixels: 10000);
        }

        $brand = new Brand;
        TaxonomyWriter::saveBrand($brand, $data);

        $this->auditTaxonomy($request, 'brand.created', $brand, ['fields' => array_keys($data)]);

        return Response::structured($this->brandDetail($brand->refresh()));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        $fields = self::brandFieldsSchema($schema);
        $fields['name'] = $fields['name']->required();

        return $fields;
    }
}
