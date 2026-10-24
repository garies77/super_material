<?php

namespace App\Http\Resources\Api;

use App\Models\Tag;
use Illuminate\Http\Request;

/** @mixin Tag */
class TagResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        /** @var Tag $tag */
        $tag = $this->resource;

        return [
            'slug' => $tag->slug,
            'name' => $tag->name,
            'description' => $tag->description,
            'article_count' => (int) ($tag->published_articles_count ?? 0),
            'url' => $tag->url(),
        ];
    }
}
