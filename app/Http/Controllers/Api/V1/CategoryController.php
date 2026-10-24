<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\ArticleResource;
use App\Http\Resources\Api\CategoryResource;
use App\Models\Article;
use App\Models\Category;
use App\Models\Product;
use App\Support\Api\HtmlSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** @tags Kategori */
class CategoryController extends ApiController
{
    private const MAX_DEPTH = 3;

    /**
     * Pohon kategori
     *
     * Kategori aktif tersusun sebagai pohon, maksimal tiga level.
     */
    public function index(Request $request): JsonResponse
    {
        $all = Category::active()->orderBy('sort_order')->orderBy('name')->get();
        $byParent = $all->groupBy(fn (Category $c) => $c->parent_id ?? 0);

        $build = function (int $parentId, int $depth) use (&$build, $byParent) {
            return ($byParent[$parentId] ?? collect())->map(function (Category $category) use ($build, $depth) {
                $category->setRelation('treeChildren', $depth < self::MAX_DEPTH ? $build($category->id, $depth + 1) : collect());

                return $category;
            })->values();
        };

        $response = response()->json(['data' => CategoryResource::collection($build(0, 1))->resolve($request)]);

        return $this->withLastModified($response, $all->max('updated_at'));
    }

    /**
     * Detail kategori
     *
     * Teks pengantar, konten pendukung, jejak kategori, subkategori, dan artikel terkait.
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        $category = Category::active()->where('slug', $slug)->with('parent')->firstOrFail();
        $category->setRelation('treeChildren', $category->children);

        $articles = $category->articles()->published()->with(['author', 'categories', 'tags'])
            ->latest('published_at')->take(3)->get();

        $data = array_merge(CategoryResource::make($category)->resolve($request), [
            'intro' => $category->intro,
            'bottom_content' => HtmlSanitizer::clean($category->bottom_content),
            'breadcrumbs' => collect($category->breadcrumbs())->map(fn (Category $c) => CategoryResource::summary($c))->all(),
            'product_count' => Product::published()->whereIn('category_id', $category->descendantIds())->count(),
            'articles' => $articles->map(fn (Article $a) => ArticleResource::summary($a))->all(),
            'updated_at' => ArticleResource::iso($category->updated_at),
        ]);

        return $this->withLastModified(response()->json(['data' => $data]), $category->updated_at);
    }
}
