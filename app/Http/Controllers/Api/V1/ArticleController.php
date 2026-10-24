<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\ApiCollection;
use App\Http\Resources\Api\ArticleDetailResource;
use App\Http\Resources\Api\ArticleResource;
use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** @tags Artikel */
class ArticleController extends ApiController
{
    /**
     * Daftar artikel
     *
     * Artikel terbit dengan filter[category], filter[tag], filter[author], urutan
     * (sort=published_at atau views; awali - untuk menurun), dan paginasi cursor.
     *
     * @queryParam filter[category] string Slug kategori.
     * @queryParam filter[tag] string Slug tag.
     * @queryParam filter[author] string Slug penulis.
     * @queryParam sort string Bawaan -published_at.
     * @queryParam fields string Kolom yang dikirim.
     * @queryParam per_page int Bawaan 20, maksimal 50.
     */
    public function index(Request $request): ApiCollection
    {
        $query = Article::published()->with(['author', 'categories', 'tags']);
        self::applyFilters($query, $this->filters($request));
        $this->applySort($query, $request, ['published_at' => 'published_at', 'views' => 'views_count'], '-published_at');

        return $this->paginate($query, $request, ArticleResource::class);
    }

    /**
     * Detail artikel
     *
     * Isi HTML tersanitasi, daftar isi, penulis, tag, FAQ, produk tertaut, dan artikel terkait.
     */
    public function show(string $slug): JsonResponse
    {
        $article = Article::published()->where('slug', $slug)->with([
            'author', 'categories', 'tags', 'faqs',
            'products' => fn ($q) => $q->published()->with(['media', 'variants']),
            'relatedArticles' => fn ($q) => $q->published(),
        ])->firstOrFail();

        return $this->withLastModified(
            ArticleDetailResource::make($article)->response(),
            $article->modifiedAt(),
        );
    }

    public static function applyFilters(Builder $query, array $filters): void
    {
        if (isset($filters['category'])) {
            $category = Category::where('slug', $filters['category'])->first();
            $query->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $category ? $category->descendantIds() : [0]));
        }

        if (isset($filters['tag'])) {
            $tagId = Tag::where('slug', $filters['tag'])->value('id') ?? 0;
            $query->whereHas('tags', fn ($q) => $q->where('tags.id', $tagId));
        }

        if (isset($filters['author'])) {
            $query->where('author_id', Author::where('slug', $filters['author'])->value('id') ?? 0);
        }
    }
}
