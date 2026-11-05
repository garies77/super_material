<?php

namespace App\Filament\Resources\Authors\Pages;

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Resources\Authors\AuthorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAuthors extends ListRecords
{
    protected static string $resource = AuthorResource::class;

    /** Penulis & Kontributor hanya punya satu profil, diubah di halaman Profil akun: tanpa tabel. */
    public function mount(): void
    {
        if (auth()->user()?->writesAsOwnAuthor()) {
            $this->redirect(EditProfile::getUrl());

            return;
        }

        parent::mount();
    }

    protected function getHeaderActions(): array
    {
        // Penulis & Kontributor sudah punya profil otomatis dan hanya boleh mengubahnya, tidak membuat penulis baru
        return [CreateAction::make()->visible(fn () => AuthorResource::canCreate())];
    }
}
