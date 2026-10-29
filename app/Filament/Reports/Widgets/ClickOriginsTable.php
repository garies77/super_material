<?php

namespace App\Filament\Reports\Widgets;

use App\Filament\Reports\Concerns\UsesReportPeriod;
use App\Support\Reports\ReportMetrics;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Dari jenis halaman apa pengunjung menekan tombol beli (produk, artikel, beranda, kategori, pencarian). */
class ClickOriginsTable extends TableWidget
{
    use UsesReportPeriod;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 1];

    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Halaman asal klik')
            ->description('Halaman tempat tombol beli ditekan.')
            ->records(fn () => ReportMetrics::clickOrigins($this->period())->all())
            ->paginated(false)
            ->emptyStateHeading('Belum ada klik')
            ->headerActions([$this->exportAction('halaman-asal-klik', ['origin' => 'Halaman', 'clicks' => 'Klik', 'share' => 'Porsi (%)'],
                fn () => ReportMetrics::clickOrigins($this->period()))])
            ->columns([
                TextColumn::make('origin')->label('Halaman'),
                TextColumn::make('clicks')->label('Klik')->numeric()->alignEnd()->weight('bold'),
                TextColumn::make('share')->label('Porsi')->alignEnd()->color('gray')
                    ->formatStateUsing(fn (float $state) => number_format($state, 1, ',', '.').'%'),
            ]);
    }
}
