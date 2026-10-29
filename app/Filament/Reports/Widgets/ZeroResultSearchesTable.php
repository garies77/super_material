<?php

namespace App\Filament\Reports\Widgets;

use App\Filament\Reports\Concerns\UsesReportPeriod;
use App\Support\Reports\ReportMetrics;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Pencarian tanpa hasil: produk yang dicari tapi belum ada, atau istilah yang perlu ditambahkan ke nama/deskripsi. */
class ZeroResultSearchesTable extends TableWidget
{
    use UsesReportPeriod;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 1];

    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Dicari tapi tidak ketemu')
            ->description('Ide produk/artikel baru atau istilah yang perlu ditambahkan.')
            ->records(fn () => ReportMetrics::zeroResultSearches($this->period())->all())
            ->paginated(false)
            ->emptyStateHeading('Semua pencarian menemukan hasil')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->recordUrl(fn (array $record) => route('search', ['q' => $record['term']]), shouldOpenInNewTab: true)
            ->headerActions([$this->exportAction('pencarian-tanpa-hasil', ['term' => 'Kata kunci', 'searches' => 'Pencarian'],
                fn () => ReportMetrics::zeroResultSearches($this->period(), 500))])
            ->columns([
                TextColumn::make('term')->label('Kata kunci')->weight('medium')->wrap(),
                TextColumn::make('searches')->label('Pencarian')->numeric()->alignEnd()->weight('bold'),
            ]);
    }
}
