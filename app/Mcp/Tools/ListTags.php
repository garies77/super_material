<?php

namespace App\Mcp\Tools;

use App\Models\Tag;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Daftar tag artikel beserta jumlah artikel (semua status) dan produk yang memakainya. Pakai untuk memilih tag yang sudah ada saat menulis artikel, atau menemukan tag dobel (mis. "tips tukang" dan "tips-tukang") untuk digabung dengan merge-tags.')]
#[IsReadOnly]
class ListTags extends AbilityTool
{
    protected string $ability = 'articles:read';

    public function handle(Request $request): ResponseFactory
    {
        $input = $this->validated($request, ['query' => ['sometimes', 'string', 'max:100']]);

        $tags = Tag::query()
            ->withCount(['articles', 'products'])
            ->when($input['query'] ?? null, fn ($q, string $query) => $q->where('name', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $query).'%'))
            ->orderBy('name')
            ->get();

        return Response::structured([
            'tags' => $tags->map(fn (Tag $tag) => [
                'slug' => $tag->slug,
                'name' => $tag->name,
                'articles' => $tag->articles_count,
                'products' => $tag->products_count,
                'url' => $tag->url(),
            ])->values()->all(),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Potongan nama tag (opsional).'),
        ];
    }
}
