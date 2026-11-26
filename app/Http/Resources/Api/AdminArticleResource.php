<?php

namespace App\Http\Resources\Api;

use App\Models\Article;
use App\Models\Faq;
use App\Models\Product;
use Illuminate\Http\Request;

/**
 * Artikel untuk REST API admin: semua status (draf, terjadwal, terbit) beserta status tinjauan dan SEO.
 * Isi HTML dikirim apa adanya (bukan versi publik dengan daftar isi) agar bisa disunting ulang.
 *
 * @mixin Article
 */
class AdminArticleResource extends ArticleResource
{
    public function toArray(Request $request): array
    {
        /** @var Article $article */
        $article = $this->resource;
        $detail = $this->included($request, 'detail');

        return array_merge(parent::toArray($request), [
            'status' => match (true) {
                $article->published_at === null => 'draft',
                $article->published_at->isFuture() => 'scheduled',
                default => 'published',
            },
            'review_status' => $article->review_status,
            'review_note' => $article->review_note,
            'is_featured' => (bool) $article->is_featured,
            'views' => (int) $article->views_count,
            'public_url' => $article->url(),
            'updated_at' => self::iso($article->updated_at),
            'schema_type' => $this->when($detail, fn () => $article->schemaType()),
            'content' => $this->when($detail, fn () => $article->content),
            'meta_title' => $this->when($detail, fn () => $article->meta_title),
            'meta_description' => $this->when($detail, fn () => $article->meta_description),
            'noindex' => $this->when($detail, fn () => (bool) $article->noindex),
            'nofollow' => $this->when($detail, fn () => (bool) $article->nofollow),
            'faqs' => $this->when($detail, fn () => $article->faqs->map(fn (Faq $f) => ['question' => $f->question, 'answer' => $f->answer])->values()->all()),
            'products' => $this->when($detail, fn () => $article->products->map(fn (Product $p) => ['slug' => $p->slug, 'name' => $p->name])->values()->all()),
        ]);
    }
}
