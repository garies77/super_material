<?php

namespace App\Filament\Reports\Widgets;

use App\Models\CatalogSnapshot;
use App\Support\Reports\CatalogHealth;
use Filament\Widgets\ChartWidget;

/** Tren mingguan 12 minggu: skor SEO rata-rata dan persentase produk lengkap, untuk melihat katalog makin rapi atau tidak. */
class CatalogTrendChart extends ChartWidget
{
    protected ?string $heading = 'Tren mingguan';

    protected ?string $description = 'Potret terakhir tiap minggu, 12 minggu terakhir. Data terkumpul mulai fitur ini aktif.';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected ?string $maxHeight = '280px';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $weeks = CatalogHealth::weeklyTrend();
        $line = fn (string $label, string $color, callable $value) => [
            'label' => $label,
            'data' => $weeks->map(fn (CatalogSnapshot $snapshot) => $value($snapshot->metrics))->values()->all(),
            'borderColor' => $color,
            'backgroundColor' => $color,
            'tension' => 0.3,
        ];

        return [
            'datasets' => [
                $line('Produk lengkap (%)', '#16a34a', fn (array $m) => $m['products'] > 0 ? round($m['complete'] / $m['products'] * 100, 1) : 0),
                $line('Skor SEO produk', '#f59309', fn (array $m) => $m['product_seo']['avg']),
                $line('Skor SEO artikel', '#64748b', fn (array $m) => $m['article_seo']['avg']),
            ],
            'labels' => $weeks->keys()->all(),
        ];
    }

    protected function getOptions(): array
    {
        return ['scales' => ['y' => ['min' => 0, 'max' => 100]]];
    }
}
