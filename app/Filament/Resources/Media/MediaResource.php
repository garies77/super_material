<?php

namespace App\Filament\Resources\Media;

use App\Filament\Resources\Media\Pages\ManageMedia;
use App\Filament\Support\FocalPointPicker;
use App\Filament\Support\ImagePreset;
use App\Models\AuditLog;
use App\Models\Media;
use App\Support\ImageDerivatives;
use App\Support\MediaLibrary;
use App\Support\MediaUsage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Pustaka Media: semua gambar di disk publik yang bisa dipakai ulang lewat tombol "Pilih dari Media".
 * Hapus hanya untuk Admin/Editor dan ditolak bila berkas masih dipakai konten (MediaUsage).
 */
class MediaResource extends Resource
{
    protected static ?string $model = Media::class;

    protected static ?string $modelLabel = 'Media';

    protected static ?string $pluralModelLabel = 'Pustaka Media';

    protected static ?string $navigationLabel = 'Media';

    protected static ?string $slug = 'media';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|\UnitEnum|null $navigationGroup = 'Konten';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->is_active;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama')->required()->maxLength(250)
                ->helperText('Untuk pencarian di pustaka. Nama berkas di server tidak berubah.'),
            TextInput::make('alt_text')->label('Teks alternatif')->maxLength(255)
                ->helperText('Deskripsi isi gambar untuk pembaca layar & SEO. Juga bisa dicari di pustaka.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Stack::make([
                    // data-media-id dibaca skrip mode pilih (filament/media/select-mode) untuk menandai kartu di browser
                    ImageColumn::make('thumbnail')->state(fn (Media $record) => $record->thumbnailUrl())
                        ->extraAttributes(fn (Media $record) => ['data-media-id' => $record->getKey()])
                        ->imageHeight(150)->extraImgAttributes(['loading' => 'lazy', 'class' => 'sm-media-thumb']),
                    TextColumn::make('name')->label('Nama')->weight('medium')->limit(40)
                        ->searchable(['name', 'path', 'alt_text']),
                    TextColumn::make('folder')->badge()->color('gray')->formatStateUsing(fn (Media $record) => $record->folderLabel()),
                    TextColumn::make('details')->color('gray')->size('xs')
                        ->state(fn (Media $record) => collect([$record->ratioLabel(), $record->dimensionsLabel(), $record->sizeLabel()])->filter()->implode(' · ')),
                ])->space(2),
            ])
            ->contentGrid(['md' => 2, 'lg' => 3, 'xl' => 4, '2xl' => 5])
            ->emptyStateIcon('heroicon-o-photo')
            ->emptyStateHeading('Pustaka media masih kosong')
            ->emptyStateDescription('Unggah gambar lewat tombol Unggah gambar, atau daftarkan gambar lama dengan perintah php artisan media:sync.')
            ->defaultSort('id', 'desc')
            ->paginated([24, 48, 96])
            ->filters([
                SelectFilter::make('folder')->label('Folder')
                    ->options(fn () => Media::query()->distinct()->orderBy('folder')->pluck('folder')
                        ->mapWithKeys(fn (string $folder) => [$folder => Media::FOLDERS[$folder] ?? $folder])),
                SelectFilter::make('usage')->label('Pemakaian')
                    ->options(['unused' => 'Belum dipakai', 'used' => 'Dipakai konten'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'unused' => $query->whereNotIn('id', MediaUsage::usedIds()),
                        'used' => $query->whereIn('id', MediaUsage::usedIds()),
                        default => $query,
                    }),
                SelectFilter::make('ratio')->label('Rasio')
                    ->options(array_combine(array_keys(Media::RATIOS), array_keys(Media::RATIOS)))
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null) && isset(Media::RATIOS[$data['value']])
                        ? $query->withRatio(Media::RATIOS[$data['value']])
                        : $query),
            ])
            // Klik kartu = Detail. Dalam mode pilih (ManageMedia) klik kartu ditangani di browser tanpa request ke server,
            // jadi kartu tidak memanggil aksi apa pun
            ->recordAction(fn (HasTable $livewire) => self::isSelecting($livewire) ? null : 'detail')
            // sm-media-card: tombol ⋮ di pojok kanan atas kartu (theme.css), bukan baris sendiri di bawah
            ->recordClasses(fn (HasTable $livewire) => ['sm-media-card', 'sm-media-card-selectable' => self::isSelecting($livewire)])
            ->recordActions([
                // Aksi lain di menu ⋮ agar kartu sempit tidak penuh tombol
                ActionGroup::make([
                    Action::make('detail')->label('Detail')->icon(Heroicon::OutlinedEye)
                        ->modalHeading(fn (Media $record) => $record->name)
                        ->modalWidth(Width::ThreeExtraLarge)
                        ->modalContent(fn (Media $record) => view('filament.media.detail', [
                            'media' => $record,
                            'usages' => MediaUsage::for($record),
                        ]))
                        ->modalSubmitAction(false)->modalCancelActionLabel('Tutup'),
                    EditAction::make()->label('Ubah info'),
                    Action::make('focus')->label('Titik fokus')->icon(Heroicon::OutlinedViewfinderCircle)
                        ->visible(fn (Media $record) => $record->isCroppable())
                        ->modalHeading('Tandai bagian penting gambar')
                        ->modalDescription('Klik bagian yang harus selalu terlihat (mis. produknya). Potongan otomatis ke rasio section akan berpusat di titik ini. Potongan yang sudah dipakai konten tidak berubah.')
                        ->modalWidth(Width::ThreeExtraLarge)
                        ->modalSubmitActionLabel('Simpan titik fokus')
                        ->fillForm(fn (Media $record) => ['focal' => ['x' => $record->focal_x, 'y' => $record->focal_y]])
                        ->schema([
                            FocalPointPicker::make('focal')->hiddenLabel()
                                ->image(fn (Media $record) => ImageDerivatives::resized($record->url(), 900)),
                        ])
                        ->action(function (Media $record, array $data): void {
                            $point = fn (string $axis) => max(0, min(100, (int) ($data['focal'][$axis] ?? 50)));
                            $record->update(['focal_x' => $point('x'), 'focal_y' => $point('y')]);

                            Notification::make()->title('Titik fokus disimpan')->success()->send();
                        }),
                    Action::make('crop')->label('Potong')->icon(Heroicon::OutlinedScissors)
                        ->visible(fn (Media $record) => $record->isCroppable())
                        ->modalHeading('Buat potongan untuk section')
                        ->modalDescription('Gambar dipotong sesuai ukuran section, berpusat di titik fokus (default tengah), dan disimpan sebagai gambar baru. Gambar asli tidak berubah.')
                        ->modalSubmitActionLabel('Buat potongan')
                        ->schema([
                            Select::make('preset')->label('Ukuran section')->required()
                                ->options(fn () => collect(config('media.presets'))
                                    ->filter(fn (array $preset) => ImagePreset::singleRatio($preset) !== null)
                                    ->map(fn (array $preset) => $preset['label'].' ('.$preset['ratio'].(filled($preset['width']) ? ', '.$preset['width'].'×'.$preset['height'].' px' : '').')')),
                        ])
                        ->action(function (Media $record, array $data): void {
                            $preset = ImagePreset::get($data['preset']);
                            $crop = MediaLibrary::cropToRatio($record, ImagePreset::singleRatio($preset), $preset['width'] ?? null);

                            Notification::make()->title($crop->is($record) ? 'Gambar ini sudah sesuai atau tidak bisa dipotong' : "Potongan dibuat: {$crop->name}")
                                ->success()->send();
                        }),
                    Action::make('replace')->label('Ganti di semua tempat')->icon(Heroicon::OutlinedArrowPath)
                        ->visible(fn () => self::canDeleteMedia())
                        ->modalHeading('Ganti gambar ini di semua konten')
                        ->modalDescription(fn (Media $record) => 'Dipakai di '.count(MediaUsage::for($record)).' tempat. Semua tempat itu akan memakai gambar pengganti; gambar ini tetap ada di pustaka.')
                        ->modalSubmitActionLabel('Ganti sekarang')
                        ->schema(fn (Media $record) => [
                            Select::make('replacement')->label('Gambar pengganti')->required()->searchable()
                                ->getSearchResultsUsing(fn (string $search) => Media::query()->whereKeyNot($record->getKey())
                                    ->where(fn (Builder $q) => $q->where('name', 'like', "%{$search}%")->orWhere('path', 'like', "%{$search}%"))
                                    ->latest('id')->limit(30)->get()->mapWithKeys(fn (Media $media) => [$media->getKey() => self::optionLabel($media)]))
                                ->getOptionLabelUsing(fn ($value) => ($media = Media::find($value)) ? self::optionLabel($media) : null)
                                ->helperText('Ketik nama berkas. Unggah dulu lewat "Unggah gambar" bila belum ada di pustaka.'),
                            Toggle::make('match_ratio')->label('Potong pengganti ke rasio gambar ini ('.($record->ratioLabel() ?? $record->dimensionsLabel()).')')
                                ->default(true)->visible($record->isCroppable())
                                ->helperText('Disarankan: tata letak konten tidak berubah. Potongan berpusat di titik fokus gambar pengganti.'),
                        ])
                        ->action(function (Media $record, array $data): void {
                            $replacement = Media::findOrFail($data['replacement']);
                            if (($data['match_ratio'] ?? false) && $record->isCroppable() && ! $replacement->matchesRatio($record->width / $record->height)) {
                                $replacement = MediaLibrary::cropToRatio($replacement, $record->width / $record->height);
                            }

                            $count = MediaUsage::replace($record, $replacement);
                            AuditLog::record(request(), 'media.replaced', $record, ['from' => $record->path, 'to' => $replacement->path, 'updated' => $count]);

                            Notification::make()->title($count ? "Gambar diganti di {$count} konten" : 'Gambar ini tidak dipakai konten mana pun')
                                ->body($count ? 'Gambar lama tidak lagi dipakai dan bisa dihapus lewat filter "Belum dipakai".' : null)
                                ->success()->send();
                        }),
                    // Hapus aman: ditolak bila gambar masih dipakai, dengan daftar lokasinya
                    Action::make('delete')->label('Hapus')->icon(Heroicon::OutlinedTrash)->color('danger')
                        ->visible(fn () => self::canDeleteMedia())
                        ->requiresConfirmation()
                        ->modalHeading('Hapus gambar dari pustaka?')
                        ->modalDescription('Berkas dihapus permanen dari server. Gambar yang masih dipakai konten tidak bisa dihapus.')
                        ->action(function (Media $record): void {
                            if ($usages = MediaUsage::for($record)) {
                                Notification::make()->title('Gambar masih dipakai, tidak dihapus')->danger()->persistent()
                                    ->body('Dipakai di: '.collect($usages)->map(fn (array $usage) => "{$usage['type']} \"{$usage['title']}\"")->take(5)->implode(', ')
                                        .(count($usages) > 5 ? ', dan '.(count($usages) - 5).' lainnya' : '').'. Ganti gambarnya di konten tersebut dulu.')
                                    ->send();

                                return;
                            }

                            MediaLibrary::delete($record);
                            Notification::make()->title('Gambar dihapus')->success()->send();
                        }),
                ])->tooltip('Aksi')->iconButton()
                    ->visible(fn (HasTable $livewire) => ! self::isSelecting($livewire)),
            ]);
    }

    private static function optionLabel(Media $media): string
    {
        return collect([$media->name, $media->ratioLabel(), $media->dimensionsLabel()])->filter()->implode(' · ');
    }

    private static function isSelecting(HasTable $livewire): bool
    {
        return $livewire instanceof ManageMedia && $livewire->isSelectingMedia;
    }

    /**
     * Hapus gambar yang tidak dipakai konten mana pun; yang masih dipakai dilewati.
     *
     * @param  Collection<int, Media>  $media
     * @return array{deleted: int, kept: int}
     */
    public static function deleteUnused(Collection $media): array
    {
        $used = MediaUsage::usedIds();
        [$kept, $deletable] = $media->partition(fn (Media $item) => $used->contains($item->id));
        $deletable->each(fn (Media $item) => MediaLibrary::delete($item));

        return ['deleted' => $deletable->count(), 'kept' => $kept->count()];
    }

    /** Hapus berkas bersifat permanen dan berdampak ke seluruh situs: hanya Admin & Editor. */
    public static function canDeleteMedia(): bool
    {
        return in_array(auth()->user()?->role, ['admin', 'editor'], true);
    }

    public static function getPages(): array
    {
        return ['index' => ManageMedia::route('/')];
    }
}
