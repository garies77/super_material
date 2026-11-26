<?php

namespace App\Mcp\Concerns;

use App\Http\Resources\Api\AdminArticleResource;
use App\Models\Article;
use App\Models\AuditLog;
use App\Support\Content\ArticleRules;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

/**
 * Bersama untuk tool MCP yang menulis artikel: validasi dan penyimpanan memakai ArticleRules dan ArticleWriter
 * yang sama dengan REST API admin, dan setiap perubahan dicatat di AuditLog dengan sumber "mcp".
 */
trait WritesArticles
{
    use ScopesArticles;

    /** Artikel dari argumen "article" (ID atau slug), dibatasi artikel yang boleh dilihat pengguna token. */
    protected function findArticle(Request $request): ?Article
    {
        $key = trim((string) $request->get('article'));
        if ($key === '') {
            return null;
        }

        return $this->visibleArticles($request->user())
            ->where(fn ($q) => ctype_digit($key) ? $q->whereKey((int) $key) : $q->where('slug', $key))
            ->first();
    }

    protected function articleNotFound(): Response
    {
        return Response::error('Artikel tidak ditemukan. Cari dulu dengan search-articles, lalu kirim ID atau slug-nya di argumen "article".');
    }

    /** Detail artikel setelah perubahan, bentuknya sama dengan GET /api/v1/admin/articles/{article}. */
    protected function articleDetail(Article $article): array
    {
        $resourceRequest = new HttpRequest;
        $resourceRequest->attributes->set('api_includes', ['detail']);

        return AdminArticleResource::make($article->loadMissing(['author', 'categories', 'tags', 'faqs', 'products']))
            ->resolve($resourceRequest);
    }

    /** @param  array<string, mixed>  $changes */
    protected function auditArticle(Request $request, string $action, Article $article, array $changes): void
    {
        AuditLog::record(request(), $action, $article, $changes, actor: $request->user(), source: 'mcp');
    }

    /**
     * Kolom artikel yang boleh dikirim; semuanya opsional (judul & isi wajib saat membuat).
     *
     * @return array<string, Type>
     */
    protected static function articleFieldsSchema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('Judul artikel.'),
            'slug' => $schema->string()->description('Slug URL (huruf kecil, angka, tanda hubung). Kosong = dibuat dari judul. Mengubah slug artikel lama membuat redirect 301 otomatis.'),
            'summary' => $schema->string()->description('Ringkasan 1-2 kalimat; dipakai di kartu artikel dan sebagai deskripsi meta cadangan.'),
            'content' => $schema->string()->description('Isi artikel dalam HTML. Pakai <h2>/<h3> untuk subjudul (daftar isi otomatis), <p>, <ul>/<ol>, <table>, <a>. Skrip, iframe, dan atribut on* dibuang saat disimpan.'),
            'status' => $schema->string()->enum(ArticleRules::STATUSES)->description('draft = belum tampil; published = terbit (atau terjadwal bila published_at di masa depan).'),
            'published_at' => $schema->string()->description('Waktu terbit ISO 8601, mis. "2026-12-01T08:00:00+07:00". Dipakai bersama status="published" untuk menjadwalkan.'),
            'author' => $schema->string()->description('Slug profil penulis. Kosong = profil pemilik token. Diabaikan untuk peran Penulis.'),
            'schema_type' => $schema->string()->enum(array_keys(Article::SCHEMA_TYPES))->description('Jenis artikel untuk Google; bawaan BlogPosting.'),
            'categories' => $schema->array()->items($schema->string())->description('Slug kategori dari list-categories; mengganti seluruh kategori lama.'),
            'tags' => $schema->array()->items($schema->string())->description('Nama tag; tag yang belum ada dibuat otomatis. Mengganti seluruh tag lama.'),
            'products' => $schema->array()->items($schema->string())->description('Slug produk yang dibahas (dari search-products); mengganti daftar lama.'),
            'image_caption' => $schema->string()->description('Keterangan foto utama. Foto utama sendiri diunggah lewat panel admin.'),
            'image_credit' => $schema->string(),
            'is_featured' => $schema->boolean()->description('Tampilkan sebagai artikel unggulan.'),
            'meta_title' => $schema->string()->description('Judul SEO, maks. 70 karakter.'),
            'meta_description' => $schema->string()->description('Deskripsi SEO, maks. 320 karakter (ideal 140-160).'),
            'canonical_url' => $schema->string(),
            'noindex' => $schema->boolean(),
            'nofollow' => $schema->boolean(),
            'faqs' => $schema->array()->items($schema->object([
                'question' => $schema->string()->required(),
                'answer' => $schema->string()->required(),
            ]))->description('Daftar FAQ; menggantikan seluruh FAQ lama.'),
        ];
    }
}
