<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Articles\ArticleResource;
use App\Models\Article;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Artikel kiriman Kontributor yang menunggu Editor/Admin; tampil paling atas dan hanya bila ada antrean. */
class PendingArticleReviews extends TableWidget
{
    protected static ?int $sort = -1;

    protected int|string|array $columnSpan = 'half';

    // Tidak lazy: antrean tinjauan harus langsung terlihat saat dashboard dibuka
    protected static bool $isLazy = false;

    protected static ?string $heading = 'Artikel menunggu tinjauan';

    public static function canView(): bool
    {
        return auth()->user()?->canReview() && Article::awaitingReview()->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Article::awaitingReview()->with('submitter')->oldest('submitted_at'))
            ->paginated([5])
            ->recordUrl(fn (Article $record) => ArticleResource::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('title')->label('Artikel')->limit(35),
                TextColumn::make('submitter.name')->label('Kontributor'),
                TextColumn::make('submitted_at')->label('Diajukan')->since(),
            ]);
    }
}
