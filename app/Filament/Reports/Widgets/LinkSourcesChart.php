<?php

namespace App\Filament\Reports\Widgets;

use App\Filament\Reports\Concerns\FiltersLinkPage;
use App\Models\Setting;
use App\Support\Reports\LinkReportMetrics;
use Filament\Widgets\ChartWidget;

/** Tayangan per sumber kunjungan (batang horizontal satu warna, 6 teratas + Lainnya). */
class LinkSourcesChart extends ChartWidget
{
    use FiltersLinkPage;

    private const LIMIT = 6;

    protected ?string $heading = 'Dari mana pengunjung datang';

    protected ?string $description = 'Tayangan per sumber kunjungan.';

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 1];

    protected static bool $isLazy = false;

    protected ?string $maxHeight = '260px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = LinkReportMetrics::bySource($this->period(), $this->linkPageId());
        $top = $rows->take(self::LIMIT)->pluck('views', 'source');
        $rest = $rows->slice(self::LIMIT)->sum('views');

        if ($rest > 0) {
            $top['Lainnya'] = $rest;
        }

        return [
            'datasets' => [[
                'label' => 'Tayangan',
                'data' => $top->values()->all(),
                'backgroundColor' => Setting::themeColor('theme_primary'),
                'borderRadius' => 4,
                'barThickness' => 18,
            ]],
            'labels' => $top->keys()->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'x' => ['beginAtZero' => true, 'border' => ['display' => false], 'ticks' => ['precision' => 0, 'maxTicksLimit' => 5]],
                'y' => ['grid' => ['display' => false]],
            ],
        ];
    }
}
