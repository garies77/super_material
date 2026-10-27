<?php

namespace App\Filament\Resources\Media;

use App\Filament\Resources\Media\Pages\ManageMedia;
use App\Models\Media;
use App\Support\MediaUsage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

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
                        ->state(fn (Media $record) => collect([$record->dimensionsLabel(), $record->sizeLabel()])->filter()->implode(' · ')),
                ])->space(2),
            ])
            ->contentGrid(['md' => 3, 'xl' => 4, '2xl' => 6])
            ->defaultSort('id', 'desc')
            ->paginated([24, 48, 96])
            ->filters([
                SelectFilter::make('folder')->label('Folder')
                    ->options(fn () => Media::query()->distinct()->orderBy('folder')->pluck('folder')
                        ->mapWithKeys(fn (string $folder) => [$folder => Media::FOLDERS[$folder] ?? $folder])),
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
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageMedia::route('/')];
    }
}
