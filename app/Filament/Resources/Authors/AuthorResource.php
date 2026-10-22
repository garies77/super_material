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
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class AuthorResource extends Resource
{
    protected static ?string $model = Author::class;

    protected static ?string $modelLabel = 'Penulis';

    protected static ?string $pluralModelLabel = 'Penulis';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static string|\UnitEnum|null $navigationGroup = 'Konten';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas Penulis (Kemurnian author.name — PRD 9.13.2 & SEO-02)')
                ->description('Nama hanya berisi nama asli individu/organisasi. Gelar, jabatan, dan penerbit dipisahkan secara ketat untuk schema Google.')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nama Murni (author.name)')
                        ->required()
                        ->maxLength(150)
                        ->helperText('Hanya nama asli tanpa gelar awalan/akhiran dan tanpa kata "Ditulis oleh / Posted by".')
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
                        ->label('Slug URL (/penulis/{slug})')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->helperText('Membentuk URL Profil Penulis dan target author.url.'),
                    TextInput::make('honorific_prefix')
                        ->label('Gelar Awalan (author.honorificPrefix)')
                        ->placeholder('Contoh: Ir., Dr., Prof.')
                        ->maxLength(50),
                    TextInput::make('honorific_suffix')
                        ->label('Gelar Akhiran (author.honorificSuffix)')
                        ->placeholder('Contoh: S.T., M.T., Ph.D.')
                        ->maxLength(50),
                    TextInput::make('job_title')
                        ->label('Jabatan / Keahlian (author.jobTitle)')
                        ->placeholder('Contoh: Spesialis Teknis Mutu Bahan Bangunan')
                        ->maxLength(100)
                        ->columnSpanFull(),
                    Textarea::make('bio')
                        ->label('Biografi Singkat Penulis')
                        ->rows(3)
                        ->columnSpanFull()
                        ->helperText('Ditampilkan di kartu profil penulis dan properti description pada Person schema.'),
                ]),

            Section::make('Foto Profil & Tautan Eksternal (sameAs)')
                ->columns(2)
                ->schema([
                    FileUpload::make('avatar')
                        ->label('Foto Profil (Avatar)')
                        ->image()
                        ->disk('public')
                        ->directory('authors')
                        ->visibility('public')
                        ->rule(new MinImagePixels(25000))
                        ->helperText('Foto wajah jelas berformat kotak (1:1).'),
                    Repeater::make('same_as')
                        ->label('Profil Eksternal (author.sameAs)')
                        ->simple(
                            TextInput::make('url')
                                ->label('URL Profil (LinkedIn, Twitter, Website)')
                                ->url()
                                ->required()
                        )
                        ->defaultItems(0)
                        ->helperText('Tautan ke profil LinkedIn, X/Twitter, atau akun riset penulis untuk sinyal E-E-A-T Google.')
                        ->columnSpanFull(),
                    Toggle::make('is_active')
                        ->label('Penulis Aktif')
                        ->default(true),
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
