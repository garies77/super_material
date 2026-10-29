<?php

namespace App\Filament\Reports\Widgets;

use App\Filament\Reports\Concerns\UsesReportPeriod;
use App\Support\Reports\ReportMetrics;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Klik yang membawa parameter UTM (iklan, tautan promosi, bio Instagram ber-UTM). UTM hanya tercatat bila pengunjung
 * menekan beli di halaman tempat ia mendarat; sumber tanpa UTM (Google organik, langsung) belum terlacak.
 */
class UtmCampaignsTable extends TableWidget
{
    use UsesReportPeriod;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 2];

    protected static bool $isLazy = false;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Kampanye & tautan promosi (UTM)')
            ->description('Klik dari tautan yang diberi utm_source, mis. ?utm_source=instagram&utm_campaign=promo-semen.')
            ->records(fn () => ReportMetrics::utmCampaigns($this->period())->all())
            ->paginated(false)
            ->emptyStateHeading('Belum ada klik ber-UTM')
            ->emptyStateDescription('Tambahkan ?utm_source=…&utm_campaign=… pada tautan yang dibagikan di Instagram, WhatsApp, atau iklan.')
            ->emptyStateIcon('heroicon-o-link')
            ->headerActions([$this->exportAction('kampanye-utm', ['source' => 'Sumber', 'medium' => 'Medium', 'campaign' => 'Kampanye', 'clicks' => 'Klik'],
                fn () => ReportMetrics::utmCampaigns($this->period()))])
            ->columns([
                TextColumn::make('source')->label('Sumber')->weight('medium'),
                TextColumn::make('medium')->label('Medium')->color('gray'),
                TextColumn::make('campaign')->label('Kampanye')->color('gray'),
                TextColumn::make('clicks')->label('Klik')->numeric()->alignEnd()->weight('bold'),
            ]);
    }
}
