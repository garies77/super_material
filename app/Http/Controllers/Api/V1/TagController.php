<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\TagResource;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** @tags Artikel */
class TagController extends ApiController
{
    /**
     * Daftar tag
     *
     * Tag yang dipakai artikel terbit, beserta jumlah artikelnya.
     */
    public function index(Request $request): JsonResponse
    {
        $tags = Tag::withCount(['articles as published_articles_count' => fn ($q) => $q->published()])
            ->whereHas('articles', fn ($q) => $q->published())
            ->orderBy('name')->get();

        return response()->json(['data' => TagResource::collection($tags)->resolve($request)]);
    }
}
