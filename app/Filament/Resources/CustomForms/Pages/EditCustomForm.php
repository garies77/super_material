<?php

namespace App\Filament\Resources\CustomForms\Pages;

use App\Filament\Resources\CustomForms\CustomFormResource;
use App\Filament\Resources\FormSubmissions\FormSubmissionResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditCustomForm extends EditRecord
{
    protected static string $resource = CustomFormResource::class;

    // Form dua kolom memakai lebar konten penuh; tombol simpan menempel di bawah layar saat menggulir
    protected Width|string|null $maxContentWidth = Width::Full;

    public static bool $formActionsAreSticky = true;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('responses')->label('Lihat jawaban')->icon('heroicon-o-inbox')->color('gray')
                ->url(fn () => FormSubmissionResource::getUrl('index', ['filters' => ['custom_form_id' => ['value' => $this->record->id]]])),
            Action::make('open')->label('Buka halaman')->icon('heroicon-o-arrow-top-right-on-square')->color('gray')
                ->url(fn () => $this->record->url(), true)->visible(fn () => $this->record->is_active && $this->record->hasFullPage()),
            DeleteAction::make()->modalDescription('Formulir beserta semua jawabannya akan dihapus.'),
        ];
    }
}
