<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    public function index()
    {
        $featured = Article::published()->with(['author', 'categories'])->where('is_featured', true)->latest('published_at')->first();
        if (! $featured) {
            $featured = Article::published()->with(['author', 'categories'])->latest('published_at')->first();
        }

        $query = Article::published()->with(['author', 'categories', 'tags'])->latest('published_at');
        if ($featured) {
            $query->where('id', '!=', $featured->id);
        }

        $articles = $query->paginate(10);
        $popular = Article::popular(5)->get();
        $categories = Category::whereHas('articles')->orderBy('name')->get();

        return view('articles', [
            'featured' => $featured,
            'articles' => $articles,
            'popular' => $popular,
            'categories' => $categories,
            'activeCategory' => null,
            'activeTag' => null,
            'title' => 'Artikel & Panduan Bahan Bangunan',
        ]);
    }

    public function category(string $slug)
    {
        $category = Category::where('slug', $slug)->firstOrFail();

        $featured = Article::published()->whereHas('categories', fn ($q) => $q->where('categories.id', $category->id))
            ->with(['author', 'categories'])->where('is_featured', true)->latest('published_at')->first();

        if (! $featured) {
            $featured = Article::published()->whereHas('categories', fn ($q) => $q->where('categories.id', $category->id))
                ->with(['author', 'categories'])->latest('published_at')->first();
        }

        $query = Article::published()->whereHas('categories', fn ($q) => $q->where('categories.id', $category->id))
            ->with(['author', 'categories', 'tags'])->latest('published_at');

        if ($featured) {
            $query->where('id', '!=', $featured->id);
        }

        $articles = $query->paginate(10);
        $popular = Article::popular(5)->get();
        $categories = Category::whereHas('articles')->orderBy('name')->get();

        return view('articles', [
            'featured' => $featured,
            'articles' => $articles,
            'popular' => $popular,
            'categories' => $categories,
            'activeCategory' => $category,
            'activeTag' => null,
            'title' => 'Artikel ' . $category->name,
        ]);
    }

    public function tag(string $slug)
    {
        $tag = Tag::where('slug', $slug)->firstOrFail();

        $featured = Article::published()->whereHas('tags', fn ($q) => $q->where('tags.id', $tag->id))
            ->with(['author', 'categories'])->latest('published_at')->first();

        $query = Article::published()->whereHas('tags', fn ($q) => $q->where('tags.id', $tag->id))
            ->with(['author', 'categories', 'tags'])->latest('published_at');

        if ($featured) {
            $query->where('id', '!=', $featured->id);
        }

        $articles = $query->paginate(10);
        $popular = Article::popular(5)->get();
        $categories = Category::whereHas('articles')->orderBy('name')->get();

        return view('articles', [
            'featured' => $featured,
            'articles' => $articles,
            'popular' => $popular,
            'categories' => $categories,
            'activeCategory' => null,
            'activeTag' => $tag,
            'title' => 'Topik: #' . $tag->name,
        ]);
    }

    public function show(Request $request, string $slug)
    {
        $article = Article::published()->where('slug', $slug)
            ->with([
                'author',
                'categories',
                'tags',
                'products.category',
                'products.media',
                'products.links.channel',
                'relatedArticles.categories',
                'faqs',
            ])
            ->firstOrFail();

        // Increment view count dengan proteksi sesi per sesi pengunjung
        $sessionKey = 'viewed_article_' . $article->id;
        if (! $request->session()->has($sessionKey)) {
            $article->increment('views_count');
            $request->session()->put($sessionKey, now()->timestamp);
        }

        [$html, $toc] = $article->contentWithToc();

        // 1. Artikel Terkait: ambil eksplisit dari relatedArticles, atau fallback ke kategori/tag yang sama
        $related = $article->relatedArticles;
        if ($related->isEmpty() && $article->categories->isNotEmpty()) {
            $catIds = $article->categories->pluck('id');
            $related = Article::published()
                ->where('id', '!=', $article->id)
                ->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $catIds))
                ->latest('published_at')
                ->take(4)
                ->get();
        }

        // 2. Rekomendasi Untukmu: artikel terbaru & menarik dari kategori lain
        $recommendations = Article::published()
            ->where('id', '!=', $article->id)
            ->when($related->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $related->pluck('id')))
            ->latest('published_at')
            ->take(4)
            ->get();

        // 3. Terpopuler untuk sidebar (CNN Style ranking 1 - 5)
        $popular = Article::popular(5)->where('id', '!=', $article->id)->get();
        if ($popular->isEmpty()) {
            $popular = Article::published()->where('id', '!=', $article->id)->latest('published_at')->take(5)->get();
        }

        // 4. Produk Terkait yang ditautkan eksplisit
        $products = $article->products->where('is_published', true);

        return view('article', [
            'article' => $article,
            'html' => $html,
            'toc' => $toc,
            'related' => $related,
            'recommendations' => $recommendations,
            'popular' => $popular,
            'products' => $products,
        ]);
    }
}
