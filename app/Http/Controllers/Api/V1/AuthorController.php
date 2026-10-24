<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\AuthorResource;
use App\Models\Author;
use Illuminate\Http\JsonResponse;

/** @tags Artikel */
class AuthorController extends ApiController
{
    /**
     * Profil penulis
     *
     * Profil penulis aktif beserta 20 artikel terbit terbaru.
     */
    public function show(string $slug): JsonResponse
    {
        $author = Author::active()->where('slug', $slug)
            ->with(['publishedArticles' => fn ($q) => $q->latest('published_at')->take(20)])
            ->firstOrFail();

        return $this->withLastModified(AuthorResource::make($author)->response(), $author->updated_at);
    }
}
