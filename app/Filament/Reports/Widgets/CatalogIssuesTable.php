<?php

namespace App\Filament\Reports\Widgets;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Support\Reports\CatalogHealth;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** Daftar kerja: produk terbit yang masih punya kekurangan data, bisa disaring per jenis kekurangan. */
class CatalogIssuesTable extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Produk yang perlu dilengkapi')
            ->description('Klik produk untuk melengkapinya. Cocok dijadikan tugas Kontributor.')
            ->query(fn () => CatalogHealth::productsWithIssues()->with('category:id,name')->withCount('media'))
            ->defaultSort('updated_at')
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('Semua produk terbit sudah lengkap')
            ->emptyStateIcon('heroicon-o-sparkles')
            ->recordUrl(fn (Product $record) => ProductResource::getUrl('edit', ['record' => $record]))
            ->filters([
                SelectFilter::make('issue')->label('Kekurangan')->options(CatalogHealth::ISSUES)
                    ->query(fn (Builder $query, array $data) => filled($data['value']) ? CatalogHealth::applyIssue($query, $data['value']) : $query),
            ])
            ->columns([
                TextColumn::make('name')->label('Produk')->weight('medium')->wrap()
                    ->description(fn (Product $record) => $record->category?->name),
                TextColumn::make('issues')->label('Kekurangan')->badge()->color('warning')
                    ->state(fn (Product $record) => CatalogHealth::issuesOf($record)),
                TextColumn::make('updated_at')->label('Terakhir diubah')->since()->sortable()->color('gray'),
            ]);
    }
}
