<?php

namespace App\Filament\Reports\Widgets;

use App\Filament\Reports\Concerns\UsesReportPeriod;
use App\Support\Reports\ReportMetrics;
use App\Support\Reports\ReportPeriod;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Angka utama periode: klik, produk diklik, lead, kampanye; masing-masing dibanding periode sebelumnya. */
class ReportKpis extends StatsOverviewWidget
{
    use UsesReportPeriod;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $period = $this->period();
        $previous = $period->previous();
        $impressions = ReportMetrics::adEvents($period, 'impression');
        $adClicks = ReportMetrics::adEvents($period, 'click');

        return [
            self::comparisonStat('Tayangan halaman', ReportMetrics::pageViews($period), ReportMetrics::pageViews($previous), 'heroicon-o-eye')
                ->chart(array_values(ReportMetrics::dailyViews($period))),
            self::comparisonStat('Klik ke toko', ReportMetrics::clicks($period), ReportMetrics::clicks($previous), 'heroicon-o-cursor-arrow-rays')
                ->chart(array_values(ReportMetrics::dailyClicks($period))),
            self::comparisonStat('Lead formulir', ReportMetrics::leads($period), ReportMetrics::leads($previous), 'heroicon-o-inbox-arrow-down'),
            Stat::make('Klik kampanye', number_format($adClicks, 0, ',', '.'))
                ->description(number_format($impressions, 0, ',', '.').' tayangan · CTR '.($impressions > 0 ? number_format($adClicks / $impressions * 100, 1, ',', '.') : '0').'%')
                ->descriptionIcon('heroicon-o-megaphone')
                ->color('gray'),
        ];
    }

    /** Stat dengan keterangan perubahan terhadap periode sebelumnya (hijau naik, merah turun). */
    public static function comparisonStat(string $label, int $current, int $previous, string $icon): Stat
    {
        $change = ReportPeriod::change($current, $previous);

        return Stat::make($label, number_format($current, 0, ',', '.'))
            ->description(match (true) {
                $change === null => $previous === 0 && $current > 0 ? 'Baru di periode ini' : 'Belum ada pembanding',
                $change > 0 => '+'.number_format($change, 1, ',', '.').'% dari periode sebelumnya',
                $change < 0 => number_format($change, 1, ',', '.').'% dari periode sebelumnya',
                default => 'Sama dengan periode sebelumnya',
            })
            ->descriptionIcon(match (true) {
                $change > 0 => 'heroicon-m-arrow-trending-up',
                $change < 0 => 'heroicon-m-arrow-trending-down',
                default => $icon,
            })
            ->color(match (true) {
                $change > 0 => 'success',
                $change < 0 => 'danger',
                default => 'gray',
            });
    }
}
