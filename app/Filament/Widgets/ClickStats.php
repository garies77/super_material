<?php

namespace App\Filament\Widgets;

use App\Models\ClickEvent;
use App\Models\SalesChannel;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ClickStats extends StatsOverviewWidget
{
    protected ?string $heading = 'Klik tombol beli (30 hari terakhir)';

    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    protected function getStats(): array
    {
        $since = now()->subDays(30);
        $base = ClickEvent::where('is_bot', false)->where('created_at', '>=', $since);

        $stats = [Stat::make('Total klik', number_format((clone $base)->count()))];

        $perChannel = (clone $base)->selectRaw('sales_channel_id, count(*) as c')->groupBy('sales_channel_id')->pluck('c', 'sales_channel_id');
        foreach (SalesChannel::orderBy('sort_order')->get() as $channel) {
            $stats[] = Stat::make($channel->name, number_format($perChannel[$channel->id] ?? 0));
        }

        return $stats;
    }
}
