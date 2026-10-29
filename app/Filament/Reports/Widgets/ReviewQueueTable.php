<?php

namespace App\Filament\Reports\Widgets;

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Products\ProductResource;
use App\Support\Reports\TeamMetrics;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Carbon;

/** Kiriman yang sedang menunggu tinjauan (tidak terikat periode), terlama di atas. */
class ReviewQueueTable extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Antrean tinjauan saat ini')
            ->description('Klik untuk membuka dan meninjau. Usahakan tidak ada yang menunggu lebih dari 2 hari.')
            ->records(fn () => TeamMetrics::queue()->all())
            ->paginated(false)
            ->emptyStateHeading('Antrean kosong')
            ->emptyStateDescription('Semua kiriman sudah ditinjau.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->recordUrl(fn (array $record) => $record['type'] === 'Artikel'
                ? ArticleResource::getUrl('edit', ['record' => $record['id']])
                : ProductResource::getUrl('edit', ['record' => $record['id']]))
            ->columns([
                TextColumn::make('title')->label('Judul')->weight('medium')->wrap()
                    ->description(fn (array $record) => $record['type']),
                TextColumn::make('submitter')->label('Diajukan oleh'),
                TextColumn::make('submitted_at')->label('Menunggu')->alignEnd()->badge()
                    ->formatStateUsing(fn (?Carbon $state) => $state ? TeamMetrics::formatHours($state->diffInMinutes(now()) / 60) : '—')
                    ->color(fn (?Carbon $state) => match (true) {
                        $state === null => 'gray',
                        $state->diffInHours(now()) >= 48 => 'danger',
                        $state->diffInHours(now()) >= 24 => 'warning',
                        default => 'success',
                    }),
            ]);
    }
}
