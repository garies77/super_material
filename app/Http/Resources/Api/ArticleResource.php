<?php

namespace App\Http\Resources\Api;

use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Http\Request;

/** @mixin Article */
class ArticleResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        /** @var Article $article */
        $article = $this->resource;

        return [
            'id' => $article->id,
            'slug' => $article->slug,
            'title' => $article->title,
            'summary' => $article->summary,
            'image' => $article->image ? [
                'url' => $article->imageUrl(),
                'caption' => $article->image_caption,
                'credit' => $article->image_credit,
            ] : null,
            'author' => $article->author ? AuthorResource::summary($article->author) : null,
            'categories' => $article->categories->map(fn (Category $c) => CategoryResource::summary($c))->values()->all(),
            'tags' => $article->tags->map(fn (Tag $t) => ['slug' => $t->slug, 'name' => $t->name])->values()->all(),
            'reading_minutes' => $article->readingMinutes(),
            'published_at' => self::iso($article->published_at),
            'modified_at' => self::iso($article->modifiedAt()),
            'canonical_url' => filled($article->canonical_url) ? $article->canonical_url : $article->url(),
        ];
    }

    /** Ringkasan artikel untuk daftar terkait. */
    public static function summary(Article $article): array
    {
        return [
            'id' => $article->id,
            'slug' => $article->slug,
            'title' => $article->title,
            'summary' => $article->summary,
            'image_url' => $article->imageUrl(),
            'published_at' => self::iso($article->published_at),
            'canonical_url' => filled($article->canonical_url) ? $article->canonical_url : $article->url(),
        ];
    }
}
