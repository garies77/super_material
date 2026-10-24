<?php

namespace App\Http\Resources\Api;

use App\Models\Article;
use App\Models\Author;
use Illuminate\Http\Request;

/** @mixin Author */
class AuthorResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        /** @var Author $author */
        $author = $this->resource;

        return [
            'slug' => $author->slug,
            'name' => $author->name,
            'display_name' => $author->displayName(),
            'job_title' => $author->job_title,
            'bio' => $author->bio,
            'avatar_url' => $author->avatarUrl(),
            'same_as' => array_values(array_filter((array) $author->same_as)),
            'url' => $author->url(),
            'articles' => $this->when($author->relationLoaded('publishedArticles'), fn () => $author->publishedArticles->map(fn (Article $a) => ArticleResource::summary($a))->values()->all()),
        ];
    }

    /** @return array{slug: string, name: string, display_name: string} */
    public static function summary(Author $author): array
    {
        return ['slug' => $author->slug, 'name' => $author->name, 'display_name' => $author->displayName()];
    }
}
