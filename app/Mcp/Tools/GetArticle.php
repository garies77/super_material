<?php

namespace App\Mcp\Tools;

use App\Http\Resources\Api\ArticleResource;
use App\Http\Resources\Api\AuthorResource;
use App\Http\Resources\Api\CategoryResource;
use App\Mcp\Concerns\ScopesArticles;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Isi lengkap satu artikel (termasuk draf): konten HTML, ringkasan, penulis, kategori, tag, produk tertaut, status tinjauan, dan SEO. Penulis hanya dapat membuka artikel miliknya.')]
#[IsReadOnly]
class GetArticle extends AbilityTool
{
    use ScopesArticles;

    protected string $ability = 'articles:read';

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'slug' => ['required_without:id', 'nullable', 'string'],
            'id' => ['required_without:slug', 'nullable', 'integer'],
        ]);

        $article = $this->visibleArticles($request->user())
            ->when($validated['id'] ?? null, fn ($q, int $id) => $q->whereKey($id), fn ($q) => $q->where('slug', $validated['slug']))
            ->with(['author', 'categories', 'tags', 'products:id,slug,name'])
            ->first();

        if (! $article) {
            return Response::error('Artikel tidak ditemukan. Cari dulu dengan search-articles.');
        }

        return Response::structured([
            'id' => $article->id,
            'slug' => $article->slug,
            'title' => $article->title,
            'status' => $this->articleStatus($article),
            'review_status' => $article->review_status,
            'review_note' => $article->review_note,
            'summary' => $article->summary,
            'content' => $article->content,
            'reading_minutes' => $article->readingMinutes(),
            'author' => $article->author ? AuthorResource::summary($article->author) : null,
            'categories' => $article->categories->map(fn (Category $c) => CategoryResource::summary($c))->values()->all(),
            'tags' => $article->tags->map(fn (Tag $t) => ['slug' => $t->slug, 'name' => $t->name])->values()->all(),
            'products' => $article->products->map(fn (Product $p) => ['slug' => $p->slug, 'name' => $p->name])->values()->all(),
            'meta_title' => $article->meta_title,
            'meta_description' => $article->meta_description,
            'is_featured' => (bool) $article->is_featured,
            'views' => (int) $article->views_count,
            'published_at' => ArticleResource::iso($article->published_at),
            'updated_at' => ArticleResource::iso($article->updated_at),
            'public_url' => $article->url(),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'slug' => $schema->string()->description('Slug artikel, mis. dari search-articles.'),
            'id' => $schema->integer()->description('ID artikel; dipakai bila slug kosong.'),
        ];
    }
}
