<?php

namespace App\Mcp\Tools;

use App\Http\Resources\Api\ArticleResource;
use App\Http\Resources\Api\AuthorResource;
use App\Http\Resources\Api\CategoryResource;
use App\Mcp\Concerns\ScopesArticles;
use App\Models\Article;
use App\Models\Category;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Cari artikel blog (termasuk draf dan terjadwal) berdasarkan judul, status, atau kategori. Penulis hanya melihat artikel miliknya. Pakai get-article untuk isi lengkap.')]
#[IsReadOnly]
class SearchArticles extends AbilityTool
{
    use ScopesArticles;

    protected string $ability = 'articles:read';

    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'query' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:published,scheduled,draft'],
            'category' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', 'between:1,50'],
        ]);

        $query = $this->visibleArticles($request->user())->with(['author', 'categories']);

        if (filled($validated['query'] ?? null)) {
            $query->where('title', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $validated['query']).'%');
        }

        match ($validated['status'] ?? null) {
            'published' => $query->published(),
            'scheduled' => $query->where('published_at', '>', now()),
            'draft' => $query->whereNull('published_at'),
            default => null,
        };

        if (isset($validated['category'])) {
            $category = Category::where('slug', $validated['category'])->first();
            $query->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $category ? $category->descendantIds() : [0]));
        }

        $total = (clone $query)->count();
        $articles = $query->latest('updated_at')->orderByDesc('id')->limit($validated['limit'] ?? 20)->get();

        return Response::structured([
            'total' => $total,
            'articles' => $articles->map(fn (Article $article) => [
                'id' => $article->id,
                'slug' => $article->slug,
                'title' => $article->title,
                'status' => $this->articleStatus($article),
                'review_status' => $article->review_status,
                'summary' => $article->summary,
                'author' => $article->author ? AuthorResource::summary($article->author) : null,
                'categories' => $article->categories->map(fn (Category $c) => CategoryResource::summary($c))->values()->all(),
                'views' => (int) $article->views_count,
                'published_at' => ArticleResource::iso($article->published_at),
                'updated_at' => ArticleResource::iso($article->updated_at),
                'public_url' => $article->url(),
            ])->values()->all(),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Potongan judul artikel.'),
            'status' => $schema->string()->enum(['published', 'scheduled', 'draft'])->description('Kosongkan untuk semua status.'),
            'category' => $schema->string()->description('Slug kategori (termasuk subkategorinya).'),
            'limit' => $schema->integer()->min(1)->max(50)->default(20)->description('Jumlah artikel, 1–50.'),
        ];
    }
}
