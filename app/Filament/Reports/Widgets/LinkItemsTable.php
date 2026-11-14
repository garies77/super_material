<?php

namespace App\Filament\Reports\Widgets;

use App\Filament\Reports\Concerns\FiltersLinkPage;
use App\Support\Reports\LinkReportMetrics;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Klik per tombol Link Bio, porsinya dari total klik, dan CTR terhadap tayangan halamannya. */
class LinkItemsTable extends TableWidget
{
    use FiltersLinkPage;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 2];

    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        $columns = ['page' => 'Halaman', 'label' => 'Tombol', 'clicks' => 'Klik', 'share' => 'Porsi', 'ctr' => 'CTR'];

        return $table
            ->heading('Performa per tombol')
            ->description('Porsi = bagian dari semua klik; CTR = klik ÷ tayangan halaman.')
            ->records(fn () => LinkReportMetrics::byItem($this->period(), $this->linkPageId())->all())
            ->paginated(false)
            ->emptyStateHeading('Belum ada klik')
            ->emptyStateIcon('heroicon-o-cursor-arrow-rays')
            ->headerActions([$this->exportAction('link-bio-tombol', $columns, fn () => LinkReportMetrics::byItem($this->period(), $this->linkPageId()))])
            ->columns([
                TextColumn::make('label')->label('Tombol')->weight('medium')->description(fn (array $record) => $record['page']),
                TextColumn::make('clicks')->label('Klik')->numeric()->alignEnd()->weight('bold'),
                TextColumn::make('share')->label('Porsi')->alignEnd()->color('gray'),
                TextColumn::make('ctr')->label('CTR')->alignEnd(),
            ]);
    }
}
