<?php

namespace App\Filament\Widgets;

use App\Models\Category;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class TopCategories extends TableWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'half';

    protected static ?string $heading = 'Kategori penghasil klik (30 hari)';

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function table(Table $table): Table
    {
        $since = now()->subDays(30);

        return $table
            ->query(
                Category::query()
                    ->join('products', 'categories.id', '=', 'products.category_id')
                    ->join('click_events', 'products.id', '=', 'click_events.product_id')
                    ->where('click_events.is_bot', false)
                    ->where('click_events.created_at', '>=', $since)
                    ->groupBy('categories.id', 'categories.name')
                    ->selectRaw('categories.id, categories.name, count(click_events.id) as clicks_count')
                    ->orderByDesc('clicks_count')
            )
            ->paginated([5])
            ->columns([
                TextColumn::make('name')->label('Kategori'),
                TextColumn::make('clicks_count')->label('Total Klik')->sortable(),
            ]);
    }
}
