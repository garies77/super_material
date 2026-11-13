<?php

namespace App\Filament\Reports\Widgets;

use App\Filament\Reports\Concerns\FiltersLinkPage;
use App\Support\Reports\LinkReportMetrics;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Asal kunjungan Link Bio dari ?utm_source atau referer; "langsung" bila tidak diketahui. */
class LinkSourcesTable extends TableWidget
{
    use FiltersLinkPage;

    protected int|string|array $columnSpan = 1;

    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        $columns = ['source' => 'Sumber', 'views' => 'Tayangan', 'clicks' => 'Klik', 'ctr' => 'CTR'];

        return $table
            ->heading('Sumber kunjungan')
            ->description('Pasang tautan dengan ?utm_source=instagram / tiktok agar sumber terbaca.')
            ->records(fn () => LinkReportMetrics::bySource($this->period(), $this->linkPageId())->all())
            ->paginated(false)
            ->emptyStateHeading('Belum ada kunjungan')
            ->emptyStateIcon('heroicon-o-globe-alt')
            ->headerActions([$this->exportAction('link-bio-sumber', $columns, fn () => LinkReportMetrics::bySource($this->period(), $this->linkPageId()))])
            ->columns([
                TextColumn::make('source')->label('Sumber')->weight('medium'),
                TextColumn::make('views')->label('Tayangan')->numeric()->alignEnd(),
                TextColumn::make('clicks')->label('Klik')->numeric()->alignEnd(),
                TextColumn::make('ctr')->label('CTR')->alignEnd()->color('gray'),
            ]);
    }
}
