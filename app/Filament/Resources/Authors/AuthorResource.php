<?php

namespace App\Filament\Resources\Authors;

use App\Filament\Resources\Authors\Pages\CreateAuthor;
use App\Filament\Resources\Authors\Pages\EditAuthor;
use App\Filament\Resources\Authors\Pages\ListAuthors;
use App\Models\Author;
use App\Rules\MinImagePixels;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuthorResource extends Resource
{
    protected static ?string $model = Author::class;

    protected static ?string $modelLabel = 'Penulis';

    protected static ?string $pluralModelLabel = 'Penulis';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static string|\UnitEnum|null $navigationGroup = 'Konten';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    /** Peran Penulis hanya melihat dan mengubah profilnya sendiri; URL edit profil lain jadi 404. */
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return parent::getEloquentQuery()->when($user?->role === 'author', fn ($q) => $q->where('user_id', $user->id));
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->role !== 'author';
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->role !== 'author';
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->role !== 'author';
    }

    public static function form(Schema $schema): Schema
    {
        // Kiri: identitas dan profil eksternal. Kanan: foto dan status. Satu kolom di bawah xl.
        return $schema->columns(['default' => 1, 'lg' => 1, 'xl' => 12])->components([
            Group::make()->columnSpan(['xl' => 7, '2xl' => 8])->schema([
                Section::make('Identitas penulis')
                    ->description('Nama, gelar, dan jabatan diisi terpisah agar data penulis terbaca benar oleh Google (schema Person).')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama')
                            ->required()
                            ->maxLength(150)
                            ->helperText('Nama asli saja, tanpa gelar dan tanpa "Ditulis oleh".')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, ?string $state, string $operation) => $operation === 'create' ? $set('slug', Str::slug($state)) : null)
                            ->rules([
                                function () {
                                    return function (string $attribute, $value, \Closure $fail) {
                                        $val = trim((string) $value);
                                        if (preg_match('/^(ditulis oleh|posted by|oleh|by)\s+/i', $val)) {
                                            $fail('Kata pengantar redaksional ("Ditulis oleh", "Posted by") dilarang masuk ke nama penulis (PRD 9.13.2).');
                                        }
                                        if (preg_match('/\b(Ir\.|Dr\.|Prof\.|Drs\.|S\.T\.|M\.T\.|S\.Kom\.|Ph\.D\.)/i', $val)) {
                                            $fail('Gelar awalan atau akhiran dilarang dimasukkan ke kolom Nama. Gunakan kolom Gelar Awalan atau Gelar Akhiran di bawah ini (SEO-02).');
                                        }
                                    };
                                },
                            ]),
                        TextInput::make('slug')
                            ->label('Slug URL')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->prefix('/penulis/')
                            ->helperText('Alamat halaman profil penulis.'),
                        TextInput::make('honorific_prefix')
                            ->label('Gelar awalan')
                            ->placeholder('Contoh: Ir., Dr., Prof.')
                            ->maxLength(50),
                        TextInput::make('honorific_suffix')
                            ->label('Gelar akhiran')
                            ->placeholder('Contoh: S.T., M.T., Ph.D.')
                            ->maxLength(50),
                        TextInput::make('job_title')
                            ->label('Jabatan / keahlian')
                            ->placeholder('Contoh: Spesialis Teknis Mutu Bahan Bangunan')
                            ->maxLength(100)
                            ->columnSpanFull(),
                        TextInput::make('motto')
                            ->label('Moto')
                            ->placeholder('Contoh: Bangun sekali, kokoh selamanya.')
                            ->maxLength(160)
                            ->columnSpanFull()
                            ->helperText('Tampil di bawah nama pada halaman /penulis/{slug}.'),
                        Textarea::make('bio')
                            ->label('Biografi singkat')
                            ->rows(3)
                            ->columnSpanFull()
                            ->helperText('Tampil di kartu profil penulis di akhir artikel.'),
                    ]),

                Section::make('Profil eksternal')
                    ->description('Tautan ke LinkedIn, X, atau situs pribadi. Membantu Google mengenali keahlian penulis (E-E-A-T).')
                    ->schema([
                        Repeater::make('same_as')
                            ->hiddenLabel()
                            ->simple(
                                TextInput::make('url')
                                    ->label('URL profil')
                                    ->placeholder('https://www.linkedin.com/in/…')
                                    ->url()
                                    ->required()
                            )
                            ->defaultItems(0)
                            ->addActionLabel('Tambah tautan profil'),
                    ]),
            ]),

            Group::make()->columnSpan(['xl' => 5, '2xl' => 4])->schema([
                Section::make('Foto & status')->schema([
                    Toggle::make('is_active')
                        ->label('Penulis aktif')
                        ->default(true)
                        ->disabled(fn () => auth()->user()?->role === 'author')
                        ->helperText('Nonaktif: halaman profil penulis disembunyikan.'),
                    FileUpload::make('avatar')
                        ->label('Foto profil')
                        ->image()
                        ->disk('public')
                        ->directory('authors')
                        ->visibility('public')
                        ->rule(new MinImagePixels(25000))
                        ->helperText('Foto wajah yang jelas, berbentuk persegi (1:1).'),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar')->label('')->circular(),
                TextColumn::make('name')->label('Nama Murni')->searchable()->sortable(),
                TextColumn::make('honorific_prefix')->label('Gelar Depan'),
                TextColumn::make('honorific_suffix')->label('Gelar Belakang'),
                TextColumn::make('job_title')->label('Jabatan')->limit(30),
                TextColumn::make('articles_count')->counts('articles')->label('Artikel'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuthors::route('/'),
            'create' => CreateAuthor::route('/create'),
            'edit' => EditAuthor::route('/{record}/edit'),
        ];
    }
}
