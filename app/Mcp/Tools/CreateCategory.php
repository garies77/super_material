<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\WritesTaxonomy;
use App\Models\Category;
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

#[Description('Membuat kategori produk baru (langsung aktif kecuali is_active=false). Cek list-categories dulu agar tidak dobel. Kategori maksimal 3 level; isi parent dengan slug induk untuk subkategori.')]
#[IsDestructive(false)]
class CreateCategory extends AbilityTool
{
    use WritesTaxonomy;

    protected string $ability = 'taxonomy:write';

    public function handle(Request $request): Response|ResponseFactory
    {
        $data = $this->validateTaxonomy(Arr::except($request->all(), ['image_url']), TaxonomyRules::category(), isCategory: true);
        if (filled($request->get('image_url'))) {
            $data['image'] = RemoteImage::store((string) $request->get('image_url'), 'image_url', 'categories', $data['slug'] ?? $data['name'], minPixels: 10000);
        }

        $category = new Category(['is_active' => true, 'sort_order' => 0]);
        TaxonomyWriter::saveCategory($category, $data);

        $this->auditTaxonomy($request, 'category.created', $category, ['fields' => array_keys($data)]);

        return Response::structured($this->categoryDetail($category->refresh()));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        $fields = self::categoryFieldsSchema($schema);
        $fields['name'] = $fields['name']->required();

        return $fields;
    }
}
