<?php

namespace App\Http\Controllers;

use App\Models\Author;

class AuthorController extends Controller
{
    public function show(string $slug)
    {
        $author = Author::active()->where('slug', $slug)->firstOrFail();
        $articles = $author->publishedArticles()->with('categories')->latest('published_at')->paginate(12);

        return view('author', [
            'author' => $author,
            'articles' => $articles,
        ]);
    }
}
