<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ReadsReportPeriod;
use App\Models\Article;
use App\Models\Category;
use App\Models\Product;
use App\Support\Reports\ReportMetrics;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Daftar ide konten yang diurutkan dari data situs: kata kunci yang dicari pengunjung tapi tidak ada hasilnya, pencarian populer yang belum punya artikel, produk yang banyak diklik tapi belum dibahas di artikel mana pun, dan kategori berisi banyak produk tanpa artikel. Setiap ide disertai alasan dan tool yang disarankan untuk menindaklanjuti.')]
#[IsReadOnly]
class GetContentOpportunities extends AbilityTool
{
    use ReadsReportPeriod;

    /** Kategori dianggap layak dibuatkan artikel bila punya minimal sekian produk terbit. */
    public const MIN_CATEGORY_PRODUCTS = 3;

    protected string $ability = 'reports:read';

    public function handle(Request $request): ResponseFactory
    {
        $validated = $this->validatedPeriodArguments($request, ['limit' => ['nullable', 'integer', 'between:1,50']]);
        $period = $this->period($validated);
        $limit = $validated['limit'] ?? 15;
        $articleTitles = Article::published()->pluck('title')->map(fn (string $title) => mb_strtolower($title));
        $ideas = collect();

        foreach (ReportMetrics::zeroResultSearches($period, 20) as $row) {
            $ideas->push([
                'type' => 'search_no_result',
                'score' => $row['searches'] * 3,
                'title' => "Pencarian \"{$row['term']}\" tidak menemukan apa pun",
                'reason' => "{$row['searches']} kali dicari dan hasilnya kosong.",
                'next_step' => 'Bila produknya dijual: tambahkan produk (create-product) atau sinonim nama. Bila tidak: tulis artikel panduan (tulis-artikel-dari-produk).',
                'data' => $row,
            ]);
        }

        foreach (ReportMetrics::topSearches($period, 20) as $row) {
            $term = mb_strtolower($row['term']);
            if ($row['results'] === 0 || $articleTitles->contains(fn (string $title) => str_contains($title, $term))) {
                continue;
            }
            $ideas->push([
                'type' => 'search_without_article',
                'score' => $row['searches'],
                'title' => "Belum ada artikel untuk pencarian \"{$row['term']}\"",
                'reason' => "{$row['searches']} kali dicari; produknya ada, tapi belum ada artikel yang judulnya memuat kata ini.",
                'next_step' => 'Tulis artikel dengan kata kunci ini di judul (prompt tulis-artikel-dari-produk).',
                'data' => $row,
            ]);
        }

        $topProducts = ReportMetrics::topProducts($period, 20);
        $withoutArticle = Product::published()->whereIn('id', $topProducts->pluck('product_id'))
            ->whereDoesntHave('articles', fn ($q) => $q->published())
            ->get(['id', 'slug'])->keyBy('id');
        foreach ($topProducts as $row) {
            if (! isset($withoutArticle[$row['product_id']])) {
                continue;
            }
            $ideas->push([
                'type' => 'product_without_article',
                'score' => $row['clicks'] * 2,
                'title' => "Produk \"{$row['name']}\" laris tapi belum dibahas di artikel",
                'reason' => "{$row['clicks']} klik tombol beli dalam periode ini; belum ada artikel terbit yang menautkannya.",
                'next_step' => "Tulis panduan pemakaian dengan produk ini (tulis-artikel-dari-produk, produk={$withoutArticle[$row['product_id']]->slug}).",
                'data' => ['slug' => $withoutArticle[$row['product_id']]->slug] + $row,
            ]);
        }

        $categories = Category::active()
            ->withCount(['products' => fn ($q) => $q->published()])
            ->whereDoesntHave('articles', fn ($q) => $q->published())
            ->get()
            ->where('products_count', '>=', self::MIN_CATEGORY_PRODUCTS);
        foreach ($categories as $category) {
            $ideas->push([
                'type' => 'category_without_article',
                'score' => $category->products_count,
                'title' => "Kategori \"{$category->name}\" belum punya artikel",
                'reason' => "{$category->products_count} produk terbit tanpa satu pun artikel di kategori ini.",
                'next_step' => "Tulis artikel perbandingan atau panduan memilih (tulis-artikel-dari-produk, kategori={$category->slug}).",
                'data' => ['slug' => $category->slug, 'products' => $category->products_count],
            ]);
        }

        return Response::structured([
            'period' => $this->periodMeta($period),
            'total' => $ideas->count(),
            'opportunities' => $ideas->sortByDesc('score')->take($limit)->values()->all(),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->periodSchema($schema),
            'limit' => $schema->integer()->min(1)->max(50)->default(15)->description('Jumlah ide yang ditampilkan, bawaan 15.'),
        ];
    }
}
