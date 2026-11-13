<?php

namespace App\Filament\Reports\Widgets;

use App\Filament\Reports\Concerns\FiltersLinkPage;
use App\Support\Reports\LinkReportMetrics;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Tayangan & klik Link Bio per jenis perangkat. */
class LinkDevicesTable extends TableWidget
{
    use FiltersLinkPage;

    protected int|string|array $columnSpan = 1;

    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Perangkat')
            ->records(fn () => LinkReportMetrics::byDevice($this->period(), $this->linkPageId())->all())
            ->paginated(false)
            ->emptyStateHeading('Belum ada kunjungan')
            ->emptyStateIcon('heroicon-o-device-phone-mobile')
            ->columns([
                TextColumn::make('device')->label('Perangkat')->weight('medium'),
                TextColumn::make('views')->label('Tayangan')->numeric()->alignEnd(),
                TextColumn::make('clicks')->label('Klik')->numeric()->alignEnd(),
            ]);
    }
}
