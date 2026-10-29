<?php

namespace App\Filament\Reports\Widgets;

use App\Filament\Reports\Concerns\UsesReportPeriod;
use App\Support\Reports\TeamMetrics;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Angka utama alur tinjauan: kiriman, persetujuan, waktu tunggu, dan antrean saat ini. */
class TeamKpis extends StatsOverviewWidget
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
        $approvals = TeamMetrics::approvals($period);
        $firstTimeRight = TeamMetrics::firstTimeRightRate($approvals);
        $decisions = TeamMetrics::reviewDecisions($period);
        $queue = TeamMetrics::queue();
        $oldest = $queue->first()['submitted_at'] ?? null;

        return [
            ReportKpis::comparisonStat('Kiriman masuk', TeamMetrics::submissions($period), TeamMetrics::submissions($period->previous()), 'heroicon-o-paper-airplane'),
            Stat::make('Disetujui', number_format($approvals->count(), 0, ',', '.'))
                ->description($firstTimeRight === null ? 'Belum ada persetujuan' : number_format($firstTimeRight, 1, ',', '.').'% tepat sekali (tanpa perbaikan)')
                ->descriptionIcon('heroicon-o-check-badge')
                ->color($firstTimeRight === null ? 'gray' : ($firstTimeRight >= 70 ? 'success' : 'warning')),
            Stat::make('Median waktu tunggu', TeamMetrics::formatHours(TeamMetrics::medianHours($decisions)))
                ->description($decisions->isEmpty() ? 'Belum ada keputusan tinjauan' : 'dari '.$decisions->count().' keputusan tinjauan')
                ->descriptionIcon('heroicon-o-clock')
                ->color('gray'),
            Stat::make('Antrean sekarang', number_format($queue->count(), 0, ',', '.'))
                ->description($oldest ? 'Terlama menunggu '.TeamMetrics::formatHours($oldest->diffInMinutes(now()) / 60) : 'Tidak ada yang menunggu')
                ->descriptionIcon('heroicon-o-inbox-stack')
                ->color($oldest && $oldest->diffInHours(now()) >= 48 ? 'danger' : 'gray'),
        ];
    }
}
