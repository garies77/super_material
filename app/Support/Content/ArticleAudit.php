<?php

namespace App\Support\Content;

use App\Models\Article;
use App\Models\Product;
use App\Support\SeoScore;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Audit kelengkapan artikel untuk asisten AI: foto utama, produk tertaut (dan yang masih draf), kategori,
 * meta, panjang isi, dan judul yang mirip artikel lain, ditambah skor SEO yang sama dengan panel admin.
 */
class ArticleAudit
{
    /** @var array<string, string> */
    public const ISSUES = [
        'no_image' => 'Tanpa foto utama',
        'no_products' => 'Tanpa produk tertaut',
        'draft_products' => 'Menautkan produk yang masih draf',
        'no_category' => 'Tanpa kategori',
        'no_meta_description' => 'Deskripsi meta kosong',
        'meta_title_too_long' => 'Judul meta lebih dari 60 karakter',
        'short_content' => 'Isi kurang dari 300 kata',
        'similar_title' => 'Judul mirip artikel lain',
    ];

    public const MIN_WORDS = 300;

    public const META_TITLE_MAX = 60;

    public const SIMILAR_TITLE_OVERLAP = 0.8;

    /**
     * @param  Collection<int, Article>  $articles  dengan relasi products, categories, tags dimuat
     * @param  callable(Article): string  $statusOf
     * @return list<array<string, mixed>>
     */
    public static function audit(Collection $articles, callable $statusOf): array
    {
        $similar = self::similarTitles($articles);

        return $articles->map(function (Article $article) use ($similar, $statusOf) {
            $draftProducts = $article->products->filter(fn (Product $product) => ! $product->is_published);
            $words = str_word_count(strip_tags((string) $article->content));

            $issues = array_keys(array_filter([
                'no_image' => blank($article->image),
                'no_products' => $article->products->isEmpty(),
                'draft_products' => $draftProducts->isNotEmpty(),
                'no_category' => $article->categories->isEmpty(),
                'no_meta_description' => blank($article->meta_description),
                'meta_title_too_long' => mb_strlen((string) $article->meta_title) > self::META_TITLE_MAX,
                'short_content' => $words < self::MIN_WORDS,
                'similar_title' => isset($similar[$article->id]),
            ]));

            $seo = SeoScore::forArticle($article);

            return [
                'id' => $article->id,
                'slug' => $article->slug,
                'title' => $article->title,
                'status' => $statusOf($article),
                'words' => $words,
                'completeness' => (int) round((1 - count($issues) / count(self::ISSUES)) * 100),
                'seo_score' => $seo['score'],
                'issues' => $issues,
                'failed_seo_checks' => array_keys(array_filter($seo['checks'], fn (bool $passed) => ! $passed)),
                'draft_products' => $draftProducts->pluck('slug')->values()->all(),
                'similar_to' => $similar[$article->id] ?? [],
            ];
        })->sortBy('completeness')->values()->all();
    }

    /**
     * @param  Collection<int, Article>  $articles
     * @return array<int, list<string>> ID artikel => slug artikel berjudul mirip
     */
    private static function similarTitles(Collection $articles): array
    {
        $words = $articles->mapWithKeys(fn (Article $article) => [
            $article->id => array_values(array_unique(array_filter(explode(' ', Str::of((string) $article->title)->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->value())))),
        ]);
        $list = $articles->values();
        $found = [];

        for ($i = 0; $i < $list->count(); $i++) {
            for ($j = $i + 1; $j < $list->count(); $j++) {
                [$a, $b] = [$list[$i], $list[$j]];
                $shortest = min(count($words[$a->id]), count($words[$b->id]));
                if ($shortest >= 3 && count(array_intersect($words[$a->id], $words[$b->id])) / $shortest >= self::SIMILAR_TITLE_OVERLAP) {
                    $found[$a->id][] = $b->slug;
                    $found[$b->id][] = $a->slug;
                }
            }
        }

        return $found;
    }
}
