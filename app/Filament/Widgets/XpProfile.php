<?php

namespace App\Filament\Widgets;

use App\Models\XpEvent;
use App\Support\Gamification;
use Filament\Widgets\Widget;

/** Kartu gamifikasi di dashboard: level, XP, lencana, dan riwayat XP terbaru milik pengguna ini. */
class XpProfile extends Widget
{
    protected string $view = 'filament.widgets.xp-profile';

    protected static ?int $sort = -4;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    /** Kontributor selalu; peran lain hanya bila pernah mendapat XP. */
    public static function canView(): bool
    {
        $user = auth()->user();

        return $user && ($user->isContributor() || XpEvent::where('user_id', $user->id)->exists());
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $user = auth()->user();

        return [
            'level' => Gamification::level(Gamification::totalXp($user)),
            'badges' => Gamification::badges($user),
            'history' => XpEvent::active()->where('user_id', $user->id)->latest()->limit(5)->get(),
        ];
    }
}
