<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

/**
 * Katalog produk (/produk) dan halaman kategori (/kategori/{slug}).
 * Filter dijalankan di database; parameter filter dipertahankan saat paginasi.
 */
class CatalogController extends Controller
{
    public const SORTS = [
        'relevansi' => 'Relevansi',
        'terbaru' => 'Terbaru',
        'termurah' => 'Harga terendah',
        'termahal' => 'Harga tertinggi',
    ];

    public function index(Request $request)
    {
        return $this->render($request, null);
    }

    public function category(Request $request, string $slug)
    {
        $category = Category::active()->where('slug', $slug)->with('parent.parent')->firstOrFail();

        return $this->render($request, $category);
    }

    private function render(Request $request, ?Category $category)
    {
        $categoryIds = $category?->descendantIds();
        $inScope = fn ($q) => $q->published()->when($categoryIds, fn ($q) => $q->whereIn('category_id', $categoryIds));

        $brandIds = collect((array) $request->query('brand'))
            ->filter(fn ($v) => is_scalar($v))->map(fn ($v) => (int) $v)->filter()->unique()->values()->all();
        $min = $request->integer('min') ?: null;
        $max = $request->integer('max') ?: null;
        if ($min && $max && $min > $max) {
            [$min, $max] = [$max, $min];
        }
        $sortParam = $request->query('sort');
        $sort = is_string($sortParam) && array_key_exists($sortParam, self::SORTS) ? $sortParam : 'relevansi';

        $query = $inScope(Product::query())->with(['category', 'brand'])
            ->when($brandIds, fn ($q) => $q->whereIn('brand_id', $brandIds))
            // Produk dengan rentang harga yang beririsan dengan filter
            ->when($min, fn ($q) => $q->whereRaw('coalesce(price_max, price_min) >= ?', [$min]))
            ->when($max, fn ($q) => $q->where('price_min', '<=', $max));

        match ($sort) {
            'terbaru' => $query->latest(),
            'termurah' => $query->orderByRaw('price_min is null')->orderBy('price_min'),
            'termahal' => $query->orderByRaw('price_min is null')->orderByDesc('price_min'),
            default => $query->orderByDesc('is_featured')->latest(),
        };

        $tree = Category::active()->orderBy('sort_order')->orderBy('name')->get()
            ->groupBy(fn (Category $c) => $c->parent_id ?? 0);

        return view('catalog', [
            'category' => $category,
            'products' => $query->paginate(12)->withQueryString(),
            'brands' => Brand::whereHas('products', $inScope)->orderBy('name')->get(),
            'tree' => $tree,
            'activeIds' => $category ? collect($category->breadcrumbs())->pluck('id')->all() : [],
            'children' => $category?->children ?? collect(),
            'articles' => $category ? $category->articles()->published()->latest('published_at')->take(3)->get() : collect(),
            'brandIds' => $brandIds,
            'min' => $min,
            'max' => $max,
            'sort' => $sort,
            'sorts' => self::SORTS,
            'filtered' => $brandIds || $min || $max || $sort !== 'relevansi',
        ]);
    }
}
