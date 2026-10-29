<?php

namespace App\Filament\Reports\Widgets;

use App\Filament\Reports\Concerns\UsesReportPeriod;
use App\Support\Reports\TeamMetrics;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Kiriman dan kualitas per Kontributor: siapa yang produktif dan siapa yang perlu dibimbing. */
class ContributorPerformanceTable extends TableWidget
{
    use UsesReportPeriod;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 2];

    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        $percent = fn (?float $state) => $state === null ? '—' : number_format($state, 1, ',', '.').'%';

        return $table
            ->heading('Kinerja kontributor')
            ->description('Skor = rata-rata skor kelengkapan saat disetujui. Tepat sekali = disetujui tanpa diminta perbaikan.')
            ->records(fn () => TeamMetrics::contributors($this->period())->all())
            ->paginated(false)
            ->emptyStateHeading('Belum ada kiriman di periode ini')
            ->emptyStateIcon('heroicon-o-user-group')
            ->headerActions([$this->exportAction('kinerja-kontributor', [
                'name' => 'Kontributor', 'submissions' => 'Kiriman', 'approved' => 'Disetujui', 'changes' => 'Diminta perbaikan',
                'score' => 'Rata-rata skor (%)', 'first_time_right' => 'Tepat sekali (%)', 'xp' => 'XP',
            ], fn () => TeamMetrics::contributors($this->period()))])
            ->columns([
                TextColumn::make('name')->label('Kontributor')->weight('medium'),
                TextColumn::make('submissions')->label('Kiriman')->numeric()->alignEnd(),
                TextColumn::make('approved')->label('Disetujui')->numeric()->alignEnd()->weight('bold'),
                TextColumn::make('changes')->label('Perbaikan')->numeric()->alignEnd()
                    ->color(fn (int $state) => $state > 0 ? 'warning' : 'gray'),
                TextColumn::make('score')->label('Skor')->alignEnd()->formatStateUsing($percent)->placeholder('—'),
                TextColumn::make('first_time_right')->label('Tepat sekali')->alignEnd()->badge()
                    ->formatStateUsing($percent)->placeholder('—')
                    ->color(fn (?float $state) => match (true) {
                        $state === null => 'gray',
                        $state >= 70 => 'success',
                        $state >= 40 => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('xp')->label('XP')->numeric()->alignEnd()->color('gray'),
            ]);
    }
}
