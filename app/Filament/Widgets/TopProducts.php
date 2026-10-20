<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class TopProducts extends TableWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Produk dengan klik terbanyak (30 hari)';

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function table(Table $table): Table
    {
        $since = now()->subDays(30);

        return $table
            ->query(
                Product::query()
                    ->whereHas('clicks', fn ($q) => $q->where('is_bot', false)->where('created_at', '>=', $since))
                    ->withCount(['clicks as clicks_count' => fn ($q) => $q->where('is_bot', false)->where('created_at', '>=', $since)])
                    ->orderByDesc('clicks_count')
            )
            ->paginated([5, 10])
            ->columns([
                TextColumn::make('name')->label('Produk'),
                TextColumn::make('category.name')->label('Kategori'),
                TextColumn::make('clicks_count')->label('Klik')->sortable(),
            ]);
    }
}
