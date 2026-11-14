<?php

namespace App\Filament\Reports\Widgets;

use App\Filament\Reports\Concerns\FiltersLinkPage;
use App\Support\Reports\LinkReportMetrics;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Rincian sumber kunjungan Link Bio (dari ?utm_source atau situs perujuk) dengan klik & CTR. */
class LinkSourcesTable extends TableWidget
{
    use FiltersLinkPage;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        $columns = ['source' => 'Sumber', 'views' => 'Tayangan', 'share' => 'Porsi', 'clicks' => 'Klik', 'ctr' => 'CTR'];

        return $table
            ->heading('Rincian sumber kunjungan')
            ->description('Dari ?utm_source pada tautan bio atau situs perujuk. "Langsung" = asal tidak terkirim; pakai tautan bio per platform di tab Pengaturan.')
            ->records(fn () => LinkReportMetrics::bySource($this->period(), $this->linkPageId())->all())
            ->paginated(false)
            ->emptyStateHeading('Belum ada kunjungan')
            ->emptyStateIcon('heroicon-o-globe-alt')
            ->headerActions([$this->exportAction('link-bio-sumber', $columns, fn () => LinkReportMetrics::bySource($this->period(), $this->linkPageId()))])
            ->columns([
                TextColumn::make('source')->label('Sumber')->weight('medium'),
                TextColumn::make('views')->label('Tayangan')->numeric()->alignEnd(),
                TextColumn::make('share')->label('Porsi tayangan')->alignEnd()->color('gray'),
                TextColumn::make('clicks')->label('Klik')->numeric()->alignEnd(),
                TextColumn::make('ctr')->label('CTR')->alignEnd()->weight('medium'),
            ]);
    }
}
