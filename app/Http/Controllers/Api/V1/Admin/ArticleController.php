<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Resources\Api\AdminArticleResource;
use App\Http\Resources\Api\ApiCollection;
use App\Mcp\Concerns\ScopesArticles;
use App\Models\Article;
use App\Models\AuditLog;
use App\Support\Content\ArticleRules;
use App\Support\Content\ArticleWriter;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Pengelolaan artikel lewat token, mis. untuk membuat artikel dengan bantuan AI. Validasi mengikuti panel admin;
 * isi HTML disanitasi; Penulis hanya melihat dan mengubah artikel miliknya.
 *
 * @tags Admin: Artikel
 */
class ArticleController extends ApiController
{
    use ScopesArticles;

    protected bool $admin = true;

    private const RELATIONS = ['author', 'categories', 'tags', 'faqs', 'products'];

    /**
     * Daftar artikel semua status
     *
     * Ability: articles:read. Filter: filter[status]=draft|scheduled|published, filter[q] (judul).
     *
     * @queryParam filter[status] string draft, scheduled, atau published.
     * @queryParam filter[q] string Cari judul.
     * @queryParam sort string title, published_at, updated_at; bawaan -updated_at.
     * @queryParam per_page int Bawaan 20, maksimal 100.
     */
    public function index(Request $request): ApiCollection
    {
        $filters = $this->filters($request);
        $query = $this->visibleArticles($request->user())->with(['author', 'categories', 'tags']);

        match ($filters['status'] ?? null) {
            'draft' => $query->whereNull('published_at'),
            'scheduled' => $query->where('published_at', '>', now()),
            'published' => $query->published(),
            default => null,
        };
        if (isset($filters['q'])) {
            $query->where('title', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], (string) $filters['q']).'%');
        }

        $this->applySort($query, $request, [
            'title' => 'title',
            'published_at' => 'published_at',
            'updated_at' => 'updated_at',
        ], '-updated_at');

        return $this->paginate($query, $request, AdminArticleResource::class);
    }

    /**
     * Detail artikel (semua status)
     *
     * Ability: articles:read. {article} berupa ID atau slug.
     */
    public function show(Request $request, string $article): AdminArticleResource
    {
        return $this->present($request, $this->findArticle($request, $article));
    }

    /**
     * Membuat artikel
     *
     * Ability: articles:write. Dibuat sebagai draf kecuali status=published dikirim; published_at di masa depan
     * menjadwalkan terbit. Tag yang belum ada dibuat otomatis; kategori, produk, dan penulis memakai slug.
     * Mendukung header Idempotency-Key.
     */
    public function store(Request $request): JsonResponse
    {
        $data = Validator::validate($request->all(), ArticleRules::article());
        $article = ArticleWriter::create($data, $request->user());

        AuditLog::record($request, 'article.created', $article, ['fields' => array_keys($data)]);

        return $this->present($request, $article)->response()->setStatusCode(201);
    }

    /**
     * Mengubah artikel
     *
     * Ability: articles:write. Hanya kolom yang dikirim yang berubah; categories, tags, products, dan faqs
     * mengganti seluruh daftar lama. Perubahan slug membuat redirect 301 dari URL lama.
     */
    public function update(Request $request, string $article): AdminArticleResource
    {
        $model = $this->findArticle($request, $article);
        $data = Validator::validate($request->all(), ArticleRules::article($model));
        $changes = ArticleWriter::update($model, $data, $request->user());

        if ($changes) {
            AuditLog::record($request, 'article.updated', $model, $changes);
        }

        return $this->present($request, $model->refresh());
    }

    private function findArticle(Request $request, string $key): Article
    {
        $article = $this->visibleArticles($request->user())
            ->where(fn ($q) => ctype_digit($key) ? $q->whereKey((int) $key) : $q->where('slug', $key))
            ->first();

        return $article ?? throw (new ModelNotFoundException)->setModel(Article::class);
    }

    private function present(Request $request, Article $article): AdminArticleResource
    {
        $request->attributes->set('api_includes', ['detail']);

        return AdminArticleResource::make($article->loadMissing(self::RELATIONS));
    }
}
