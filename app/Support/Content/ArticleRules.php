<?php

namespace App\Support\Content;

use App\Models\Article;
use App\Support\Catalog\ProductRules;
use Illuminate\Validation\Rule;

/**
 * Aturan validasi artikel untuk REST API admin dan MCP, mengikuti batas formulir Filament:
 * slug unik, judul meta maks. 70 karakter, kategori/produk/penulis harus sudah ada.
 */
class ArticleRules
{
    public const STATUSES = ['draft', 'published'];

    /** @return array<string, mixed> */
    public static function article(?Article $article = null): array
    {
        $creating = $article === null;

        return [
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:'.ProductRules::SLUG_PATTERN, Rule::unique('articles', 'slug')->ignore($article?->id)],
            'summary' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'content' => [$creating ? 'required' : 'sometimes', 'string', 'max:500000'],
            'status' => ['sometimes', Rule::in(self::STATUSES)],
            'published_at' => ['sometimes', 'nullable', 'date'],
            'author' => ['sometimes', 'nullable', 'string', Rule::exists('authors', 'slug')],
            'schema_type' => ['sometimes', Rule::in(array_keys(Article::SCHEMA_TYPES))],
            'categories' => ['sometimes', 'array', 'max:20'],
            'categories.*' => ['string', 'distinct', Rule::exists('categories', 'slug')],
            'tags' => ['sometimes', 'array', 'max:30'],
            'tags.*' => ['string', 'max:100'],
            'products' => ['sometimes', 'array', 'max:30'],
            'products.*' => ['string', 'distinct', Rule::exists('products', 'slug')],
            'image_caption' => ['sometimes', 'nullable', 'string', 'max:255'],
            'image_credit' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_featured' => ['sometimes', 'boolean'],
            'meta_title' => ['sometimes', 'nullable', 'string', 'max:70'],
            'meta_description' => ['sometimes', 'nullable', 'string', 'max:320'],
            'canonical_url' => ['sometimes', 'nullable', 'url:http,https', 'max:255'],
            'noindex' => ['sometimes', 'boolean'],
            'nofollow' => ['sometimes', 'boolean'],
            'faqs' => ['sometimes', 'array', 'max:50'],
            'faqs.*.question' => ['required', 'string', 'max:255'],
            'faqs.*.answer' => ['required', 'string', 'max:5000'],
        ];
    }
}
