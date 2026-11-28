<?php

namespace App\Mcp\Tools;

use App\Models\Category;
use App\Models\Product;
use App\Support\Catalog\CatalogAudit;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Audit kelengkapan katalog dalam satu panggilan: jumlah produk per kekurangan (tanpa foto, deskripsi pendek, tanpa FAQ, tanpa tautan beli, meta kosong, slug tidak rapi, kemungkinan dobel, dll.), lalu daftar produk dari yang paling tidak lengkap beserta skor kelengkapan (0-100), skor SEO panel admin, dan pemeriksaan SEO yang gagal. Pakai ini sebelum merapikan produk.')]
#[IsReadOnly]
class AuditCatalog extends AbilityTool
{
    protected string $ability = 'catalog:read';

    public function handle(Request $request): ResponseFactory
    {
        $input = $this->validated($request, [
            'category' => ['sometimes', 'string', Rule::exists('categories', 'slug')],
            'brand' => ['sometimes', 'string', Rule::exists('brands', 'slug')],
            'status' => ['sometimes', Rule::in(['published', 'draft'])],
            'issue' => ['sometimes', Rule::in(array_keys(CatalogAudit::issueLabels()))],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:200'],
        ]);

        $products = Product::query()
            ->with(['media', 'links', 'faqs', 'brand', 'variants'])
            ->withCount('media')
            ->when($input['category'] ?? null, fn ($q, string $slug) => $q->whereIn('category_id', Category::firstWhere('slug', $slug)->descendantIds()))
            ->when($input['brand'] ?? null, fn ($q, string $slug) => $q->whereHas('brand', fn ($b) => $b->where('slug', $slug)))
            ->when($input['status'] ?? null, fn ($q, string $status) => $q->where('is_published', $status === 'published'))
            ->get();

        $rows = collect(CatalogAudit::audit($products));
        $labels = CatalogAudit::issueLabels();

        $issueCounts = collect($labels)->map(fn (string $label, string $code) => [
            'label' => $label,
            'count' => $rows->filter(fn (array $row) => in_array($code, $row['issues'], true))->count(),
        ])->filter(fn (array $issue) => $issue['count'] > 0)->sortByDesc('count')->all();

        $selected = isset($input['issue'])
            ? $rows->filter(fn (array $row) => in_array($input['issue'], $row['issues'], true))
            : $rows;

        return Response::structured([
            'total_products' => $rows->count(),
            'complete_products' => $rows->where('issues', [])->count(),
            'average_completeness' => (int) round($rows->avg('completeness') ?? 100),
            'issue_counts' => $issueCounts,
            'issue_labels' => $labels,
            'matching_products' => $selected->count(),
            'products' => $selected->take($input['limit'] ?? 50)->values()->all(),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'category' => $schema->string()->description('Slug kategori (termasuk subkategorinya).'),
            'brand' => $schema->string()->description('Slug merek.'),
            'status' => $schema->string()->enum(['published', 'draft'])->description('Kosongkan untuk semua status.'),
            'issue' => $schema->string()->enum(array_keys(CatalogAudit::issueLabels()))->description('Hanya tampilkan produk dengan kekurangan ini.'),
            'limit' => $schema->integer()->min(1)->max(200)->description('Jumlah produk yang ditampilkan, bawaan 50 (diurutkan dari yang paling tidak lengkap).'),
        ];
    }
}
