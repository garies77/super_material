<?php

namespace App\Support\Reports;

use App\Models\Article;
use App\Models\CatalogSnapshot;
use App\Models\Product;
use App\Support\SeoScore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Kesehatan katalog: kekurangan data produk terbit (dihitung langsung dengan query ringan) dan sebaran
 * skor SEO produk/artikel. Skor SEO membaca ukuran gambar dari disk sehingga dihitung lewat potret harian
 * (perintah reports:snapshot-catalog), yang sekaligus menjadi bahan tren mingguan.
 */
class CatalogHealth
{
    public const ISSUES = [
        'no_photo' => 'Tanpa foto',
        'no_description' => 'Tanpa ringkasan/deskripsi',
        'no_category' => 'Tanpa kategori',
        'no_brand' => 'Tanpa merek',
        'no_sku' => 'Tanpa SKU',
        'stale_price' => 'Masa harga lewat/kosong',
    ];

    public const SCORE_BUCKETS = ['0–49' => [0, 49], '50–79' => [50, 79], '80–99' => [80, 99], '100' => [100, 100]];

    /** Syarat tiap kekurangan untuk query produk. */
    public static function applyIssue(Builder $query, string $issue): Builder
    {
        $blank = fn (Builder $q, string $column) => $q->where(fn (Builder $w) => $w->whereNull($column)->orWhere($column, ''));

        return match ($issue) {
            'no_photo' => $blank($query->whereDoesntHave('media'), 'image'),
            'no_description' => $blank($blank($query, 'summary'), 'description'),
            'no_category' => $query->whereNull('category_id'),
            'no_brand' => $query->whereNull('brand_id'),
            'no_sku' => $blank($query, 'sku'),
            'stale_price' => $query->where('price_display', '!=', 'hidden')
                ->where(fn (Builder $w) => $w->whereNull('price_valid_until')->orWhere('price_valid_until', '<', now())),
        };
    }

    /** @return array<string, int> kekurangan => jumlah produk terbit */
    public static function issueCounts(): array
    {
        return collect(self::ISSUES)->map(fn (string $label, string $issue) => self::applyIssue(Product::published(), $issue)->count())->all();
    }

    /** Produk terbit yang punya minimal satu kekurangan. @return Builder<Product> */
    public static function productsWithIssues(): Builder
    {
        return Product::published()->where(function (Builder $any) {
            foreach (array_keys(self::ISSUES) as $issue) {
                $any->orWhere(fn (Builder $q) => self::applyIssue($q, $issue));
            }
        });
    }

    /** @return list<string> label kekurangan sebuah produk (dihitung dari model yang sudah dimuat) */
    public static function issuesOf(Product $product): array
    {
        $issues = [
            'no_photo' => ($product->media_count ?? $product->media()->count()) === 0 && blank($product->image),
            'no_description' => blank($product->summary) && blank($product->description),
            'no_category' => blank($product->category_id),
            'no_brand' => blank($product->brand_id),
            'no_sku' => blank($product->sku),
            'stale_price' => $product->price_display !== 'hidden' && ! $product->price_valid_until?->isFuture(),
        ];

        return array_values(array_intersect_key(self::ISSUES, array_filter($issues)));
    }

    /** Hitung & simpan potret hari ini (menimpa potret hari yang sama). */
    public static function snapshot(): CatalogSnapshot
    {
        $productScores = [];
        Product::published()->with(['media', 'brand', 'variants'])->chunkById(200, function (Collection $products) use (&$productScores) {
            foreach ($products as $product) {
                $productScores[] = SeoScore::forProduct($product)['score'];
            }
        });

        $articleScores = [];
        Article::published()->with(['categories', 'tags'])->chunkById(200, function (Collection $articles) use (&$articleScores) {
            foreach ($articles as $article) {
                $articleScores[] = SeoScore::forArticle($article)['score'];
            }
        });

        $products = Product::published()->count();

        // whereDate, bukan updateOrCreate: kolom date bisa tersimpan sebagai "Y-m-d 00:00:00" (cast date di SQLite)
        $today = now(ClickReport::TIMEZONE)->toDateString();
        $snapshot = CatalogSnapshot::whereDate('date', $today)->first() ?? new CatalogSnapshot(['date' => $today]);

        $snapshot->metrics = [
            'products' => $products,
            'articles' => count($articleScores),
            'complete' => $products - self::productsWithIssues()->count(),
            'issues' => self::issueCounts(),
            'product_seo' => self::scoreSummary($productScores),
            'article_seo' => self::scoreSummary($articleScores),
        ];
        // Selalu perbarui updated_at agar subjudul menampilkan waktu hitung terakhir
        $snapshot->touch();

        return $snapshot;
    }

    public static function latest(): ?CatalogSnapshot
    {
        return CatalogSnapshot::latest('date')->first();
    }

    /** Potret pembanding: yang terakhir paling tidak 7 hari sebelum potret terbaru. */
    public static function weekAgo(): ?CatalogSnapshot
    {
        $latest = self::latest();

        return $latest ? CatalogSnapshot::where('date', '<=', $latest->date->copy()->subDays(7))->latest('date')->first() : null;
    }

    /**
     * Potret terakhir tiap minggu (Senin–Minggu) untuk sejumlah minggu terakhir, urut lama ke baru.
     *
     * @return Collection<string, CatalogSnapshot> label minggu (d M) => potret
     */
    public static function weeklyTrend(int $weeks = 12): Collection
    {
        $since = now(ClickReport::TIMEZONE)->startOfWeek()->subWeeks($weeks - 1)->toDateString();

        return CatalogSnapshot::where('date', '>=', $since)->orderBy('date')->get()
            ->groupBy(fn (CatalogSnapshot $snapshot) => $snapshot->date->copy()->startOfWeek()->toDateString())
            ->mapWithKeys(fn (Collection $week, string $monday) => [
                Carbon::parse($monday)->translatedFormat('d M') => $week->last(),
            ]);
    }

    /**
     * @param  list<int>  $scores
     * @return array{avg: float, buckets: array<string, int>}
     */
    private static function scoreSummary(array $scores): array
    {
        return [
            'avg' => $scores ? round(array_sum($scores) / count($scores), 1) : 0.0,
            'buckets' => collect(self::SCORE_BUCKETS)
                ->map(fn (array $range) => count(array_filter($scores, fn (int $score) => $score >= $range[0] && $score <= $range[1])))
                ->all(),
        ];
    }
}
