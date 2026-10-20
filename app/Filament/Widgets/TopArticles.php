<?php

namespace App\Filament\Widgets;

use App\Models\Article;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class TopArticles extends TableWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'half';

    protected static ?string $heading = 'Artikel paling banyak dibaca';

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Article::published()->orderByDesc('views_count')
            )
            ->paginated([5])
            ->columns([
                TextColumn::make('title')->label('Artikel')->limit(35),
                TextColumn::make('categories.name')->label('Kategori')->badge(),
                TextColumn::make('views_count')->label('Dibaca')->numeric()->sortable(),
            ]);
    }
}
