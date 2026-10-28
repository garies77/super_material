<?php

namespace App\Filament\Support;

use App\Models\Media;
use App\Rules\MinImagePixels;
use App\Support\MediaLibrary;
use Filament\Actions\Action;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use WeakMap;

/**
 * Tombol "Pilih dari Media" untuk field gambar. Pada FileUpload, path terpilih dimasukkan ke state
 * unggahan (galeri: ditambahkan, tunggal: diganti). Pada TextInput path, nilainya diganti dan
 * modal menyediakan unggahan baru karena field teks tidak bisa mengunggah sendiri.
 *
 * Field dengan ukuran baku (ImagePreset): gambar yang rasionya pas tampil paling atas; gambar lain
 * dibuatkan salinan terpotong tengah agar tampilan section tetap konsisten.
 */
class MediaPicker
{
    public const LIMIT = 60;

    /** @var WeakMap<Component, string>|null field teks alternatif pendamping per field gambar (lihat macro altTextField) */
    private static ?WeakMap $altTextFields = null;

    /** Tautkan field gambar ke field teks alternatif di sebelahnya (path relatif terhadap container yang sama). */
    public static function rememberAltTextField(Component $component, string $field): void
    {
        self::$altTextFields ??= new WeakMap;
        self::$altTextFields[$component] = $field;
    }

    public static function action(?string $directory = null, int $minPixels = 0, ?string $preset = null, ?string $altTextField = null): Action
    {
        return Action::make('pickFromMediaLibrary')
            ->label('Pilih dari Media')
            ->icon(Heroicon::OutlinedPhoto)
            ->modalHeading('Pustaka Media')
            ->modalDescription(fn (Component $component) => ($ratios = self::targetRatioLabels($component, $preset))
                ? 'Section ini memakai rasio '.implode(' atau ', $ratios).'. Gambar bertanda "Pas" langsung dipakai; gambar lain dipotong otomatis menjadi salinan baru.'
                : 'Pilih gambar yang sudah pernah diunggah agar tidak perlu mengunggah ulang.')
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitActionLabel('Gunakan gambar')
            ->visible(fn (Component $component) => ! $component->isDisabled()
                && (! $component instanceof BaseFileUpload || $component->getDiskName() === Media::DISK))
            ->schema(function (Component $component) use ($minPixels, $preset): array {
                $ratios = self::targetRatios($component, $preset);

                $grid = MediaGrid::make('selected')->hiddenLabel()
                    ->multiple(self::isMultiple($component))
                    ->targetRatios($ratios)
                    ->items(fn (Get $get) => self::query($component, $ratios, $get('search'), $get('folder'))->limit(self::LIMIT)->get());

                // Mengetik/memilih folder hanya me-render ulang grid, bukan seluruh halaman edit di belakang modal
                $renderGridOnly = fn () => $grid->partiallyRender();

                $upload = LibraryFileUpload::make('upload')->label('Atau unggah gambar baru')
                    ->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(4096)->storeFiles(false)
                    ->rules($minPixels > 0 ? [new MinImagePixels($minPixels)] : [])
                    ->helperText($minPixels > 0 ? 'Minimal '.number_format($minPixels, 0, ',', '.').' piksel. Gambar baru otomatis masuk Pustaka Media.' : 'Gambar baru otomatis masuk Pustaka Media.')
                    ->visible(! $component instanceof BaseFileUpload);

                return [
                    Grid::make(3)->schema([
                        TextInput::make('search')->hiddenLabel()->placeholder('Cari nama berkas atau teks alternatif…')
                            ->prefixIcon(Heroicon::MagnifyingGlass)->live(debounce: 400)->afterStateUpdated($renderGridOnly)->columnSpan(2),
                        Select::make('folder')->hiddenLabel()->placeholder('Semua folder')->live()->afterStateUpdated($renderGridOnly)
                            ->options(fn () => Media::query()->distinct()->orderBy('folder')->pluck('folder')
                                ->mapWithKeys(fn (string $folder) => [$folder => Media::FOLDERS[$folder] ?? $folder])),
                    ]),
                    $grid,
                    $preset ? ImagePreset::apply($upload, $preset) : $upload,
                ];
            })
            ->action(function (array $data, Component $component, Get $get, Set $set) use ($directory, $preset, $altTextField): void {
                $media = self::selectedMedia($data['selected'] ?? []);

                if (($upload = $data['upload'] ?? null) instanceof TemporaryUploadedFile) {
                    $path = MediaLibrary::storeUploadedFile($upload, $directory ?? 'media', pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME));
                    $media[] = Media::firstWhere('path', $path);
                }

                $media = array_values(array_filter($media));

                if ($media === []) {
                    Notification::make()->title('Belum ada gambar yang dipilih')->warning()->send();

                    return;
                }

                self::apply($component, self::fitToSection($component, $preset, $media));

                // Teks alternatif dari pustaka hanya mengisi field yang masih kosong, tidak menimpa tulisan admin
                $altField = $altTextField ?? self::$altTextFields[$component] ?? null;
                $altText = collect($media)->map->alt_text->first(fn (?string $alt) => filled($alt));
                if ($altField && $altText && blank($get($altField))) {
                    $set($altField, $altText);
                }
            });
    }

    /**
     * Hanya gambar yang benar-benar terdaftar di pustaka yang diterima (state grid dikirim dari browser),
     * dalam urutan klik.
     *
     * @return array<int, Media>
     */
    private static function selectedMedia(mixed $selected): array
    {
        $requested = collect(is_array($selected) ? $selected : [])->filter(fn ($path) => is_string($path))->unique()->values();
        $known = Media::whereIn('path', $requested)->get()->keyBy('path');

        return $requested->map(fn (string $path) => $known->get($path))->filter()->values()->all();
    }

    /**
     * Gambar yang rasionya tidak sesuai section dibuatkan salinan terpotong.
     *
     * @param  array<int, Media>  $media
     * @return array<int, string>
     */
    private static function fitToSection(Component $component, ?string $preset, array $media): array
    {
        $ratios = self::targetRatios($component, $preset);
        $required = count($ratios) === 1 ? $ratios[0] : null;
        $cropped = [];

        $paths = collect($media)->map(function (Media $item) use ($required, $component, $preset, &$cropped): string {
            if ($required === null || $item->matchesRatio($required)) {
                return $item->path;
            }

            $fitted = MediaLibrary::cropToRatio($item, $required, self::targetWidth($component, $preset));
            if ($fitted->isNot($item)) {
                $cropped[] = $item->name;
            }

            return $fitted->path;
        })->all();

        if ($cropped !== []) {
            Notification::make()->title(count($cropped).' gambar dipotong otomatis ke rasio '.self::targetRatioLabels($component, $preset)[0])
                ->body($component instanceof FileUpload
                    ? 'Potongan diambil dari bagian tengah dan disimpan sebagai gambar baru. Klik ikon pensil pada gambar bila ingin menggeser area potongan.'
                    : 'Potongan diambil dari bagian tengah dan disimpan sebagai gambar baru. Gambar asli tetap utuh di pustaka.')
                ->info()->send();
        }

        return $paths;
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

    /**
     * Rasio wajib section dalam bentuk "lebar:tinggi": dari FileUpload ber-preset, atau preset yang diberikan
     * untuk field path teks.
     *
     * @return array<int, string>
     */
    private static function targetRatioLabels(Component $component, ?string $preset): array
    {
        $ratio = $component instanceof FileUpload ? $component->getImageAspectRatio() : (ImagePreset::get($preset)['ratio'] ?? null);

        return array_values(array_filter((array) $ratio));
    }

    /** @return array<int, float> */
    private static function targetRatios(Component $component, ?string $preset): array
    {
        return collect(self::targetRatioLabels($component, $preset))
            ->map(fn (string $ratio) => ImagePreset::singleRatio(['ratio' => $ratio]))
            ->filter()->values()->all();
    }

    private static function targetWidth(Component $component, ?string $preset): ?int
    {
        $width = $component instanceof FileUpload ? $component->getAutomaticallyResizeImagesWidth() : (ImagePreset::get($preset)['width'] ?? null);

        return filled($width) ? (int) $width : null;
    }

    /**
     * @param  array<int, float>  $ratios
     * @return Builder<Media>
     */
    private static function query(Component $component, array $ratios, ?string $search, ?string $folder): Builder
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
            // Gambar yang rasionya sudah pas dengan section tampil lebih dulu
            ->when($ratios !== [], fn (Builder $q) => $q->orderByRaw(
                'CASE WHEN '.implode(' OR ', array_fill(0, count($ratios), '(width IS NOT NULL AND height > 0 AND ABS(width - height * ?) <= height * ? * ?)')).' THEN 0 ELSE 1 END',
                collect($ratios)->flatMap(fn (float $ratio) => [$ratio, $ratio, Media::RATIO_TOLERANCE])->all(),
            ))
            ->latest('id');
    }
}
