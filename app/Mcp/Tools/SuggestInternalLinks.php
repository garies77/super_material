<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ScopesArticles;
use App\Models\Article;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Saran tautan internal untuk satu artikel: produk terbit yang relevan tetapi belum ditautkan, dan artikel terbit lain yang bisa disebut di isi. Skor berasal dari kategori yang sama, tag yang sama, dan kata yang cocok di judul/ringkasan. Untuk menautkan produk, kirim daftar products LENGKAP (lama + baru) ke update-article.')]
#[IsReadOnly]
class SuggestInternalLinks extends AbilityTool
{
    use ScopesArticles;

    /** Kata umum yang tidak membedakan topik. */
    private const STOPWORDS = [
        'dan', 'yang', 'di', 'ke', 'dari', 'untuk', 'dengan', 'cara', 'agar', 'tidak', 'ini', 'itu', 'atau', 'pada',
        'dalam', 'adalah', 'jadi', 'bisa', 'lebih', 'tips', 'panduan', 'jual', 'murah', 'harga', 'medan', 'jaya', 'intero',
    ];

    protected string $ability = 'articles:read';

    public function handle(Request $request): Response|ResponseFactory
    {
        $input = $this->validated($request, [
            'article' => ['required', 'string'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ]);
        $key = $input['article'];
        $limit = $input['limit'] ?? 5;

        $article = $this->visibleArticles($request->user())->with(['categories', 'tags', 'products'])
            ->where(fn ($q) => ctype_digit($key) ? $q->whereKey((int) $key) : $q->where('slug', $key))
            ->first();
        if (! $article) {
            return Response::error('Artikel tidak ditemukan. Cari dulu dengan search-articles.');
        }

        $words = self::words($article->title.' '.$article->summary);
        $categoryIds = $article->categories->flatMap(fn (Category $category) => $category->descendantIds())->unique()->all();
        $tagIds = $article->tags->modelKeys();

        $products = Product::published()->whereKeyNot($article->products->modelKeys())->get(['id', 'name', 'slug', 'category_id'])
            ->map(function (Product $product) use ($words, $categoryIds) {
                $shared = array_values(array_intersect($words, self::words($product->name)));
                $sameCategory = in_array($product->category_id, $categoryIds, true);

                return [
                    'slug' => $product->slug,
                    'name' => $product->name,
                    'url' => $product->url(),
                    'score' => ($sameCategory ? 3 : 0) + count($shared),
                    'reason' => trim(($sameCategory ? 'kategori sama; ' : '').($shared ? 'kata cocok: '.implode(', ', $shared) : ''), '; '),
                ];
            });

        $articles = Article::published()->whereKeyNot($article->getKey())->with(['categories:id', 'tags:id'])->get(['id', 'title', 'slug', 'summary'])
            ->map(function (Article $other) use ($words, $categoryIds, $tagIds) {
                $shared = array_values(array_intersect($words, self::words($other->title.' '.$other->summary)));
                $sharedCategories = count(array_intersect($other->categories->modelKeys(), $categoryIds));
                $sharedTags = count(array_intersect($other->tags->modelKeys(), $tagIds));

                return [
                    'slug' => $other->slug,
                    'title' => $other->title,
                    'url' => $other->url(),
                    'score' => 2 * $sharedCategories + 2 * $sharedTags + count($shared),
                    'reason' => collect([
                        $sharedCategories ? 'kategori sama' : null,
                        $sharedTags ? 'tag sama' : null,
                        $shared ? 'kata cocok: '.implode(', ', $shared) : null,
                    ])->filter()->implode('; '),
                ];
            });

        $top = fn ($rows) => $rows->where('score', '>', 0)->sortByDesc('score')->take($limit)->values()->all();

        return Response::structured([
            'article' => $article->slug,
            'linked_products' => $article->products->pluck('slug')->all(),
            'products' => $top($products),
            'articles' => $top($articles),
        ]);
    }

    /** @return list<string> */
    private static function words(?string $text): array
    {
        $words = explode(' ', Str::of(strip_tags((string) $text))->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->value());

        return array_values(array_unique(array_filter($words, fn (string $word) => mb_strlen($word) >= 3 && ! in_array($word, self::STOPWORDS, true))));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'article' => $schema->string()->required()->description('ID (angka) atau slug artikel.'),
            'limit' => $schema->integer()->min(1)->max(20)->description('Jumlah saran per jenis, bawaan 5.'),
        ];
    }
}
