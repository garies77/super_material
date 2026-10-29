<?php

namespace App\Support\Reports;

use App\Models\Product;
use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * Ringkasan satu periode untuk email mingguan, halaman cetak/PDF, dan API reports/summary:
 * satu sumber angka agar ketiganya selalu sama dengan Pusat Laporan.
 */
class ReportSummary
{
    public const RECIPIENTS_SETTING = 'report_weekly_recipients';

    public const ENABLED_SETTING = 'report_weekly_enabled';

    /** Senin–Minggu minggu lalu (WIB). */
    public static function lastWeek(): ReportPeriod
    {
        $monday = now(ClickReport::TIMEZONE)->startOfWeek()->subWeek();

        return new ReportPeriod($monday, $monday->copy()->endOfWeek());
    }

    /**
     * @return array{
     *     period: array{from: string, to: string, label: string},
     *     kpis: array<string, array{label: string, value: int, previous: int, change: ?float}>,
     *     top_products: list<array{name: string, clicks: int, change: ?float}>,
     *     top_searches: list<array{term: string, searches: int, results: int}>,
     *     zero_result_searches: list<array{term: string, searches: int}>,
     *     team: array{submissions: int, approved: int, first_time_right: ?float, median_wait_hours: ?float, queue: int},
     *     catalog: array{products: int, complete: int, issues: array<string, int>}
     * }
     */
    public static function build(ReportPeriod $period, int $limit = 5): array
    {
        $previous = $period->previous();
        $kpi = fn (string $label, callable $metric) => [
            'label' => $label,
            'value' => $value = $metric($period),
            'previous' => $old = $metric($previous),
            'change' => ReportPeriod::change($value, $old),
        ];
        $approvals = TeamMetrics::approvals($period);
        $products = Product::published()->count();

        return [
            'period' => ['from' => $period->from->toDateString(), 'to' => $period->to->toDateString(), 'label' => $period->label()],
            'kpis' => [
                'page_views' => $kpi('Tayangan halaman', ReportMetrics::pageViews(...)),
                'clicks' => $kpi('Klik ke toko', ReportMetrics::clicks(...)),
                'leads' => $kpi('Lead formulir', ReportMetrics::leads(...)),
                'campaign_clicks' => $kpi('Klik kampanye', fn (ReportPeriod $p) => ReportMetrics::adEvents($p, 'click')),
            ],
            'top_products' => ReportMetrics::topProducts($period, $limit)
                ->map(fn (array $row) => ['name' => $row['name'], 'clicks' => $row['clicks'], 'change' => $row['change']])->values()->all(),
            'top_searches' => ReportMetrics::topSearches($period, $limit)
                ->map(fn (array $row) => ['term' => $row['term'], 'searches' => $row['searches'], 'results' => $row['results']])->values()->all(),
            'zero_result_searches' => ReportMetrics::zeroResultSearches($period, $limit)
                ->map(fn (array $row) => ['term' => $row['term'], 'searches' => $row['searches']])->values()->all(),
            'team' => [
                'submissions' => TeamMetrics::submissions($period),
                'approved' => $approvals->count(),
                'first_time_right' => TeamMetrics::firstTimeRightRate($approvals),
                'median_wait_hours' => TeamMetrics::medianHours(TeamMetrics::reviewDecisions($period)),
                'queue' => TeamMetrics::queue()->count(),
            ],
            'catalog' => [
                'products' => $products,
                'complete' => $products - CatalogHealth::productsWithIssues()->count(),
                'issues' => CatalogHealth::issueCounts(),
            ],
        ];
    }

    /** "+12,5%", "-3,0%", "baru", atau "—" untuk tampilan ringkasan. */
    public static function formatChange(?float $change, int $previous = 0, int $current = 0): string
    {
        return match (true) {
            $change === null => $previous === 0 && $current > 0 ? 'baru' : '—',
            default => ($change > 0 ? '+' : '').number_format($change, 1, ',', '.').'%',
        };
    }

    public static function isEnabled(): bool
    {
        return Setting::getBool(self::ENABLED_SETTING);
    }

    /** @return list<string> alamat email penerima laporan mingguan yang valid */
    public static function recipients(): array
    {
        $saved = json_decode((string) Setting::get(self::RECIPIENTS_SETTING, '[]'), true);
        $emails = is_array($saved) ? $saved : [(string) Setting::get(self::RECIPIENTS_SETTING)];

        return array_values(array_unique(array_filter(array_map('trim', $emails), fn (string $email) => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)));
    }

    /** Pengiriman sungguhan butuh mailer selain log/array di server. */
    public static function mailConfigured(): bool
    {
        return ! in_array(config('mail.default'), ['log', 'array'], true);
    }

    public static function generatedAt(): Carbon
    {
        return now(ClickReport::TIMEZONE);
    }
}
