<?php

namespace App\Http\Resources\Api;

use App\Models\Article;
use App\Models\Faq;
use App\Models\Product;
use App\Support\Api\HtmlSanitizer;
use Illuminate\Http\Request;

/**
 * Detail artikel: isi HTML tersanitasi, penulis, tag, dan produk tertaut (PRD 8.12.5).
 * HTML kustom ikut tersanitasi; CSS dan JavaScript kustom tidak pernah dikirim.
 *
 * @mixin Article
 */
class ArticleDetailResource extends ArticleResource
{
    public function toArray(Request $request): array
    {
        /** @var Article $article */
        $article = $this->resource;
        [$html, $toc] = $article->contentWithToc();

        return array_merge(parent::toArray($request), [
            'schema_type' => $article->schemaType(),
            'content' => HtmlSanitizer::clean($html),
            'custom_html' => HtmlSanitizer::clean($article->custom_html),
            'table_of_contents' => $toc,
            'faqs' => $article->faqs->map(fn (Faq $faq) => ['question' => $faq->question, 'answer' => $faq->answer])->values()->all(),
            'products' => $article->products->map(fn (Product $p) => ProductResource::summary($p))->values()->all(),
            'related_articles' => $article->relatedArticles->map(fn (Article $a) => ArticleResource::summary($a))->values()->all(),
        ]);
    }
}
