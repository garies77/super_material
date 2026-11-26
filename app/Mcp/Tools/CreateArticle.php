<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\WritesArticles;
use App\Support\Content\ArticleRules;
use App\Support\Content\ArticleWriter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Validator;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Description('Membuat artikel baru. Dibuat sebagai DRAF (belum tampil di situs) kecuali status="published" dikirim. Isi dalam HTML dengan subjudul <h2>/<h3>. Kategori, tag, produk tertaut, FAQ, dan SEO boleh dikirim sekaligus. Foto utama diunggah lewat panel admin.')]
#[IsDestructive(false)]
class CreateArticle extends AbilityTool
{
    use WritesArticles;

    protected string $ability = 'articles:write';

    public function handle(Request $request): Response|ResponseFactory
    {
        $data = Validator::validate($request->all(), ArticleRules::article());
        $article = ArticleWriter::create($data, $request->user());

        $this->auditArticle($request, 'article.created', $article, ['fields' => array_keys($data)]);

        return Response::structured($this->articleDetail($article));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        $fields = self::articleFieldsSchema($schema);
        $fields['title'] = $fields['title']->required();
        $fields['content'] = $fields['content']->required();

        return $fields;
    }
}
