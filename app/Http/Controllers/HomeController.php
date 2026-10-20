<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Models\Product;

class HomeController extends Controller
{
    public function __invoke()
    {
        return view('home', [
            'categories' => Category::active()->whereNull('parent_id')->withCount('products')->orderBy('sort_order')->orderBy('name')->get(),
            'featured' => Product::published()->with(['category', 'brand'])->orderByDesc('is_featured')->latest()->take(8)->get(),
            'articles' => Article::published()->latest('published_at')->take(3)->get(),
        ]);
    }
}
