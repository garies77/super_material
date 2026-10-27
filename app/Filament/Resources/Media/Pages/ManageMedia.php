<?php

namespace App\Filament\Resources\Media\Pages;

use App\Filament\Resources\Media\MediaResource;
use App\Filament\Support\DescriptiveFileName;
use App\Filament\Support\LibraryFileUpload;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ManageMedia extends ManageRecords
{
    protected static string $resource = MediaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Berkas disimpan & didaftarkan oleh penyimpan global FileUpload (MediaLibrary::storeFileUpload)
            Action::make('upload')->label('Unggah gambar')->icon(Heroicon::OutlinedArrowUpTray)
                ->modalHeading('Unggah ke Pustaka Media')
                ->modalSubmitActionLabel('Simpan ke pustaka')
                ->schema([
                    LibraryFileUpload::make('files')->label('Gambar')->multiple()->required()
                        ->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'])->maxSize(4096)->maxFiles(50)
                        ->disk('public')->directory('media')->visibility('public')
                        ->getUploadedFileNameForStorageUsing(fn (TemporaryUploadedFile $file) => DescriptiveFileName::for(
                            $file, pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 'media'
                        ))
                        ->helperText('JPG, PNG, WebP, atau SVG, maks. 4 MB per berkas. Beri nama berkas yang deskriptif (mis. semen-gresik-40kg.jpg) agar mudah dicari. Gambar yang isinya sama persis dengan gambar di pustaka tidak disimpan dua kali.'),
                ])
                ->action(function (array $data): void {
                    Notification::make()->title(count($data['files'] ?? []).' gambar tersedia di pustaka')->success()->send();
                }),
        ];
    }
}
