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
            Action::make('saveDraft')->label('Simpan draf')->icon('heroicon-o-document-check')->color('gray')
                ->action(fn () => $this->submitAs('draft'))->keyBindings(['mod+s']),
            Action::make('publish')->label('Terbitkan')->icon('heroicon-o-globe-alt')
                ->requiresConfirmation()->modalHeading('Terbitkan halaman?')
                ->modalDescription('Isi draf akan tampil di halaman publik.')
                ->action(fn () => $this->submitAs('publish')),
            Action::make('preview')->label('Pratinjau')->icon('heroicon-o-eye')->color('gray')
                ->action(fn () => $this->submitAs('preview')),
            $this->getCancelFormAction(),
        ];
    }

    protected function intentNotification(): string
    {
        return match ($this->intent) {
            'publish' => 'Halaman diterbitkan',
            'preview' => 'Draf disimpan, membuka pratinjau',
            default => 'Draf disimpan',
        };
    }
}
