<?php

namespace App\Http\Controllers;

use App\Models\PageViewStat;
use App\Models\Product;
use App\Support\Reports\TrafficRecorder;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function show(Request $request, string $slug)
    {
        $product = Product::published()->where('slug', $slug)
            ->with([
                'category.parent.parent',
                'brand',
                'media',
                'variants' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
                'faqs',
                'links.channel',
            ])->firstOrFail();

        TrafficRecorder::view($request, PageViewStat::PRODUCT, $product->id);

        $related = Product::published()
            ->where('id', '!=', $product->id)
            ->when($product->category_id, fn ($q) => $q->where('category_id', $product->category_id))
            ->with(['category', 'media', 'variants'])
            ->inRandomOrder()
            ->take(6)
            ->get();

        return view('product', [
            'product' => $product,
            'channels' => $product->buyChannels(),
            'related' => $related,
            'articles' => $product->articles()->published()->latest('published_at')->take(3)->get(),
        ]);
    }
}
