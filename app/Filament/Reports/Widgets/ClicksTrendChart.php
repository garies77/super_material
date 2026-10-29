<?php

namespace App\Filament\Reports\Widgets;

use App\Filament\Reports\Concerns\UsesReportPeriod;
use App\Support\Reports\ReportMetrics;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/** Tren klik harian periode ini, dengan garis periode sebelumnya sebagai pembanding. */
class ClicksTrendChart extends ChartWidget
{
    use UsesReportPeriod;

    protected ?string $heading = 'Tren klik harian';

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 2];

    protected static bool $isLazy = false;

    protected ?string $maxHeight = '280px';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $current = ReportMetrics::dailyClicks($this->period());
        $previous = array_values(ReportMetrics::dailyClicks($this->period()->previous()));

        return [
            'datasets' => [
                [
                    'label' => 'Periode ini',
                    'data' => array_values($current),
                    'borderColor' => '#f59309',
                    'backgroundColor' => 'rgba(245, 147, 9, 0.12)',
                    'fill' => true,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Periode sebelumnya',
                    'data' => $previous,
                    'borderColor' => '#94a3b8',
                    'borderDash' => [6, 4],
                    'pointRadius' => 0,
                    'tension' => 0.3,
                ],
            ],
            'labels' => array_map(fn (string $date) => Carbon::parse($date)->translatedFormat('d M'), array_keys($current)),
        ];
    }
}
