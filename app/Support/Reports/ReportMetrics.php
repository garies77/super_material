<?php

namespace App\Support\Reports;

use App\Models\AdEvent;
use App\Models\Article;
use App\Models\ClickEvent;
use App\Models\FormSubmission;
use App\Models\PageViewStat;
use App\Models\Product;
use App\Models\SalesChannel;
use App\Models\SearchStat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Angka Pusat Laporan. Semua berbasis agregat (tanpa data pribadi); klik bot selalu disaring dan lead
 * berstatus spam tidak dihitung. Angka klik memakai ClickReport yang sama dengan API laporan.
 */
class ReportMetrics
{
    public static function clicks(ReportPeriod $period): int
    {
        return $period->clickReport()->totalClicks();
    }

    /** @return array<string, int> tanggal (Y-m-d, WIB) => klik, termasuk hari tanpa klik */
    public static function dailyClicks(ReportPeriod $period): array
    {
        $counts = $period->clickReport('day')->rows()->pluck('clicks', 'date')->all();
        $days = [];

        for ($day = $period->from->copy()->startOfDay(); $day->lte($period->to); $day->addDay()) {
            $days[$day->toDateString()] = $counts[$day->toDateString()] ?? 0;
        }

        return $days;
    }

    public static function clickedProducts(ReportPeriod $period): int
    {
        return self::clickQuery($period)->distinct()->count('product_id');
    }

    public static function leads(ReportPeriod $period): int
    {
        return FormSubmission::whereBetween('created_at', $period->utcRange())->where('status', '!=', 'spam')->count();
    }

    public static function adEvents(ReportPeriod $period, string $type): int
    {
        return AdEvent::whereBetween('created_at', $period->utcRange())->where('is_bot', false)->where('type', $type)->count();
    }

    /** @return Collection<int, array{name: string, clicks: int}> */
    public static function channelShares(ReportPeriod $period): Collection
    {
        return $period->clickReport('channel')->rows()->map(fn (array $row) => ['name' => $row['name'], 'clicks' => $row['clicks']]);
    }

    /**
     * Produk terlaris berdasarkan klik, dengan rincian per kanal dan pembanding periode sebelumnya.
     *
     * @return Collection<string, array<string, mixed>>
     */
    public static function topProducts(ReportPeriod $period, int $limit = 10): Collection
    {
        $rows = $period->clickReport('product')->rows()->take($limit);
        $ids = $rows->pluck('product_id');

        $previous = self::clickQuery($period->previous())->whereIn('product_id', $ids)
            ->groupBy('product_id')->selectRaw('product_id, count(*) as clicks')->pluck('clicks', 'product_id');
        $byChannel = self::clickQuery($period)->whereIn('product_id', $ids)
            ->groupBy('product_id', 'sales_channel_id')->selectRaw('product_id, sales_channel_id, count(*) as clicks')->get()
            ->groupBy('product_id');
        $channels = SalesChannel::pluck('name', 'id');
        $categories = Product::whereIn('id', $ids)->with('category')->get()->mapWithKeys(fn (Product $p) => [$p->id => $p->category?->name]);

        return $rows->mapWithKeys(fn (array $row) => ["p{$row['product_id']}" => [
            'product_id' => $row['product_id'],
            'name' => $row['name'],
            'category' => $categories[$row['product_id']] ?? '—',
            'clicks' => $row['clicks'],
            'change' => ReportPeriod::change($row['clicks'], (int) ($previous[$row['product_id']] ?? 0)),
            'channels' => collect($byChannel[$row['product_id']] ?? [])
                ->sortByDesc('clicks')
                ->map(fn ($c) => ($channels[$c->sales_channel_id] ?? 'Lainnya').' '.$c->clicks)->implode(' · '),
        ]]);
    }

    /** Produk terbit yang tidak diklik sama sekali dalam periode ini. @return Builder<Product> */
    public static function zeroClickProducts(ReportPeriod $period): Builder
    {
        return Product::published()->whereDoesntHave('clicks', fn (Builder $q) => $q
            ->whereBetween('created_at', $period->utcRange())->where('is_bot', false));
    }

    /**
     * Klik yang membawa parameter UTM (kampanye/iklan/tautan promosi).
     *
     * @return Collection<string, array{source: string, medium: string, campaign: string, clicks: int}>
     */
    public static function utmCampaigns(ReportPeriod $period): Collection
    {
        return self::clickQuery($period)->whereNotNull('utm_source')
            ->groupBy('utm_source', 'utm_medium', 'utm_campaign')
            ->selectRaw('utm_source, utm_medium, utm_campaign, count(*) as clicks')
            ->orderByDesc('clicks')->toBase()->get()
            ->mapWithKeys(fn ($row, int $i) => ["u{$i}" => [
                'source' => $row->utm_source,
                'medium' => $row->utm_medium ?: '—',
                'campaign' => $row->utm_campaign ?: '—',
                'clicks' => (int) $row->clicks,
            ]]);
    }

    /**
     * Jenis halaman tempat pengunjung menekan tombol beli (dari URL halaman asal klik).
     *
     * @return Collection<string, array{origin: string, clicks: int, share: float}>
     */
    public static function clickOrigins(ReportPeriod $period): Collection
    {
        $prefixes = [
            'Halaman produk' => self::routePrefix('product.show'),
            'Artikel' => self::routePrefix('article.show'),
            'Kategori' => self::routePrefix('category.show'),
            'Pencarian' => self::routePrefix('search'),
        ];

        $counts = self::clickQuery($period)->groupBy('page_url')->selectRaw('page_url, count(*) as clicks')->toBase()->get()
            ->reduce(function (array $carry, $row) use ($prefixes) {
                $path = '/'.ltrim((string) parse_url((string) $row->page_url, PHP_URL_PATH), '/');
                $origin = match (true) {
                    blank($row->page_url) => 'Tidak diketahui',
                    $path === '/' => 'Beranda',
                    default => collect($prefixes)->search(fn (string $prefix) => str_starts_with($path, $prefix)) ?: 'Halaman lain',
                };
                $carry[$origin] = ($carry[$origin] ?? 0) + (int) $row->clicks;

                return $carry;
            }, []);

        arsort($counts);
        $total = max(1, array_sum($counts));

        return collect($counts)->mapWithKeys(fn (int $clicks, string $origin) => [$origin => [
            'origin' => $origin,
            'clicks' => $clicks,
            'share' => round($clicks / $total * 100, 1),
        ]]);
    }

    /** @return Collection<string, array{title: string, slug: string, clicks: int}> */
    public static function articlesDrivingClicks(ReportPeriod $period, int $limit = 10): Collection
    {
        return $period->clickReport('article')->rows()->take($limit)
            ->mapWithKeys(fn (array $row) => ["a{$row['slug']}" => [
                'title' => $row['title'] ?? $row['slug'],
                'slug' => $row['slug'],
                'clicks' => $row['clicks'],
            ]]);
    }

    /**
     * Lead per formulir beserta status tindak lanjut (spam tidak dihitung).
     *
     * @return Collection<string, array<string, mixed>>
     */
    public static function leadsByForm(ReportPeriod $period): Collection
    {
        return FormSubmission::whereBetween('form_submissions.created_at', $period->utcRange())->where('status', '!=', 'spam')
            ->join('custom_forms', 'custom_forms.id', '=', 'form_submissions.custom_form_id')
            ->groupBy('custom_forms.id', 'custom_forms.name', 'form_submissions.status')
            ->selectRaw('custom_forms.id as form_id, custom_forms.name as form_name, form_submissions.status, count(*) as total')
            ->toBase()->get()
            ->groupBy('form_id')
            ->mapWithKeys(fn (Collection $rows, $formId) => ["f{$formId}" => [
                'form' => $rows->first()->form_name,
                'total' => (int) $rows->sum('total'),
                'new' => (int) $rows->where('status', 'new')->sum('total'),
                'contacted' => (int) $rows->where('status', 'contacted')->sum('total'),
                'done' => (int) $rows->where('status', 'done')->sum('total'),
            ]])
            ->sortByDesc('total');
    }

    /** Tayangan halaman produk/artikel (sekali per sesi, tanpa bot). */
    public static function pageViews(ReportPeriod $period, ?string $type = null): int
    {
        return (int) self::viewQuery($period)->when($type, fn (Builder $q) => $q->where('viewable_type', $type))->sum('views');
    }

    /** @return array<string, int> tanggal (Y-m-d, WIB) => tayangan, termasuk hari tanpa tayangan */
    public static function dailyViews(ReportPeriod $period): array
    {
        $counts = self::viewQuery($period)->groupBy('date')->selectRaw('date, sum(views) as views')->toBase()->get()
            ->mapWithKeys(fn ($row) => [substr((string) $row->date, 0, 10) => (int) $row->views])->all();
        $days = [];

        for ($day = $period->from->copy()->startOfDay(); $day->lte($period->to); $day->addDay()) {
            $days[$day->toDateString()] = $counts[$day->toDateString()] ?? 0;
        }

        return $days;
    }

    /**
     * Produk paling banyak dilihat beserta klik tombol beli dan rasio konversinya (klik ÷ tayangan).
     *
     * @return Collection<string, array{product_id: int, name: string, category: string, views: int, clicks: int, rate: float}>
     */
    public static function productConversion(ReportPeriod $period, int $limit = 10): Collection
    {
        $views = self::viewQuery($period)->where('viewable_type', PageViewStat::PRODUCT)
            ->groupBy('viewable_id')->selectRaw('viewable_id, sum(views) as views')
            ->orderByDesc('views')->limit($limit)->toBase()->pluck('views', 'viewable_id');
        $clicks = self::clickQuery($period)->whereIn('product_id', $views->keys())
            ->groupBy('product_id')->selectRaw('product_id, count(*) as clicks')->pluck('clicks', 'product_id');
        $products = Product::whereIn('id', $views->keys())->with('category')->get()->keyBy('id');

        return $views->filter(fn ($count, $id) => $products->has($id))
            ->mapWithKeys(fn ($count, $id) => ["p{$id}" => [
                'product_id' => (int) $id,
                'name' => $products[$id]->name,
                'category' => $products[$id]->category?->name ?? '—',
                'views' => (int) $count,
                'clicks' => (int) ($clicks[$id] ?? 0),
                'rate' => round((int) ($clicks[$id] ?? 0) / max(1, (int) $count) * 100, 1),
            ]]);
    }

    /**
     * Artikel paling banyak dibaca beserta klik tombol beli dari halaman artikel itu.
     *
     * @return Collection<string, array{title: string, slug: string, views: int, clicks: int, rate: float}>
     */
    public static function articlePerformance(ReportPeriod $period, int $limit = 10): Collection
    {
        $views = self::viewQuery($period)->where('viewable_type', PageViewStat::ARTICLE)
            ->groupBy('viewable_id')->selectRaw('viewable_id, sum(views) as views')->toBase()->pluck('views', 'viewable_id');
        $clicks = $period->clickReport('article')->rows()->pluck('clicks', 'slug');
        $articles = Article::query()
            ->where(fn (Builder $q) => $q->whereIn('id', $views->keys())->orWhereIn('slug', $clicks->keys()))
            ->get(['id', 'slug', 'title']);

        return $articles
            ->map(fn (Article $article) => [
                'title' => $article->title,
                'slug' => $article->slug,
                'views' => (int) ($views[$article->id] ?? 0),
                'clicks' => (int) ($clicks[$article->slug] ?? 0),
            ])
            ->map(fn (array $row) => $row + ['rate' => $row['views'] > 0 ? round($row['clicks'] / $row['views'] * 100, 1) : 0.0])
            ->sortBy([['views', 'desc'], ['clicks', 'desc']])
            ->take($limit)
            ->mapWithKeys(fn (array $row) => ["a{$row['slug']}" => $row]);
    }

    /**
     * Kata kunci yang paling sering dicari di halaman /cari, dengan jumlah hasil terakhir dan perubahan.
     *
     * @return Collection<string, array{term: string, searches: int, results: int, change: ?float}>
     */
    public static function topSearches(ReportPeriod $period, int $limit = 10): Collection
    {
        $rows = self::searchTerms($period)->take($limit);
        $previous = SearchStat::whereBetween('date', $period->previous()->dateRange())->whereIn('term', $rows->keys())
            ->groupBy('term')->selectRaw('term, sum(searches) as searches')->pluck('searches', 'term');

        return $rows->mapWithKeys(fn (array $row, string $term) => ['s'.md5($term) => $row + [
            'change' => ReportPeriod::change($row['searches'], (int) ($previous[$term] ?? 0)),
        ]]);
    }

    /**
     * Kata kunci yang (pada pencarian terakhirnya) tidak menemukan apa pun: ide produk/artikel baru atau sinonim.
     *
     * @return Collection<string, array{term: string, searches: int, results: int}>
     */
    public static function zeroResultSearches(ReportPeriod $period, int $limit = 10): Collection
    {
        return self::searchTerms($period)->where('results', 0)->take($limit)
            ->mapWithKeys(fn (array $row, string $term) => ['s'.md5($term) => $row]);
    }

    /** @return Collection<string, array{term: string, searches: int, results: int}> kata kunci => total, urut terbanyak */
    private static function searchTerms(ReportPeriod $period): Collection
    {
        return SearchStat::whereBetween('date', $period->dateRange())->orderBy('date')->toBase()->get(['term', 'searches', 'results'])
            ->groupBy('term')
            ->map(fn (Collection $days, string $term) => [
                'term' => $term,
                'searches' => (int) $days->sum('searches'),
                'results' => (int) $days->last()->results,
            ])
            ->sortBy([['searches', 'desc'], ['term', 'asc']]);
    }

    /** @return Builder<PageViewStat> */
    private static function viewQuery(ReportPeriod $period): Builder
    {
        return PageViewStat::query()->whereBetween('date', $period->dateRange());
    }

    /** @return Builder<ClickEvent> klik manusia (bukan bot) dalam periode */
    private static function clickQuery(ReportPeriod $period): Builder
    {
        return ClickEvent::query()->whereBetween('created_at', $period->utcRange())->where('is_bot', false);
    }

    private static function routePrefix(string $name): string
    {
        $path = (string) parse_url(route($name, ['slug' => 'x']), PHP_URL_PATH);

        return str_ends_with($path, '/x') ? substr($path, 0, -1) : $path;
    }
}
