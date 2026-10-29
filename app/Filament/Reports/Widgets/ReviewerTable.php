<?php

namespace App\Filament\Reports\Widgets;

use App\Filament\Reports\Concerns\UsesReportPeriod;
use App\Support\Reports\TeamMetrics;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Keputusan tinjauan per Editor/Admin dan berapa lama kiriman menunggu sebelum diputuskan. */
class ReviewerTable extends TableWidget
{
    use UsesReportPeriod;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 1];

    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Peninjau')
            ->description('Median waktu dari diajukan sampai diputuskan.')
            ->records(fn () => TeamMetrics::reviewers($this->period())->all())
            ->paginated(false)
            ->emptyStateHeading('Belum ada tinjauan')
            ->emptyStateIcon('heroicon-o-clipboard-document-check')
            ->columns([
                // Kartu sepertiga lebar: jumlah tinjauan cukup di baris kedua agar kolom waktu tunggu tidak terpotong
                TextColumn::make('name')->label('Peninjau')->weight('medium')->wrap()
                    ->description(fn (array $record) => "{$record['reviews']} tinjauan: {$record['approved']} disetujui, {$record['changes']} perbaikan"),
                TextColumn::make('median_hours')->label('Waktu tunggu')->alignEnd()->weight('bold')
                    ->formatStateUsing(fn (?float $state) => TeamMetrics::formatHours($state))->placeholder('—'),
            ]);
    }
}
