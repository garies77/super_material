<?php

namespace App\Filament\Support;

use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Icons\Heroicon;

/**
 * Hierarki tombol header halaman admin yang seragam:
 * satu aksi utama berwarna (Simpan/Buat/Ajukan/Setujui), aksi sekunder abu-abu,
 * dan aksi berbahaya/jarang dipakai (Hapus, Duplikat, Jadikan draf) di menu "Aksi lain".
 */
class PageActions
{
    /** Tombol sekunder "Lihat di situs" (tab baru). */
    public static function viewOnSite(Closure $url, string $label = 'Lihat di situs'): Action
    {
        return Action::make('view')->label($label)->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
            ->url($url)->openUrlInNewTab();
    }

    /**
     * Menu "Aksi lain" berisi aksi yang tidak perlu selalu terlihat.
     *
     * @param  array<int, Action>  $actions
     */
    public static function more(array $actions): ActionGroup
    {
        return ActionGroup::make($actions)->label('Aksi lain')->tooltip('Aksi lain')
            ->icon(Heroicon::EllipsisHorizontal)->color('gray')->button()->hiddenLabel();
    }
}
