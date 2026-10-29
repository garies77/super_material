<?php

namespace App\Filament\Reports\Widgets;

use App\Filament\Reports\Concerns\UsesReportPeriod;
use App\Support\Reports\ReportMetrics;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Artikel paling banyak dibaca dan seberapa sering pembacanya lanjut menekan tombol beli: bukti nilai konten. */
class ArticlePerformanceTable extends TableWidget
{
    use UsesReportPeriod;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 2];

    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Performa artikel')
            ->description('Dibaca dihitung sekali per pengunjung per sesi. Klik beli = tombol beli ditekan langsung di artikel.')
            ->records(fn () => ReportMetrics::articlePerformance($this->period())->all())
            ->paginated(false)
            ->emptyStateHeading('Belum ada artikel yang dibaca')
            ->emptyStateDescription('Tambahkan produk terkait di artikel agar pembaca bisa langsung membeli.')
            ->emptyStateIcon('heroicon-o-newspaper')
            ->recordUrl(fn (array $record) => route('article.show', $record['slug']), shouldOpenInNewTab: true)
            ->headerActions([$this->exportAction('performa-artikel', [
                'title' => 'Artikel', 'slug' => 'Slug', 'views' => 'Dibaca', 'clicks' => 'Klik beli', 'rate' => 'Rasio klik (%)',
            ], fn () => ReportMetrics::articlePerformance($this->period(), 500))])
            ->columns([
                TextColumn::make('title')->label('Artikel')->weight('medium')->wrap(),
                TextColumn::make('views')->label('Dibaca')->numeric()->alignEnd()->weight('bold'),
                TextColumn::make('clicks')->label('Klik beli')->numeric()->alignEnd(),
                TextColumn::make('rate')->label('Rasio klik')->alignEnd()->color('gray')
                    ->formatStateUsing(fn (float $state) => number_format($state, 1, ',', '.').'%'),
            ]);
    }
}
