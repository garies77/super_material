<?php

namespace App\Filament\Reports\Widgets;

use App\Models\Product;
use App\Support\Reports\CatalogHealth;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Jumlah produk terbit per jenis kekurangan data, dibanding potret seminggu lalu. */
class CatalogIssuesSummaryTable extends TableWidget
{
    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 1];

    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Kekurangan data produk')
            ->description('Produk terbit. Selisih dibanding minggu lalu.')
            ->records(fn () => $this->rows())
            ->paginated(false)
            ->columns([
                // Kartu sepertiga lebar: porsi di baris kedua dan judul kolom pendek agar tidak bergulir ke samping
                TextColumn::make('label')->label('Kekurangan')->wrap()
                    ->description(fn (array $record) => $record['share'].' produk terbit'),
                TextColumn::make('count')->label('Jml')->numeric()->alignEnd()->weight('bold')
                    ->color(fn (int $state) => $state > 0 ? null : 'success'),
                TextColumn::make('diff')->label('Δ')->tooltip('Selisih dibanding minggu lalu')->alignEnd()->badge()
                    ->formatStateUsing(fn (?int $state) => $state === null ? '—' : ($state > 0 ? '+' : '').$state)
                    ->color(fn (?int $state) => match (true) {
                        $state === null, $state === 0 => 'gray',
                        $state < 0 => 'success',
                        default => 'danger',
                    }),
            ]);
    }

    /** @return array<string, array{label: string, share: string, count: int, diff: ?int}> */
    private function rows(): array
    {
        $weekAgo = CatalogHealth::weekAgo()?->metrics['issues'] ?? null;
        $total = max(1, Product::published()->count());

        return collect(CatalogHealth::issueCounts())
            ->map(fn (int $count, string $issue) => [
                'label' => CatalogHealth::ISSUES[$issue],
                'share' => number_format($count / $total * 100, 0, ',', '.').'%',
                'count' => $count,
                'diff' => $weekAgo === null ? null : $count - (int) ($weekAgo[$issue] ?? 0),
            ])
            ->sortByDesc('count')
            ->all();
    }
}
