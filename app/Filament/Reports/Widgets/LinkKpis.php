<?php

namespace App\Filament\Reports\Widgets;

use App\Filament\Reports\Concerns\FiltersLinkPage;
use App\Support\Reports\LinkReportMetrics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Tayangan, klik, dan CTR Link Bio dibanding periode sebelumnya. */
class LinkKpis extends StatsOverviewWidget
{
    use FiltersLinkPage;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected function getColumns(): int
    {
        return 3;
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

        return [
            ReportKpis::comparisonStat('Tayangan halaman', $views, $previousViews, 'heroicon-o-eye'),
            ReportKpis::comparisonStat('Klik tombol', $clicks, $previousClicks, 'heroicon-o-cursor-arrow-rays'),
            Stat::make('CTR', LinkReportMetrics::formatCtr($ctr))
                ->description($previousCtr === null ? 'Klik ÷ tayangan' : 'Periode sebelumnya '.LinkReportMetrics::formatCtr($previousCtr))
                ->descriptionIcon('heroicon-o-chart-bar')
                ->color('gray'),
        ];
    }
}
