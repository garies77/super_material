<?php

namespace App\Filament\Reports\Widgets;

use App\Filament\Reports\Concerns\FiltersLinkPage;
use App\Support\Reports\LinkReportMetrics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Angka utama Link Bio: tayangan, klik, CTR (dibanding periode sebelumnya), dan sumber kunjungan teratas. */
class LinkKpis extends StatsOverviewWidget
{
    use FiltersLinkPage;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $period = $this->period();
        $page = $this->linkPageId();
        $views = LinkReportMetrics::views($period, $page);
        $clicks = LinkReportMetrics::clicks($period, $page);
        $previousViews = LinkReportMetrics::views($period->previous(), $page);
        $previousClicks = LinkReportMetrics::clicks($period->previous(), $page);
        $ctr = LinkReportMetrics::ctr($clicks, $views);
        $previousCtr = LinkReportMetrics::ctr($previousClicks, $previousViews);
        $top = LinkReportMetrics::topSource($period, $page);

        return [
            ReportKpis::comparisonStat('Tayangan halaman', $views, $previousViews, 'heroicon-o-eye'),
            ReportKpis::comparisonStat('Klik tombol', $clicks, $previousClicks, 'heroicon-o-cursor-arrow-rays'),
            Stat::make('CTR (klik ÷ tayangan)', LinkReportMetrics::formatCtr($ctr))
                ->description($previousCtr === null ? 'Belum ada pembanding' : 'Periode sebelumnya '.LinkReportMetrics::formatCtr($previousCtr))
                ->descriptionIcon('heroicon-o-chart-bar')
                ->color('gray'),
            Stat::make('Sumber teratas', $top['source'] ?? '—')
                ->description($top ? number_format($top['views'], 0, ',', '.').' tayangan · '
                    .number_format($top['views'] / max(1, $views) * 100, 0, ',', '.').'% dari total' : 'Belum ada kunjungan')
                ->descriptionIcon('heroicon-o-globe-alt')
                ->color('gray'),
        ];
    }
}
