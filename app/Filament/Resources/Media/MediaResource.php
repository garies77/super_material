<?php

namespace App\Filament\Resources\Media;

use App\Filament\Resources\Media\Pages\ManageMedia;
use App\Filament\Support\ImagePreset;
use App\Models\Media;
use App\Support\MediaLibrary;
use App\Support\MediaUsage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pustaka Media: semua gambar di disk publik yang bisa dipakai ulang lewat tombol "Pilih dari Media".
 * Fase 1 belum menyediakan hapus; berkas yang masih dipakai konten tidak boleh hilang.
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

    protected static ?int $navigationSort = 7;

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
                    ImageColumn::make('thumbnail')->state(fn (Media $record) => $record->thumbnailUrl())
                        ->imageHeight(150)->extraImgAttributes(['loading' => 'lazy', 'class' => 'sm-media-thumb']),
                    TextColumn::make('name')->label('Nama')->weight('medium')->limit(40)
                        ->searchable(['name', 'path', 'alt_text']),
                    TextColumn::make('folder')->badge()->color('gray')->formatStateUsing(fn (Media $record) => $record->folderLabel()),
                    TextColumn::make('details')->color('gray')->size('xs')
                        ->state(fn (Media $record) => collect([$record->ratioLabel(), $record->dimensionsLabel(), $record->sizeLabel()])->filter()->implode(' · ')),
                ])->space(2),
            ])
            ->contentGrid(['md' => 3, 'xl' => 4, '2xl' => 6])
            ->defaultSort('id', 'desc')
            ->paginated([24, 48, 96])
            ->filters([
                SelectFilter::make('folder')->label('Folder')
                    ->options(fn () => Media::query()->distinct()->orderBy('folder')->pluck('folder')
                        ->mapWithKeys(fn (string $folder) => [$folder => Media::FOLDERS[$folder] ?? $folder])),
                SelectFilter::make('ratio')->label('Rasio')
                    ->options(array_combine(array_keys(Media::RATIOS), array_keys(Media::RATIOS)))
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null) && isset(Media::RATIOS[$data['value']])
                        ? $query->withRatio(Media::RATIOS[$data['value']])
                        : $query),
            ])
            ->recordAction('detail')
            ->recordActions([
                Action::make('detail')->label('Detail')->icon(Heroicon::OutlinedEye)
                    ->modalHeading(fn (Media $record) => $record->name)
                    ->modalWidth(Width::ThreeExtraLarge)
                    ->modalContent(fn (Media $record) => view('filament.media.detail', [
                        'media' => $record,
                        'usages' => MediaUsage::for($record),
                    ]))
                    ->modalSubmitAction(false)->modalCancelActionLabel('Tutup'),
                EditAction::make()->label('Ubah info'),
                Action::make('crop')->label('Potong')->icon(Heroicon::OutlinedScissors)
                    ->visible(fn (Media $record) => $record->isCroppable())
                    ->modalHeading('Buat potongan untuk section')
                    ->modalDescription('Gambar dipotong dari bagian tengah sesuai ukuran section dan disimpan sebagai gambar baru. Gambar asli tidak berubah.')
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
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageMedia::route('/')];
    }
}
