<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\Pages\PageResource;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreatePage extends CreateRecord
{
    use HasPageEditorActions;

    protected static string $resource = PageResource::class;

    // Form dua kolom memakai lebar konten penuh; tombol simpan menempel di bawah layar saat menggulir
    protected Width|string|null $maxContentWidth = Width::Full;

    public static bool $formActionsAreSticky = true;

    protected static ?string $title = 'Buat Halaman';

    public function submitAs(string $intent): void
    {
        $this->intent = $intent;
        $this->create();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Halaman baru selalu dimulai sebagai draf; Publikasikan menerapkannya setelah tersimpan
        return $data + ['is_published' => false, 'updated_by' => auth()->id()];
    }

    protected function afterCreate(): void
    {
        if ($this->intent === 'publish') {
            $this->record->publish();
        }
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return $this->intentNotification();
    }

    protected function getRedirectUrl(): string
    {
        return $this->intent === 'preview'
            ? route('page.preview', $this->record)
            : static::getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
