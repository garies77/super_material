<?php

namespace App\Filament\Resources\Authors\Pages;

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Resources\Authors\AuthorResource;
use App\Filament\Support\PageActions;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

class EditAuthor extends EditRecord
{
    protected static string $resource = AuthorResource::class;

    // Form dua kolom memakai lebar konten penuh; tombol simpan menempel di bawah layar saat menggulir
    protected Width|string|null $maxContentWidth = Width::Full;

    public static bool $formActionsAreSticky = true;

    /** Penulis & Kontributor mengubah profil penulisnya di halaman Profil akun (satu foto, satu tempat). */
    protected function authorizeAccess(): void
    {
        if (auth()->user()?->writesAsOwnAuthor()) {
            $this->redirect(EditProfile::getUrl());

            return;
        }

        parent::authorizeAccess();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewPublic')->label('Lihat profil publik')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                ->url(fn () => $this->record->url(), shouldOpenInNewTab: true)
                ->visible(fn () => $this->record->is_active)
                ->tooltip('Tampilan profil ini bagi pembaca situs'),
            PageActions::more([DeleteAction::make()->visible(fn () => AuthorResource::canDelete($this->record))])
                ->visible(fn () => AuthorResource::canDelete($this->record)),
        ];
    }
}
