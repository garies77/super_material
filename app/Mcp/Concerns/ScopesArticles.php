<?php

namespace App\Mcp\Concerns;

use App\Models\Article;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** Artikel yang boleh dibaca pengguna token: Penulis hanya artikel miliknya, sama dengan panel admin. */
trait ScopesArticles
{
    /** @return Builder<Article> */
    protected function visibleArticles(?User $user): Builder
    {
        $query = Article::query();

        return $user?->writesAsOwnAuthor()
            ? $query->whereHas('author', fn ($q) => $q->where('user_id', $user->id))
            : $query;
    }

    protected function articleStatus(Article $article): string
    {
        return match (true) {
            $article->published_at === null => 'draft',
            $article->published_at->isFuture() => 'scheduled',
            default => 'published',
        };
    }
}
