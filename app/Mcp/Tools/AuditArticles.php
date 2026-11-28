<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ScopesArticles;
use App\Models\Article;
use App\Support\Content\ArticleAudit;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Audit kelengkapan artikel (termasuk draf) dalam satu panggilan: tanpa foto utama, tanpa produk tertaut atau menautkan produk draf, tanpa kategori, meta kosong/terlalu panjang, isi kurang dari 300 kata, dan judul mirip artikel lain. Diurutkan dari yang paling tidak lengkap, lengkap dengan skor SEO panel admin. Penulis hanya melihat artikel miliknya.')]
#[IsReadOnly]
class AuditArticles extends AbilityTool
{
    use ScopesArticles;

    protected string $ability = 'articles:read';

    public function handle(Request $request): ResponseFactory
    {
        $input = $this->validated($request, [
            'issue' => ['sometimes', Rule::in(array_keys(ArticleAudit::ISSUES))],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:200'],
        ]);

        $articles = $this->visibleArticles($request->user())->with(['products', 'categories', 'tags'])->get();
        $rows = collect(ArticleAudit::audit($articles, fn (Article $article) => $this->articleStatus($article)));

        $issueCounts = collect(ArticleAudit::ISSUES)->map(fn (string $label, string $code) => [
            'label' => $label,
            'count' => $rows->filter(fn (array $row) => in_array($code, $row['issues'], true))->count(),
        ])->filter(fn (array $issue) => $issue['count'] > 0)->sortByDesc('count')->all();

        $selected = isset($input['issue'])
            ? $rows->filter(fn (array $row) => in_array($input['issue'], $row['issues'], true))
            : $rows;

        return Response::structured([
            'total_articles' => $rows->count(),
            'complete_articles' => $rows->where('issues', [])->count(),
            'issue_counts' => $issueCounts,
            'issue_labels' => ArticleAudit::ISSUES,
            'matching_articles' => $selected->count(),
            'articles' => $selected->take($input['limit'] ?? 50)->values()->all(),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'issue' => $schema->string()->enum(array_keys(ArticleAudit::ISSUES))->description('Hanya tampilkan artikel dengan kekurangan ini.'),
            'limit' => $schema->integer()->min(1)->max(200)->description('Jumlah artikel yang ditampilkan, bawaan 50.'),
        ];
    }
}
