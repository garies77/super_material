<?php

namespace App\Filament\Resources\Media\Pages;

use App\Filament\Resources\Media\MediaResource;
use App\Filament\Support\DescriptiveFileName;
use App\Filament\Support\LibraryFileUpload;
use App\Models\Media;
use App\Support\MediaUsage;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ManageMedia extends ManageRecords
{
    protected static string $resource = MediaResource::class;

    /** Mode pilih untuk hapus: klik kartu menandai gambar (bingkai merah) alih-alih membuka detail. */
    public bool $isSelectingMedia = false;

    /**
     * Diisi di browser (entangle tunda) saat kartu diklik; baru terkirim ke server bersama permintaan "Hapus terpilih".
     *
     * @var array<int, int|string>
     */
    public array $selectedMediaIds = [];

    /**
     * Tambahkan semua gambar yang tidak dipakai konten (seluruh pustaka, bukan hanya halaman ini) ke pilihan.
     * Grid tidak di-render ulang; hanya daftar ID yang dikirim balik ke browser.
     */
    public function selectAllUnusedMedia(): void
    {
        if (! $this->isSelectingMedia || ! MediaResource::canSelectMediaToDelete()) {
            return;
        }

        // Kontributor/Penulis: hanya unggahannya sendiri
        $unused = Media::whereNotIn('id', MediaUsage::usedIds())
            ->unless(MediaResource::canDeleteMedia(), fn ($query) => $query->where('uploaded_by', auth()->id()))
            ->pluck('id')->all();
        $this->selectedMediaIds = array_values(array_unique([...array_map('intval', $this->selectedMediaIds), ...$unused]));

        Notification::make()->title(count($unused).' gambar yang belum dipakai ditambahkan ke pilihan')->success()->send();
        $this->skipRender();
    }

    /** Skrip & bilah penghitung mode pilih; tanpa request ke server per klik kartu. */
    public function getFooter(): ?View
    {
        return view('filament.media.select-mode');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('deleteSelected')
                ->label('Hapus terpilih')
                ->icon(Heroicon::OutlinedTrash)->color('danger')
                ->visible(fn () => $this->isSelectingMedia && MediaResource::canSelectMediaToDelete())
                ->requiresConfirmation()
                ->modalHeading(fn () => 'Hapus '.count($this->selectedMediaIds).' gambar terpilih?')
                ->modalDescription('Berkas dihapus permanen dari server. Gambar yang masih dipakai konten otomatis dilewati.')
                ->action(function (): void {
                    $ids = array_values(array_filter(array_map('intval', $this->selectedMediaIds)));

                    if ($ids === []) {
                        Notification::make()->title('Belum ada gambar yang dipilih')->body('Klik gambar yang ingin dihapus dulu.')->warning()->send();

                        return;
                    }

                    $result = MediaResource::deleteUnused(Media::whereKey($ids)->get());

                    Notification::make()->title($result['deleted'].' gambar dihapus')
                        ->body(collect([
                            $result['kept'] ? $result['kept'].' gambar dilewati karena masih dipakai konten.' : null,
                            $result['notOwned'] ? $result['notOwned'].' gambar dilewati karena bukan unggahan Anda.' : null,
                        ])->filter()->implode(' ') ?: null)
                        ->success()->send();

                    $this->isSelectingMedia = false;
                    $this->selectedMediaIds = [];
                }),

            Action::make('select')
                ->label(fn () => $this->isSelectingMedia ? 'Batal' : 'Pilih untuk dihapus')
                ->icon(fn () => $this->isSelectingMedia ? Heroicon::OutlinedXMark : Heroicon::OutlinedCursorArrowRipple)
                ->color('gray')
                ->visible(fn () => MediaResource::canSelectMediaToDelete())
                ->action(function (): void {
                    $this->isSelectingMedia = ! $this->isSelectingMedia;
                    $this->selectedMediaIds = [];
                }),

            // Berkas disimpan & didaftarkan oleh penyimpan global FileUpload (MediaLibrary::storeFileUpload)
            Action::make('upload')->label('Unggah gambar')->icon(Heroicon::OutlinedArrowUpTray)
                ->visible(fn () => ! $this->isSelectingMedia)
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

    public function getSubheading(): ?string
    {
        return $this->isSelectingMedia
            ? 'Mode pilih: klik gambar yang ingin dihapus (bingkai merah), lalu klik "Hapus terpilih". Gambar yang masih dipakai konten tidak akan terhapus.'
            : null;
    }
}
