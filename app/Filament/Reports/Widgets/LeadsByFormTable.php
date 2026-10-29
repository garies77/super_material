<?php

namespace App\Filament\Reports\Widgets;

use App\Filament\Reports\Concerns\UsesReportPeriod;
use App\Support\Reports\ReportMetrics;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Lead per formulir dan status tindak lanjutnya (angka saja, tanpa data pribadi pengirim). */
class LeadsByFormTable extends TableWidget
{
    use UsesReportPeriod;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 1];

    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Lead formulir')
            ->description('Spam tidak dihitung.')
            ->records(fn () => ReportMetrics::leadsByForm($this->period())->all())
            ->paginated(false)
            ->emptyStateHeading('Belum ada lead')
            ->emptyStateIcon('heroicon-o-inbox')
            ->headerActions([$this->exportAction('lead-formulir', ['form' => 'Formulir', 'total' => 'Total', 'new' => 'Baru', 'contacted' => 'Dihubungi', 'done' => 'Selesai'],
                fn () => ReportMetrics::leadsByForm($this->period()))])
            ->columns([
                TextColumn::make('form')->label('Formulir')->weight('medium')->wrap()
                    ->description(fn (array $record) => "Baru {$record['new']} · Dihubungi {$record['contacted']} · Selesai {$record['done']}"),
                TextColumn::make('total')->label('Lead')->numeric()->alignEnd()->weight('bold'),
            ]);
    }
}
