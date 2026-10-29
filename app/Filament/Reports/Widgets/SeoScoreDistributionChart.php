<?php

namespace App\Filament\Reports\Widgets;

use App\Support\Reports\CatalogHealth;
use Filament\Widgets\ChartWidget;

/** Sebaran skor SEO produk & artikel terbit menurut rentang skor (dari potret terbaru). */
class SeoScoreDistributionChart extends ChartWidget
{
    protected ?string $heading = 'Sebaran skor SEO';

    protected ?string $description = 'Jumlah konten per rentang skor kelengkapan SEO.';

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 2];

    protected static bool $isLazy = false;

    protected ?string $maxHeight = '280px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $metrics = CatalogHealth::latest()?->metrics;
        $buckets = array_keys(CatalogHealth::SCORE_BUCKETS);

        return [
            'datasets' => [
                [
                    'label' => 'Produk',
                    'data' => array_map(fn (string $bucket) => $metrics['product_seo']['buckets'][$bucket] ?? 0, $buckets),
                    'backgroundColor' => '#f59309',
                    'borderRadius' => 4,
                ],
                [
                    'label' => 'Artikel',
                    'data' => array_map(fn (string $bucket) => $metrics['article_seo']['buckets'][$bucket] ?? 0, $buckets),
                    'backgroundColor' => '#64748b',
                    'borderRadius' => 4,
                ],
            ],
            'labels' => array_map(fn (string $bucket) => "Skor {$bucket}", $buckets),
        ];
    }

    protected function getOptions(): array
    {
        return ['scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]]];
    }
}
