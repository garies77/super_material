<?php

namespace App\Filament\Widgets;

use App\Models\ClickEvent;
use Filament\Widgets\ChartWidget;

class ClickTrend extends ChartWidget
{
    protected ?string $heading = 'Tren klik harian';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public ?string $filter = '30';

    public static function canView(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    protected function getFilters(): ?array
    {
        return ['7' => '7 hari', '30' => '30 hari', '90' => '90 hari'];
    }

    protected function getData(): array
    {
        $days = (int) $this->filter;
        $rows = ClickEvent::where('is_bot', false)
            ->where('created_at', '>=', now()->subDays($days - 1)->startOfDay())
            ->get(['created_at'])
            ->groupBy(fn ($e) => $e->created_at->format('Y-m-d'))
            ->map->count();

        $labels = [];
        $values = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = now()->subDays($i);
            $labels[] = $d->format('d M');
            $values[] = $rows[$d->format('Y-m-d')] ?? 0;
        }

        return [
            'datasets' => [['label' => 'Klik', 'data' => $values, 'borderColor' => '#d97706', 'backgroundColor' => 'rgba(217,119,6,.15)', 'fill' => true]],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
