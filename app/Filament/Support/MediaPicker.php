<?php

namespace App\Filament\Support;

use App\Models\Media;
use App\Rules\MinImagePixels;
use App\Support\MediaLibrary;
use Filament\Actions\Action;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Tombol "Pilih dari Media" untuk field gambar. Pada FileUpload, path terpilih dimasukkan ke state
 * unggahan (galeri: ditambahkan, tunggal: diganti). Pada TextInput path, nilainya diganti dan
 * modal menyediakan unggahan baru karena field teks tidak bisa mengunggah sendiri.
 */
class MediaPicker
{
    public const LIMIT = 60;

    public static function action(?string $directory = null, int $minPixels = 0): Action
    {
        return Action::make('pickFromMediaLibrary')
            ->label('Pilih dari Media')
            ->icon(Heroicon::OutlinedPhoto)
            ->modalHeading('Pustaka Media')
            ->modalDescription('Pilih gambar yang sudah pernah diunggah agar tidak perlu mengunggah ulang.')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitActionLabel('Gunakan gambar')
            ->visible(fn (Component $component) => ! $component->isDisabled()
                && (! $component instanceof BaseFileUpload || $component->getDiskName() === Media::DISK))
            ->schema(function (Component $component) use ($minPixels): array {
                $grid = MediaGrid::make('selected')->hiddenLabel()
                    ->multiple(self::isMultiple($component))
                    ->items(fn (Get $get) => self::query($component, $get('search'), $get('folder'))->limit(self::LIMIT)->get());

                // Mengetik/memilih folder hanya me-render ulang grid, bukan seluruh halaman edit di belakang modal
                $renderGridOnly = fn () => $grid->partiallyRender();

                return [
                    Grid::make(3)->schema([
                        TextInput::make('search')->hiddenLabel()->placeholder('Cari nama berkas atau teks alternatif…')
                            ->prefixIcon(Heroicon::MagnifyingGlass)->live(debounce: 400)->afterStateUpdated($renderGridOnly)->columnSpan(2),
                        Select::make('folder')->hiddenLabel()->placeholder('Semua folder')->live()->afterStateUpdated($renderGridOnly)
                            ->options(fn () => Media::query()->distinct()->orderBy('folder')->pluck('folder')
                                ->mapWithKeys(fn (string $folder) => [$folder => Media::FOLDERS[$folder] ?? $folder])),
                    ]),
                    $grid,
                    LibraryFileUpload::make('upload')->label('Atau unggah gambar baru')
                        ->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(4096)->storeFiles(false)
                        ->rules($minPixels > 0 ? [new MinImagePixels($minPixels)] : [])
                        ->helperText($minPixels > 0 ? 'Minimal '.number_format($minPixels, 0, ',', '.').' piksel. Gambar baru otomatis masuk Pustaka Media.' : 'Gambar baru otomatis masuk Pustaka Media.')
                        ->visible(! $component instanceof BaseFileUpload),
                ];
            })
            ->action(function (array $data, Component $component) use ($directory): void {
                $paths = self::selectedPaths($data['selected'] ?? []);

                if (($upload = $data['upload'] ?? null) instanceof TemporaryUploadedFile) {
                    $folder = $directory ?? 'media';
                    $paths[] = MediaLibrary::storeUploadedFile($upload, $folder, pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME));
                }

                if ($paths === []) {
                    Notification::make()->title('Belum ada gambar yang dipilih')->warning()->send();

                    return;
                }

                self::apply($component, $paths);
            });
    }

    /**
     * Hanya path yang benar-benar terdaftar di pustaka yang diterima; state grid dikirim dari browser.
     *
     * @return array<int, string>
     */
    private static function selectedPaths(mixed $selected): array
    {
        $requested = collect(is_array($selected) ? $selected : [])->filter(fn ($path) => is_string($path))->values();
        $known = Media::whereIn('path', $requested)->pluck('path')->all();

        return $requested->filter(fn (string $path) => in_array($path, $known, true))->unique()->values()->all();
    }

    /** @param  array<int, string>  $paths */
    private static function apply(Component $component, array $paths): void
    {
        if ($component instanceof BaseFileUpload) {
            // State FileUpload berbentuk [uuid => path]; Filepond memuat ulang berkas saat state berubah
            $state = $component->isMultiple() ? array_filter((array) $component->getRawState()) : [];

            foreach ($component->isMultiple() ? $paths : [$paths[0]] as $path) {
                if (! in_array($path, $state, true)) {
                    $state[(string) Str::uuid()] = $path;
                }
            }

            $component->rawState($state);
        } else {
            $component->state($paths[0]);
        }

        $component->callAfterStateUpdated();
    }

    private static function isMultiple(Component $component): bool
    {
        return $component instanceof BaseFileUpload && $component->isMultiple();
    }

    /** @return Builder<Media> */
    private static function query(Component $component, ?string $search, ?string $folder): Builder
    {
        $accepted = $component instanceof BaseFileUpload ? ($component->getAcceptedFileTypes() ?? []) : [];
        $exactTypes = array_values(array_filter($accepted, fn (string $type) => ! str_ends_with($type, '/*')));

        return Media::query()
            ->when(filled($search), fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('path', 'like', "%{$search}%")
                ->orWhere('alt_text', 'like', "%{$search}%")))
            ->when(filled($folder), fn (Builder $q) => $q->where('folder', $folder))
            // Field dengan daftar tipe khusus (mis. logo kanal: SVG/PNG) hanya menampilkan tipe tersebut
            ->when($exactTypes !== [] && count($exactTypes) === count($accepted), fn (Builder $q) => $q->whereIn('mime_type', $exactTypes))
            ->latest('id');
    }
}
