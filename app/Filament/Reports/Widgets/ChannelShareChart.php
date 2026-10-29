<?php

namespace App\Filament\Reports\Widgets;

use App\Filament\Reports\Concerns\UsesReportPeriod;
use App\Support\Reports\ReportMetrics;
use Filament\Widgets\ChartWidget;

/** Porsi klik per kanal beli (Shopee, Tokopedia, Lazada, TikTok Shop, WhatsApp). */
class ChannelShareChart extends ChartWidget
{
    use UsesReportPeriod;

    protected ?string $heading = 'Klik per kanal';

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 1];

    protected static bool $isLazy = false;

    protected ?string $maxHeight = '280px';

    /** Warna resmi kanal agar mudah dikenali; kanal lain memakai abu-abu. */
    private const COLORS = ['Shopee' => '#ee4d2d', 'Tokopedia' => '#03ac0e', 'Lazada' => '#0f146d', 'TikTok Shop' => '#111111', 'WhatsApp' => '#25d366'];

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $rows = ReportMetrics::channelShares($this->period());

        return [
            'datasets' => [[
                'data' => $rows->pluck('clicks')->all(),
                'backgroundColor' => $rows->map(fn (array $row) => self::COLORS[$row['name']] ?? '#94a3b8')->all(),
            ]],
            'labels' => $rows->pluck('name')->all(),
        ];
    }
}
