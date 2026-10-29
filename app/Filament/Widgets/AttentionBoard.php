<?php

namespace App\Filament\Widgets;

use App\Support\AttentionBoard as Attention;
use Filament\Widgets\Widget;

/** Pengingat Editor/Admin di paling atas dashboard; hanya tampil bila ada yang perlu ditangani. */
class AttentionBoard extends Widget
{
    protected string $view = 'filament.widgets.attention-board';

    protected static ?int $sort = -7;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return ($user = auth()->user()) && Attention::for($user) !== [];
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return ['items' => Attention::for(auth()->user())];
    }
}
