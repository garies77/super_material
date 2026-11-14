<?php

namespace App\Support\Reports;

use App\Models\LinkItem;
use App\Models\LinkPage;
use App\Models\LinkStat;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Angka Laporan Link Bio dari agregat harian link_stats; dipakai widget dan ekspor CSV agar sumbernya sama. */
class LinkReportMetrics
{
    public const DEVICES = ['mobile' => 'Ponsel', 'desktop' => 'Desktop', 'tablet' => 'Tablet'];

    /** Nama tampil sumber kunjungan; sumber lain (domain perujuk, utm bebas) ditampilkan apa adanya. */
    public const SOURCE_LABELS = [
        'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'facebook' => 'Facebook', 'threads' => 'Threads',
        'youtube' => 'YouTube', 'linkedin' => 'LinkedIn', 'x' => 'X (Twitter)', 'whatsapp' => 'WhatsApp',
        'telegram' => 'Telegram', 'pinterest' => 'Pinterest', 'google' => 'Google', 'shopee' => 'Shopee',
        'tokopedia' => 'Tokopedia', 'langsung' => 'Langsung / tidak diketahui',
    ];

    public static function sourceLabel(string $source): string
    {
        return self::SOURCE_LABELS[$source] ?? $source;
    }

    /** @return array{source: string, views: int}|null sumber dengan tayangan terbanyak */
    public static function topSource(ReportPeriod $period, ?int $pageId = null): ?array
    {
        $top = self::bySource($period, $pageId)->first();

        return $top && $top['views'] > 0 ? ['source' => $top['source'], 'views' => $top['views']] : null;
    }

    public static function views(ReportPeriod $period, ?int $pageId = null): int
    {
        return (int) self::base($period, $pageId)->where('link_item_id', LinkStat::PAGE_VIEW)->sum('count');
    }

    public static function clicks(ReportPeriod $period, ?int $pageId = null): int
    {
        return (int) self::base($period, $pageId)->where('link_item_id', '>', LinkStat::PAGE_VIEW)->sum('count');
    }

    /** CTR dalam persen (klik ÷ tayangan); null bila belum ada tayangan. */
    public static function ctr(int $clicks, int $views): ?float
    {
        return $views > 0 ? round($clicks / $views * 100, 1) : null;
    }

    public static function formatCtr(?float $ctr): string
    {
        return $ctr === null ? '—' : number_format($ctr, 1, ',', '.').'%';
    }

    /** @return array<string, array{views: int, clicks: int}> tanggal (Y-m-d) => angka, termasuk hari tanpa data */
    public static function daily(ReportPeriod $period, ?int $pageId = null): array
    {
        $rows = self::base($period, $pageId)
            ->select('date', ...self::sums())
            ->groupBy('date')->get()
            ->keyBy(fn ($r) => $r->date->toDateString());

        $days = [];
        for ($day = $period->from->copy()->startOfDay(); $day->lte($period->to); $day->addDay()) {
            $row = $rows[$day->toDateString()] ?? null;
            $days[$day->toDateString()] = ['views' => (int) ($row->views ?? 0), 'clicks' => (int) ($row->clicks ?? 0)];
        }

        return $days;
    }

    /** @return Collection<int, array{page: string, label: string, clicks: int, share: string, ctr: string}> */
    public static function byItem(ReportPeriod $period, ?int $pageId = null): Collection
    {
        $clicks = self::base($period, $pageId)->where('link_item_id', '>', LinkStat::PAGE_VIEW)
            ->select('link_page_id', 'link_item_id', DB::raw('SUM(count) as clicks'))
            ->groupBy('link_page_id', 'link_item_id')->get();
        $viewsByPage = self::base($period, $pageId)->where('link_item_id', LinkStat::PAGE_VIEW)
            ->select('link_page_id', DB::raw('SUM(count) as views'))->groupBy('link_page_id')->pluck('views', 'link_page_id');
        $labels = LinkItem::whereIn('id', $clicks->pluck('link_item_id'))->pluck('label', 'id');
        $pages = LinkPage::pluck('title', 'id');
        $total = max(1, (int) $clicks->sum('clicks'));

        return $clicks->map(fn ($r) => [
            'page' => $pages[$r->link_page_id] ?? '—',
            'label' => $labels[$r->link_item_id] ?? '(tombol dihapus)',
            'clicks' => (int) $r->clicks,
            'share' => number_format($r->clicks / $total * 100, 1, ',', '.').'%',
            'ctr' => self::formatCtr(self::ctr((int) $r->clicks, (int) ($viewsByPage[$r->link_page_id] ?? 0))),
        ])->sortByDesc('clicks')->values();
    }

    /** @return Collection<int, array{source: string, views: int, clicks: int, ctr: string, share: string}> */
    public static function bySource(ReportPeriod $period, ?int $pageId = null): Collection
    {
        return self::withShare(self::grouped($period, $pageId, 'source')
            ->map(fn ($r) => ['source' => self::sourceLabel($r->source)] + self::row($r)));
    }

    /** @return Collection<int, array{device: string, views: int, clicks: int, ctr: string, share: string}> */
    public static function byDevice(ReportPeriod $period, ?int $pageId = null): Collection
    {
        return self::withShare(self::grouped($period, $pageId, 'device')
            ->map(fn ($r) => ['device' => self::DEVICES[$r->device] ?? $r->device] + self::row($r)));
    }

    /** Urutkan menurut tayangan dan tambahkan porsi tayangan (%). */
    private static function withShare(Collection $rows): Collection
    {
        $total = max(1, $rows->sum('views'));

        return $rows->sortByDesc('views')->values()
            ->map(fn (array $row) => $row + ['share' => number_format($row['views'] / $total * 100, 1, ',', '.').'%']);
    }

    private static function grouped(ReportPeriod $period, ?int $pageId, string $column): Collection
    {
        return self::base($period, $pageId)->select($column, ...self::sums())->groupBy($column)->get();
    }

    /** @return array{views: int, clicks: int, ctr: string} */
    private static function row(object $r): array
    {
        return ['views' => (int) $r->views, 'clicks' => (int) $r->clicks, 'ctr' => self::formatCtr(self::ctr((int) $r->clicks, (int) $r->views))];
    }

    /** @return array<int, Expression> */
    private static function sums(): array
    {
        return [
            DB::raw('SUM(CASE WHEN link_item_id = 0 THEN count ELSE 0 END) as views'),
            DB::raw('SUM(CASE WHEN link_item_id > 0 THEN count ELSE 0 END) as clicks'),
        ];
    }

    private static function base(ReportPeriod $period, ?int $pageId): Builder
    {
        return LinkStat::query()
            ->whereBetween('date', $period->dateRange())
            ->when($pageId, fn (Builder $q) => $q->where('link_page_id', $pageId));
    }
}
