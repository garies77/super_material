<?php

namespace App\Filament\Reports\Widgets;

use App\Filament\Reports\Concerns\FiltersLinkPage;
use App\Models\Setting;
use App\Support\Reports\LinkReportMetrics;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/** Tren harian tayangan vs klik Link Bio. */
class LinkTrendChart extends ChartWidget
{
    use FiltersLinkPage;

    protected ?string $heading = 'Tayangan & klik harian';

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 2];

    protected static bool $isLazy = false;

    protected ?string $maxHeight = '280px';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $days = LinkReportMetrics::daily($this->period(), $this->linkPageId());

        return [
            'datasets' => [
                [
                    'label' => 'Tayangan',
                    'data' => array_column($days, 'views'),
                    'borderColor' => '#94a3b8',
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Klik',
                    'data' => array_column($days, 'clicks'),
                    'borderColor' => Setting::themeColor('theme_primary'),
                    'backgroundColor' => Setting::themeColor('theme_primary').'1f',
                    'fill' => true,
                    'tension' => 0.3,
                ],
            ],
            'labels' => array_map(fn (string $date) => Carbon::parse($date)->translatedFormat('d M'), array_keys($days)),
        ];
    }
}
