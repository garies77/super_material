<?php

namespace App\Filament\Reports\Widgets;

use App\Filament\Reports\Concerns\UsesReportPeriod;
use App\Filament\Resources\Products\ProductResource;
use App\Support\Reports\ReportMetrics;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** 10 produk paling banyak diklik, rincian per kanal, dan perubahan terhadap periode sebelumnya. */
class TopProductsTable extends TableWidget
{
    use UsesReportPeriod;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Produk paling banyak diklik')
            ->description('Klik tombol beli ke marketplace & WhatsApp, 10 teratas.')
            ->records(fn () => ReportMetrics::topProducts($this->period())->all())
            ->paginated(false)
            ->emptyStateHeading('Belum ada klik di periode ini')
            ->emptyStateIcon('heroicon-o-cursor-arrow-rays')
            ->recordUrl(fn (array $record) => ProductResource::getUrl('edit', ['record' => $record['product_id']]))
            ->headerActions([$this->exportAction('produk-terlaris', [
                'name' => 'Produk', 'category' => 'Kategori', 'clicks' => 'Klik', 'change' => 'Perubahan (%)', 'channels' => 'Per kanal',
            ], fn () => ReportMetrics::topProducts($this->period(), 100))])
            ->columns([
                // Rincian kanal sebagai baris kedua di bawah nama, bukan kolom sendiri yang membuat baris sangat tinggi
                TextColumn::make('name')->label('Produk')->weight('medium')->wrap()
                    ->description(fn (array $record) => $record['channels']),
                TextColumn::make('category')->label('Kategori')->color('gray')->wrap(),
                TextColumn::make('clicks')->label('Klik')->numeric()->alignEnd()->weight('bold'),
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
