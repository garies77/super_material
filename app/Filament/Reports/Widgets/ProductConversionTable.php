<?php

namespace App\Filament\Reports\Widgets;

use App\Filament\Reports\Concerns\UsesReportPeriod;
use App\Filament\Resources\Products\ProductResource;
use App\Support\Reports\ReportMetrics;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Produk paling banyak dilihat dan berapa persen pengunjungnya lanjut ke toko; rasio rendah = cek harga, foto, atau deskripsi. */
class ProductConversionTable extends TableWidget
{
    use UsesReportPeriod;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Konversi produk: dilihat → diklik')
            ->description('10 produk paling banyak dilihat. Konversi rendah biasanya berarti harga, foto, atau deskripsi perlu diperbaiki.')
            ->records(fn () => ReportMetrics::productConversion($this->period())->all())
            ->paginated(false)
            ->emptyStateHeading('Belum ada tayangan produk di periode ini')
            ->emptyStateIcon('heroicon-o-eye')
            ->recordUrl(fn (array $record) => ProductResource::getUrl('edit', ['record' => $record['product_id']]))
            ->headerActions([$this->exportAction('konversi-produk', [
                'name' => 'Produk', 'category' => 'Kategori', 'views' => 'Dilihat', 'clicks' => 'Klik', 'rate' => 'Konversi (%)',
            ], fn () => ReportMetrics::productConversion($this->period(), 500))])
            ->columns([
                TextColumn::make('name')->label('Produk')->weight('medium')->wrap()
                    ->description(fn (array $record) => $record['category']),
                TextColumn::make('views')->label('Dilihat')->numeric()->alignEnd()->weight('bold'),
                TextColumn::make('clicks')->label('Klik')->numeric()->alignEnd(),
                TextColumn::make('rate')->label('Konversi')->alignEnd()->badge()
                    ->formatStateUsing(fn (float $state) => number_format($state, 1, ',', '.').'%')
                    ->color(fn (float $state) => match (true) {
                        $state >= 10 => 'success',
                        $state >= 3 => 'warning',
                        default => 'danger',
                    }),
            ]);
    }
}
