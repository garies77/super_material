<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\ArticleResource;
use App\Http\Resources\Api\ProductResource;
use App\Models\Article;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** @tags Pencarian */
class SearchController extends ApiController
{
    /**
     * Pencarian produk dan artikel
     *
     * Logika sama dengan halaman /cari: nama, ringkasan, merek, dan kategori produk; judul dan ringkasan artikel.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            /** Kata kunci, minimal 2 karakter. */
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $q = trim($validated['q']);
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';

        $products = Product::published()->with(['category', 'brand', 'variants'])
            ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('summary', 'like', $like)
                ->orWhereHas('brand', fn ($b) => $b->where('name', 'like', $like))
                ->orWhereHas('category', fn ($c) => $c->where('name', 'like', $like)))
            ->orderBy('name')->take(24)->get();

        $articles = Article::published()->with(['author', 'categories', 'tags'])
            ->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('summary', 'like', $like))
            ->latest('published_at')->take(12)->get();

        $request->attributes->set('api_includes', ['category', 'brand']);

        return response()->json([
            'data' => [
                'query' => $q,
                'products' => ProductResource::collection($products)->resolve($request),
                'articles' => ArticleResource::collection($articles)->resolve($request),
            ],
        ]);
    }
}
