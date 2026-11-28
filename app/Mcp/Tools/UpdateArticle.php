<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\WritesArticles;
use App\Support\Content\ArticleRules;
use App\Support\Content\ArticleWriter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Description('Mengubah artikel yang sudah ada (termasuk draf). Hanya kolom yang dikirim yang berubah; categories, tags, products, dan faqs mengganti seluruh daftar lama. status="published" menerbitkan, status="draft" menarik dari situs. Penulis hanya dapat mengubah artikel miliknya. image_url mengganti foto utama.')]
#[IsDestructive]
#[IsIdempotent]
class UpdateArticle extends AbilityTool
{
    use WritesArticles;

    protected string $ability = 'articles:write';

    public function handle(Request $request): Response|ResponseFactory
    {
        $article = $this->findArticle($request);
        if (! $article) {
            return $this->articleNotFound();
        }

        $input = Arr::except($request->all(), ['article']);
        if ($input === []) {
            return Response::error('Tidak ada kolom yang dikirim untuk diubah.');
        }

        $data = Validator::validate($input, ArticleRules::article($article));
        if ($image = $this->storeArticleImage($request, $article->slug)) {
            $data['image'] = $image;
        }
        $changes = ArticleWriter::update($article, $data, $request->user());

        if ($changes) {
            $this->auditArticle($request, 'article.updated', $article, $changes);
        }

        return Response::structured([
            'changed' => array_keys($changes),
            'article' => $this->articleDetail($article->refresh()),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'article' => $schema->string()->required()->description('ID (angka) atau slug artikel yang diubah, mis. dari search-articles.'),
        ] + self::articleFieldsSchema($schema);
    }
}
