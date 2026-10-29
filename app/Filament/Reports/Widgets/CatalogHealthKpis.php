<?php

namespace App\Filament\Reports\Widgets;

use App\Models\Product;
use App\Support\Reports\CatalogHealth;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Ringkasan kesehatan katalog: kelengkapan produk dan skor SEO rata-rata, dibanding potret seminggu lalu. */
class CatalogHealthKpis extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $latest = CatalogHealth::latest()?->metrics;
        $weekAgo = CatalogHealth::weekAgo()?->metrics;
        $products = Product::published()->count();
        $withIssues = CatalogHealth::productsWithIssues()->count();
        $complete = $products - $withIssues;

        return [
            Stat::make('Produk lengkap', number_format($complete, 0, ',', '.').' dari '.number_format($products, 0, ',', '.'))
                ->description(($products > 0 ? number_format($complete / $products * 100, 1, ',', '.') : '0').'% tanpa kekurangan data')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color($products > 0 && $complete / $products >= 0.8 ? 'success' : 'warning'),
            self::delta('Perlu dilengkapi', $withIssues, $weekAgo ? $weekAgo['products'] - $weekAgo['complete'] : null, 'produk', lowerIsBetter: true),
            self::delta('Skor SEO produk', $latest['product_seo']['avg'] ?? 0, $weekAgo['product_seo']['avg'] ?? null, 'poin', suffix: '%'),
            self::delta('Skor SEO artikel', $latest['article_seo']['avg'] ?? 0, $weekAgo['article_seo']['avg'] ?? null, 'poin', suffix: '%'),
        ];
    }

    private static function delta(string $label, int|float $current, int|float|null $weekAgo, string $unit, bool $lowerIsBetter = false, string $suffix = ''): Stat
    {
        $value = is_float($current) ? number_format($current, 1, ',', '.') : number_format($current, 0, ',', '.');
        $diff = $weekAgo === null ? null : round($current - $weekAgo, 1);
        $better = $diff !== null && $diff != 0 && (($diff < 0) === $lowerIsBetter);

        return Stat::make($label, $value.$suffix)
            ->description(match (true) {
                $diff === null => 'Belum ada pembanding minggu lalu',
                $diff == 0 => 'Sama dengan minggu lalu',
                default => ($diff > 0 ? '+' : '').number_format($diff, is_float($diff) && floor($diff) != $diff ? 1 : 0, ',', '.')." {$unit} dari minggu lalu",
            })
            ->descriptionIcon(match (true) {
                $diff === null || $diff == 0 => 'heroicon-m-minus',
                $diff > 0 => 'heroicon-m-arrow-trending-up',
                default => 'heroicon-m-arrow-trending-down',
            })
            ->color(match (true) {
                $diff === null || $diff == 0 => 'gray',
                $better => 'success',
                default => 'danger',
            });
    }
}
