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

#[Description('Mengarsipkan artikel: hilang dari situs dan daftar, tetapi bisa dipulihkan dengan restore-article atau lewat panel admin (filter Arsip). Artikel yang sudah terbit atau terjadwal hanya bisa diarsipkan dengan force_unpublish=true; minta persetujuan pengguna dulu. Penulis hanya dapat mengarsipkan artikel miliknya.')]
#[IsDestructive]
class ArchiveArticle extends AbilityTool
{
    use WritesArticles;

    protected string $ability = 'articles:write';

    public function handle(Request $request): Response|ResponseFactory
    {
        $article = $this->findArticle($request);
        if (! $article) {
            return $this->articleNotFound();
        }

        $status = $this->articleStatus($article);
        if ($status !== 'draft' && $request->get('force_unpublish') !== true) {
            return Response::error("Artikel ini berstatus {$status}. Kirim force_unpublish=true untuk tetap mengarsipkan, atau jadikan draf dulu dengan update-article.");
        }

        $article->delete();
        $this->auditArticle($request, 'article.archived', $article, ['status' => ['from' => $status, 'to' => null]]);

        return Response::structured([
            'archived' => $article->slug,
            'title' => $article->title,
            'restore_with' => 'restore-article',
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'article' => $schema->string()->required()->description('ID (angka) atau slug artikel.'),
            'force_unpublish' => $schema->boolean()->description('Wajib true untuk mengarsipkan artikel yang terbit atau terjadwal.'),
        ];
    }
}
