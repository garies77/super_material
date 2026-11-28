<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\WritesArticles;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Description('Memulihkan artikel dari arsip. Status dan jadwal terbit kembali seperti sebelum diarsipkan. Penulis hanya dapat memulihkan artikel miliknya.')]
#[IsDestructive(false)]
#[IsIdempotent]
class RestoreArticle extends AbilityTool
{
    use WritesArticles;

    protected string $ability = 'articles:write';

    public function handle(Request $request): Response|ResponseFactory
    {
        $key = trim((string) $request->get('article'));
        $article = $key === '' ? null : $this->visibleArticles($request->user())->onlyTrashed()
            ->where(fn ($q) => ctype_digit($key) ? $q->whereKey((int) $key) : $q->where('slug', $key))
            ->first();

        if (! $article) {
            return Response::error('Artikel arsip tidak ditemukan. Kirim ID atau slug artikel yang diarsipkan.');
        }

        $article->restore();
        $this->auditArticle($request, 'article.restored', $article, []);

        return Response::structured($this->articleDetail($article->refresh()));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'article' => $schema->string()->required()->description('ID (angka) atau slug artikel yang diarsipkan.'),
        ];
    }
}
