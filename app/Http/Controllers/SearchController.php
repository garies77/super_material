<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Product;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';

        $products = $articles = collect();
        if (mb_strlen($q) >= 2) {
            $products = Product::published()->with(['category', 'brand'])
                ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('summary', 'like', $like)
                    ->orWhereHas('brand', fn ($b) => $b->where('name', 'like', $like))
                    ->orWhereHas('category', fn ($c) => $c->where('name', 'like', $like)))
                ->orderBy('name')->take(24)->get();
            $articles = Article::published()
                ->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('summary', 'like', $like))
                ->latest('published_at')->take(12)->get();
        }

        return view('search', compact('q', 'products', 'articles'));
    }
}
