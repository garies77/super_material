<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\WritesTaxonomy;
use App\Models\Tag;
use App\Support\Catalog\TaxonomyWriter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Description('Menggabungkan tag dobel: semua artikel dan produk bertag "source" dipindah ke tag "target", tag source DIHAPUS, dan URL tag lama dialihkan 301 ke tag target. Tidak bisa dibatalkan otomatis; tampilkan kedua tag (list-tags) dan minta persetujuan pengguna dulu.')]
#[IsDestructive]
class MergeTags extends AbilityTool
{
    use WritesTaxonomy;

    protected string $ability = 'taxonomy:write';

    public function handle(Request $request): Response|ResponseFactory
    {
        /** @var Tag|null $source */
        $source = $this->findBySlugOrId($request, 'source', Tag::class);
        /** @var Tag|null $target */
        $target = $this->findBySlugOrId($request, 'target', Tag::class);

        if (! $source || ! $target) {
            return Response::error('Tag source atau target tidak ditemukan. Lihat slug-nya lewat list-tags.');
        }
        if ($source->is($target)) {
            return Response::error('Tag source dan target sama; pilih dua tag yang berbeda.');
        }

        $moved = TaxonomyWriter::mergeTags($source, $target);

        $this->auditTaxonomy($request, 'tag.merged', $target, [
            'source' => ['from' => $source->slug, 'to' => null],
            'moved' => ['from' => null, 'to' => $moved],
        ]);

        $target->loadCount(['articles', 'products']);

        return Response::structured([
            'moved' => $moved,
            'removed_tag' => $source->slug,
            'tag' => [
                'slug' => $target->slug,
                'name' => $target->name,
                'articles' => $target->articles_count,
                'products' => $target->products_count,
                'url' => $target->url(),
            ],
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'source' => $schema->string()->required()->description('Slug atau ID tag yang akan dihapus setelah digabung.'),
            'target' => $schema->string()->required()->description('Slug atau ID tag yang dipertahankan.'),
        ];
    }
}
