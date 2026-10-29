<?php

namespace App\Filament\Reports\Widgets;

use App\Filament\Reports\Concerns\UsesReportPeriod;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Support\Reports\ReportMetrics;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Produk terbit tanpa satu klik pun dalam periode: kandidat diperbaiki (foto, harga, deskripsi) atau dipromosikan. */
class ZeroClickProductsTable extends TableWidget
{
    use UsesReportPeriod;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Produk tanpa klik')
            ->description('Produk terbit yang tidak diklik sama sekali di periode ini. Perbaiki foto, harga, atau promosikan.')
            ->query(fn () => ReportMetrics::zeroClickProducts($this->period())->with('category'))
            ->defaultSort('updated_at')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('Semua produk terbit mendapat klik')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->recordUrl(fn (Product $record) => ProductResource::getUrl('edit', ['record' => $record]))
            ->headerActions([$this->exportAction('produk-tanpa-klik', ['name' => 'Produk', 'category' => 'Kategori'],
                fn () => ReportMetrics::zeroClickProducts($this->period())->with('category')->get()
                    ->map(fn (Product $p) => ['name' => $p->name, 'category' => $p->category?->name]))])
            ->columns([
                TextColumn::make('name')->label('Produk')->weight('medium')->searchable(),
                TextColumn::make('category.name')->label('Kategori')->color('gray'),
                TextColumn::make('updated_at')->label('Terakhir diubah')->since()->sortable(),
            ]);
    }
}
