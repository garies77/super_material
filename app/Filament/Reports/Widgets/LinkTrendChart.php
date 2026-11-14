<?php

namespace App\Filament\Reports\Widgets;

use App\Filament\Reports\Concerns\FiltersLinkPage;
use App\Models\Setting;
use App\Support\Reports\LinkReportMetrics;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/** Tren harian tayangan vs klik Link Bio (satu sumbu: keduanya hitungan kunjungan). */
class LinkTrendChart extends ChartWidget
{
    use FiltersLinkPage;

    protected ?string $heading = 'Tayangan & klik harian';

    protected ?string $description = 'Tayangan dihitung sekali per kunjungan; klik setiap kali tombol ditekan.';

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 2];

    protected static bool $isLazy = false;

    protected ?string $maxHeight = '260px';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $days = LinkReportMetrics::daily($this->period(), $this->linkPageId());
        $primary = Setting::themeColor('theme_primary');
        $line = ['borderWidth' => 2, 'tension' => 0.3, 'pointRadius' => 0, 'pointHoverRadius' => 5, 'pointHitRadius' => 12];

        return [
            'datasets' => [
                ['label' => 'Tayangan', 'data' => array_column($days, 'views'), 'borderColor' => '#94A3B8', 'backgroundColor' => '#94A3B8'] + $line,
                ['label' => 'Klik', 'data' => array_column($days, 'clicks'), 'borderColor' => $primary, 'backgroundColor' => $primary.'1a', 'fill' => 'origin'] + $line,
            ],
            'labels' => array_map(fn (string $date) => Carbon::parse($date)->translatedFormat('d M'), array_keys($days)),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'plugins' => [
                'legend' => ['position' => 'bottom', 'labels' => ['usePointStyle' => true, 'pointStyle' => 'line', 'boxWidth' => 24]],
                'tooltip' => ['padding' => 10],
            ],
            'scales' => [
                'x' => ['grid' => ['display' => false], 'ticks' => ['autoSkip' => true, 'maxTicksLimit' => 8, 'maxRotation' => 0]],
                'y' => ['beginAtZero' => true, 'border' => ['display' => false], 'ticks' => ['precision' => 0, 'maxTicksLimit' => 5]],
            ],
        ];
    }
}
