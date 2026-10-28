<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\Pages\PageResource;
use App\Filament\Support\PageActions;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditPage extends EditRecord
{
    use HasPageEditorActions;

    protected static string $resource = PageResource::class;

    // Form dua kolom memakai lebar konten penuh; tombol simpan menempel di bawah layar saat menggulir
    protected Width|string|null $maxContentWidth = Width::Full;

    public static bool $formActionsAreSticky = true;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Editor membuka salinan kerja; halaman lama tanpa draf memakai versi terbit
        $data['draft_blocks'] ??= $data['blocks'] ?? [];

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $data + ['updated_by' => auth()->id()];
    }

    public function submitAs(string $intent): void
    {
        $this->intent = $intent;
        $this->save(shouldRedirect: false, shouldSendSavedNotification: false);

        if ($intent === 'publish') {
            $this->record->publish();
        }

        Notification::make()->title($this->intentNotification())->success()->send();

        if ($intent === 'preview') {
            $this->redirect(route('page.preview', $this->record));
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view')->label('Lihat di situs')->icon('heroicon-o-arrow-top-right-on-square')->color('gray')
                ->visible(fn () => $this->record->is_published)
                ->url(fn () => $this->record->url())->openUrlInNewTab(),
            PageActions::more([
                Action::make('unpublish')->label('Jadikan draf')->icon('heroicon-o-eye-slash')->color('warning')
                    ->visible(fn () => $this->record->is_published)
                    ->requiresConfirmation()->modalDescription('Halaman tidak lagi tampil di situs sampai diterbitkan kembali.')
                    ->action(function () {
                        $this->record->update(['is_published' => false]);
                        Notification::make()->title('Halaman dijadikan draf')->success()->send();
                    }),
                DeleteAction::make(),
            ]),
        ];
    }
}
