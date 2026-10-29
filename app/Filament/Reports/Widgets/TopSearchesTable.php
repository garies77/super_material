<?php

namespace App\Filament\Reports\Widgets;

use App\Filament\Reports\Concerns\UsesReportPeriod;
use App\Support\Reports\ReportMetrics;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Kata kunci yang paling sering dicari pengunjung: cerminan permintaan pasar. */
class TopSearchesTable extends TableWidget
{
    use UsesReportPeriod;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 2];

    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Pencarian terpopuler')
            ->description('Kata kunci yang dicari lewat halaman pencarian situs.')
            ->records(fn () => ReportMetrics::topSearches($this->period())->all())
            ->paginated(false)
            ->emptyStateHeading('Belum ada pencarian di periode ini')
            ->emptyStateIcon('heroicon-o-magnifying-glass')
            ->recordUrl(fn (array $record) => route('search', ['q' => $record['term']]), shouldOpenInNewTab: true)
            ->headerActions([$this->exportAction('pencarian-terpopuler', [
                'term' => 'Kata kunci', 'searches' => 'Pencarian', 'results' => 'Hasil terakhir', 'change' => 'Perubahan (%)',
            ], fn () => ReportMetrics::topSearches($this->period(), 500))])
            ->columns([
                TextColumn::make('term')->label('Kata kunci')->weight('medium')->wrap(),
                TextColumn::make('searches')->label('Pencarian')->numeric()->alignEnd()->weight('bold'),
                TextColumn::make('results')->label('Hasil')->numeric()->alignEnd()
                    ->color(fn (int $state) => $state === 0 ? 'danger' : 'gray'),
                TextColumn::make('change')->label('vs sebelumnya')->alignEnd()->badge()
                    ->formatStateUsing(fn (?float $state) => $state === null ? 'baru' : ($state > 0 ? '+' : '').number_format($state, 1, ',', '.').'%')
                    ->color(fn (?float $state) => match (true) {
                        $state === null => 'info',
                        $state > 0 => 'success',
                        $state < 0 => 'danger',
                        default => 'gray',
                    }),
            ]);
    }
}
