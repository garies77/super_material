<?php

namespace App\Filament\Reports\Widgets;

use App\Filament\Reports\Concerns\UsesReportPeriod;
use App\Models\SearchSynonym;
use App\Support\Reports\ReportMetrics;
use Filament\Actions\Action;
use Filament\Forms\Components\TagsInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Pencarian tanpa hasil: produk yang dicari tapi belum ada, atau istilah yang bisa langsung diberi sinonim. */
class ZeroResultSearchesTable extends TableWidget
{
    use UsesReportPeriod;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 1];

    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        $synonyms = SearchSynonym::map();

        return $table
            ->heading('Dicari tapi tidak ketemu')
            ->description('Beri sinonim agar pencarian berikutnya menemukan produk, atau jadikan ide produk/artikel baru.')
            ->records(fn () => ReportMetrics::zeroResultSearches($this->period())->all())
            ->paginated(false)
            ->emptyStateHeading('Semua pencarian menemukan hasil')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->recordUrl(fn (array $record) => route('search', ['q' => $record['term']]), shouldOpenInNewTab: true)
            ->headerActions([$this->exportAction('pencarian-tanpa-hasil', ['term' => 'Kata kunci', 'searches' => 'Pencarian'],
                fn () => ReportMetrics::zeroResultSearches($this->period(), 500))])
            ->columns([
                TextColumn::make('term')->label('Kata kunci')->weight('medium')->wrap()
                    ->description(fn (array $record) => isset($synonyms[$record['term']]) ? 'Sinonim: '.implode(', ', $synonyms[$record['term']]) : null),
                TextColumn::make('searches')->label('Pencarian')->numeric()->alignEnd()->weight('bold'),
            ])
            ->recordActions([
                Action::make('addSynonym')->label('Tambah sinonim')->tooltip('Tambah sinonim')->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->iconButton()->color('primary')
                    ->hidden(fn (array $record) => isset($synonyms[$record['term']]))
                    ->modalHeading(fn (array $record) => 'Sinonim untuk "'.$record['term'].'"')
                    ->modalDescription('Pengunjung yang mencari kata ini juga akan melihat hasil untuk kata di bawah.')
                    ->modalSubmitActionLabel('Simpan sinonim')
                    ->schema([
                        TagsInput::make('synonyms')->label('Cari juga sebagai')->required()->placeholder('cth: bata ringan')
                            ->splitKeys([','])->nestedRecursiveRules(['max:100']),
                    ])
                    ->action(function (array $record, array $data): void {
                        SearchSynonym::updateOrCreate(['term' => $record['term']], ['synonyms' => $data['synonyms']]);
                        Notification::make()->title('Sinonim disimpan')->body('Coba cari "'.$record['term'].'" di situs.')->success()->send();
                    }),
            ]);
    }
}
