<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Produk kiriman Kontributor yang menunggu Editor/Admin; tampil paling atas dan hanya bila ada antrean. */
class PendingProductReviews extends TableWidget
{
    protected static ?int $sort = -6;

    protected int|string|array $columnSpan = 'half';

    // Tidak lazy: antrean tinjauan harus langsung terlihat saat dashboard dibuka
    protected static bool $isLazy = false;

    protected static ?string $heading = 'Produk menunggu tinjauan';

    public static function canView(): bool
    {
        return auth()->user()?->canReview() && Product::awaitingReview()->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Product::awaitingReview()->with('submitter')->oldest('submitted_at'))
            ->paginated([5])
            ->recordUrl(fn (Product $record) => ProductResource::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('name')->label('Produk')->limit(35),
                TextColumn::make('submitter.name')->label('Kontributor'),
                TextColumn::make('submitted_at')->label('Diajukan')->since(),
            ]);
    }
}
