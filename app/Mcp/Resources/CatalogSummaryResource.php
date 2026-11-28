<?php

namespace App\Mcp\Resources;

use App\Mcp\Concerns\RequiresAbility;
use App\Models\Article;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

#[Description('Ringkasan katalog untuk konteks awal: jumlah produk terbit/draf/arsip, produk per kategori dan merek, rentang harga acuan, dan jumlah artikel. Diperbarui paling lama tiap 10 menit; untuk data rinci pakai search-products dan audit-catalog.')]
#[Uri('supermaterial://katalog/ringkasan')]
#[MimeType('application/json')]
class CatalogSummaryResource extends Resource
{
    use RequiresAbility;

    public const CACHE_KEY = 'mcp:catalog-summary';

    /** @var list<string> */
    protected array $abilities = ['catalog:read'];

    public function handle(Request $request): Response
    {
        $summary = Cache::remember(self::CACHE_KEY, now()->addMinutes(10), fn () => [
            'site' => Setting::brandName(),
            'url' => url('/'),
            'products' => [
                'published' => Product::published()->count(),
                'draft' => Product::where('is_published', false)->count(),
                'archived' => Product::onlyTrashed()->count(),
                'price_min' => Product::published()->min('price_min'),
                'price_max' => Product::published()->max('price_max') ?? Product::published()->max('price_min'),
            ],
            'categories' => Category::query()->with('parent:id,slug')->withCount('products')->orderBy('name')->get()
                ->map(fn (Category $category) => [
                    'slug' => $category->slug,
                    'name' => $category->name,
                    'parent' => $category->parent?->slug,
                    'products' => $category->products_count,
                ])->all(),
            'brands' => Brand::query()->withCount('products')->orderBy('name')->get()
                ->map(fn (Brand $brand) => ['slug' => $brand->slug, 'name' => $brand->name, 'products' => $brand->products_count])
                ->all(),
            'articles' => [
                'published' => Article::published()->count(),
                'total' => Article::count(),
            ],
            'generated_at' => now()->toIso8601String(),
        ]);

        return Response::text(json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }
}
