<?php

namespace App\Filament\Resources\Pages\Pages;

use Filament\Actions\Action;

/**
 * Tombol editor halaman: Simpan Draft, Publikasikan, Preview. Semuanya menyimpan isi editor dulu
 * sehingga konten tidak hilang; Preview menampilkan draf tanpa mengubah halaman publik.
 */
trait HasPageEditorActions
{
    /** Peringatan saat meninggalkan editor dengan perubahan yang belum disimpan. */
    protected function hasUnsavedDataChangesAlert(): bool
    {
        return true;
    }

    public string $intent = 'draft';

    protected function getFormActions(): array
    {
        return [
            Action::make('saveDraft')->label('Simpan Draft')->icon('heroicon-o-document-check')
                ->action(fn () => $this->submitAs('draft'))->keyBindings(['mod+s']),
            Action::make('publish')->label('Publikasikan')->icon('heroicon-o-globe-alt')->color('success')
                ->requiresConfirmation()->modalHeading('Publikasikan halaman?')
                ->modalDescription('Isi draf akan tampil di halaman publik.')
                ->action(fn () => $this->submitAs('publish')),
            Action::make('preview')->label('Preview')->icon('heroicon-o-eye')->color('gray')
                ->action(fn () => $this->submitAs('preview')),
            $this->getCancelFormAction(),
        ];
    }

    protected function intentNotification(): string
    {
        return match ($this->intent) {
            'publish' => 'Halaman dipublikasikan',
            'preview' => 'Draf disimpan, membuka preview',
            default => 'Draf disimpan',
        };
    }
}
