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
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Description('Mengubah kategori produk. Hanya kolom yang dikirim yang berubah. Mengganti slug membuat redirect 301 dari URL lama. is_active=false menyembunyikan kategori beserta halamannya; respons memberi peringatan bila kategori masih berisi produk.')]
#[IsDestructive]
#[IsIdempotent]
class UpdateCategory extends AbilityTool
{
    use WritesTaxonomy;

    protected string $ability = 'taxonomy:write';

    public function handle(Request $request): Response|ResponseFactory
    {
        /** @var Category|null $category */
        $category = $this->findBySlugOrId($request, 'category', Category::class);
        if (! $category) {
            return Response::error('Kategori tidak ditemukan. Lihat list-categories, lalu kirim ID atau slug-nya di argumen "category".');
        }

        $input = Arr::except($request->all(), ['category', 'image_url']);
        if ($input === [] && blank($request->get('image_url'))) {
            return Response::error('Tidak ada kolom yang dikirim untuk diubah.');
        }

        $data = $this->validateTaxonomy($input, TaxonomyRules::category($category), $category, isCategory: true);
        if (filled($request->get('image_url'))) {
            $data['image'] = RemoteImage::store((string) $request->get('image_url'), 'image_url', 'categories', $category->slug, minPixels: 10000);
        }

        $changes = TaxonomyWriter::saveCategory($category, $data);
        if ($changes) {
            $this->auditTaxonomy($request, 'category.updated', $category, $changes);
        }

        $warnings = [];
        if (array_key_exists('is_active', $changes) && ! $category->is_active && ($count = $category->products()->count()) > 0) {
            $warnings[] = "Kategori dinonaktifkan padahal masih berisi {$count} produk; halaman kategorinya tidak tampil lagi di situs.";
        }

        return Response::structured([
            'changed' => array_keys($changes),
            'category' => $this->categoryDetail($category->refresh()),
        ] + ($warnings ? ['warnings' => $warnings] : []));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'category' => $schema->string()->required()->description('ID (angka) atau slug kategori yang diubah, dari list-categories.'),
        ] + self::categoryFieldsSchema($schema);
    }
}
