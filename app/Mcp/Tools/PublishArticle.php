<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\WritesArticles;
use App\Models\Article;
use App\Support\Content\ArticleAudit;
use App\Support\Content\ArticleWriter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Carbon;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Description('Menerbitkan artikel sekarang, atau menjadwalkannya bila published_at diisi waktu yang akan datang. Sebelum terbit, artikel diperiksa: DITOLAK bila foto utama kosong, deskripsi meta kosong, atau menautkan produk yang masih draf (perbaiki dulu dengan update-article / image_url). Kekurangan lain dikembalikan sebagai warnings. skip_checks=true melewati pemeriksaan; pakai hanya bila pengguna memintanya.')]
#[IsDestructive]
class PublishArticle extends AbilityTool
{
    use WritesArticles;

    /** Kekurangan yang menghalangi terbit; sisanya hanya peringatan. */
    public const BLOCKING_ISSUES = ['no_image', 'no_meta_description', 'draft_products'];

    protected string $ability = 'articles:write';

    public function handle(Request $request): Response|ResponseFactory
    {
        $article = $this->findArticle($request);
        if (! $article) {
            return $this->articleNotFound();
        }

        $input = $this->validated($request, [
            'published_at' => ['sometimes', 'date'],
            'skip_checks' => ['sometimes', 'boolean'],
        ]);
        $publishAt = isset($input['published_at']) ? Carbon::parse($input['published_at']) : now();

        $audit = ArticleAudit::audit(collect([$article->load(['products', 'categories', 'tags'])]), fn (Article $a) => $this->articleStatus($a))[0];
        $blocking = array_values(array_intersect($audit['issues'], self::BLOCKING_ISSUES));
        if ($blocking && ! ($input['skip_checks'] ?? false)) {
            $reasons = implode('; ', array_map(fn (string $issue) => ArticleAudit::ISSUES[$issue], $blocking));

            return Response::error("Artikel belum siap terbit: {$reasons}. Lengkapi dulu, atau kirim skip_checks=true bila pengguna tetap ingin menerbitkan.");
        }

        $changes = ArticleWriter::update($article, ['status' => 'published', 'published_at' => $publishAt->toIso8601String()], $request->user());
        if ($changes) {
            $this->auditArticle($request, 'article.published', $article, $changes);
        }

        $article->refresh();
        $warnings = array_map(fn (string $issue) => ArticleAudit::ISSUES[$issue], array_values(array_diff($audit['issues'], $blocking)));

        return Response::structured([
            'status' => $this->articleStatus($article),
            'published_at' => $article->published_at?->toIso8601String(),
            'public_url' => $article->url(),
            'article' => $this->articleDetail($article),
        ] + ($warnings ? ['warnings' => $warnings] : []));
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'article' => $schema->string()->required()->description('ID (angka) atau slug artikel.'),
            'published_at' => $schema->string()->description('Waktu terbit ISO 8601, mis. "2026-12-01T08:00:00+07:00". Kosong = terbit sekarang; waktu yang akan datang = dijadwalkan.'),
            'skip_checks' => $schema->boolean()->description('true = lewati pemeriksaan kelengkapan (hanya bila pengguna memintanya).'),
        ];
    }
}
