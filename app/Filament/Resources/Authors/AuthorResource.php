<?php

namespace App\Filament\Resources\Authors;

use App\Filament\Resources\Authors\Pages\CreateAuthor;
use App\Filament\Resources\Authors\Pages\EditAuthor;
use App\Filament\Resources\Authors\Pages\ListAuthors;
use App\Filament\Support\SeoScorePanel;
use App\Models\Author;
use App\Rules\MinImagePixels;
use BackedEnum;
use Closure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
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

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    /** Peran Penulis hanya melihat dan mengubah profilnya sendiri; URL edit profil lain jadi 404. */
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return parent::getEloquentQuery()->when($user?->writesAsOwnAuthor(), fn ($q) => $q->where('user_id', $user->id));
    }

    /** Penulis & Kontributor mengubah profilnya lewat menu "Profil saya" di sidebar, jadi menu Penulis tidak perlu. */
    public static function shouldRegisterNavigation(): bool
    {
        return ! auth()->user()?->writesAsOwnAuthor();
    }

    /** Label pemeriksaan => [teks misi, field tujuan] untuk panel kelengkapan profil (memakai tampilan SeoScorePanel). */
    public const PROFILE_MISSIONS = [
        'avatar' => ['Unggah foto profil yang jelas', 'avatar'],
        'job_title' => ['Isi jabatan atau keahlian', 'job_title'],
        'bio' => ['Tulis biografi singkat (minimal 60 karakter)', 'bio'],
        'motto' => ['Tulis moto singkat', 'motto'],
        'same_as' => ['Tambahkan tautan profil (LinkedIn, Instagram, situs)', 'same_as'],
    ];

    /**
     * Kelengkapan profil dari isi form yang sedang diketik (belum disimpan).
     *
     * @return array{score: int, checks: array<string, bool>, pending: bool}
     */
    public static function profileCompleteness(Get $get): array
    {
        $avatar = $get('avatar');
        $checks = [
            'avatar' => filled($avatar) && collect((array) $avatar)->filter()->isNotEmpty(),
            'job_title' => filled($get('job_title')),
            'bio' => mb_strlen(trim((string) $get('bio'))) >= 60,
            'motto' => filled($get('motto')),
            'same_as' => collect((array) $get('same_as'))->filter(fn ($item) => filled(is_array($item) ? ($item['url'] ?? null) : $item))->isNotEmpty(),
        ];

        return [
            'score' => (int) round(count(array_filter($checks)) / count($checks) * 100),
            'checks' => $checks,
            'pending' => false,
        ];
    }

    public static function canCreate(): bool
    {
        return ! auth()->user()?->writesAsOwnAuthor();
    }

    public static function canDelete(Model $record): bool
    {
        return ! auth()->user()?->writesAsOwnAuthor();
    }

    public static function canDeleteAny(): bool
    {
        return ! auth()->user()?->writesAsOwnAuthor();
    }

    public static function form(Schema $schema): Schema
    {
        // Kiri: identitas dan profil eksternal. Kanan: foto dan status. Satu kolom di bawah xl.
        return $schema->columns(['default' => 1, 'lg' => 1, 'xl' => 12])->components([
            Group::make()->columnSpan(['xl' => 7, '2xl' => 8])->schema([
                Section::make('Identitas penulis')
                    ->description('Nama dan jabatan diisi terpisah agar data penulis terbaca benar oleh Google (schema Person).')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nama')
                            ->required()
                            ->maxLength(150)
                            ->helperText('Nama asli saja, tanpa gelar dan tanpa "Ditulis oleh".')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Set $set, ?string $state, string $operation) => $operation === 'create' ? $set('slug', Str::slug($state)) : null)
                            ->rule(self::pureNameRule()),
                        TextInput::make('slug')
                            ->label('Slug URL')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->prefix('/penulis/')
                            // Alamat profil sudah tersebar di artikel & Google: hanya Admin/Editor yang mengubahnya
                            ->disabled(fn () => (bool) auth()->user()?->writesAsOwnAuthor())
                            ->helperText(fn () => auth()->user()?->writesAsOwnAuthor()
                                ? 'Alamat halaman profil Anda. Hubungi Admin bila perlu diubah.'
                                : 'Alamat halaman profil penulis.'),
                        ...self::detailFields(),
                    ]),

                self::externalProfileSection(),
            ]),

            Group::make()->columnSpan(['xl' => 5, '2xl' => 4])->schema([
                self::completenessSection(),
                Section::make('Foto & status')->schema([
                    Toggle::make('is_active')
                        ->label('Penulis aktif')
                        ->default(true)
                        ->disabled(fn () => (bool) auth()->user()?->writesAsOwnAuthor())
                        ->helperText('Nonaktif: halaman profil penulis disembunyikan.'),
                    self::avatarField(),
                ]),
            ]),
        ]);
    }

    /**
     * Nama penulis harus murni untuk schema Person: tanpa kata pengantar dan tanpa gelar.
     * Dibungkus satu closure lagi karena Filament mengevaluasi closure yang diberikan ke rule().
     */
    public static function pureNameRule(): Closure
    {
        return fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
            $name = trim((string) $value);
            if (preg_match('/^(ditulis oleh|posted by|oleh|by)\s+/i', $name)) {
                $fail('Kata pengantar redaksional ("Ditulis oleh", "Posted by") dilarang masuk ke nama penulis (PRD 9.13.2).');
            }
            if (preg_match('/\b(Ir\.|Dr\.|Prof\.|Drs\.|S\.T\.|M\.T\.|S\.Kom\.|Ph\.D\.)/i', $name)) {
                $fail('Tulis nama tanpa gelar (Ir., Dr., S.T., dan sejenisnya) (SEO-02).');
            }
        };
    }

    /**
     * Jabatan, moto, dan biografi; dipakai juga di halaman Profil akun.
     *
     * @return array<int, Component>
     */
    public static function detailFields(): array
    {
        return [
            TextInput::make('job_title')
                ->label('Jabatan / keahlian')
                ->live(onBlur: true)->afterStateUpdated(SeoScorePanel::refresh())
                ->placeholder('Contoh: Spesialis Teknis Mutu Bahan Bangunan')
                ->maxLength(100)
                ->columnSpanFull(),
            TextInput::make('motto')
                ->label('Moto')
                ->live(onBlur: true)->afterStateUpdated(SeoScorePanel::refresh())
                ->placeholder('Contoh: Bangun sekali, kokoh selamanya.')
                ->maxLength(160)
                ->columnSpanFull()
                ->helperText('Tampil di bawah nama pada halaman /penulis/{slug}.'),
            Textarea::make('bio')
                ->label('Biografi singkat')
                ->live(onBlur: true)->afterStateUpdated(SeoScorePanel::refresh())
                ->rows(3)
                ->columnSpanFull()
                ->helperText('Tampil di kartu profil penulis di akhir artikel.'),
        ];
    }

    public static function externalProfileSection(): Section
    {
        return Section::make('Profil eksternal')
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
                    ->live()->afterStateUpdated(SeoScorePanel::refresh())
                    ->addActionLabel('Tambah tautan profil'),
            ]);
    }

    /** Satu-satunya foto profil: tampil di artikel, halaman penulis, dan avatar akun di panel admin. */
    public static function avatarField(): FileUpload
    {
        return FileUpload::make('avatar')
            ->label('Foto profil')
            ->live()->afterStateUpdated(SeoScorePanel::refresh())
            ->image()
            ->imagePreset('square_small')
            ->disk('public')
            ->directory('authors')
            ->visibility('public')
            ->rule(new MinImagePixels(25000))
            ->helperText('Foto wajah yang jelas, berbentuk persegi (1:1).');
    }

    /** @param  string  $statePath  Awalan state field penulis di form (mis. "author" di halaman Profil) agar misi bisa menggulir ke field-nya. */
    public static function completenessSection(string $statePath = ''): Section
    {
        $missions = collect(self::PROFILE_MISSIONS)
            ->map(fn (array $mission) => [$mission[0], ltrim("{$statePath}.{$mission[1]}", '.')])
            ->all();

        return Section::make('Kelengkapan profil')
            ->description('Profil lengkap membuat pembaca dan Google lebih percaya pada tulisan Anda.')
            ->schema([SeoScorePanel::make(fn (Get $get) => self::profileCompleteness($get), $missions)]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar')->label('')->circular(),
                TextColumn::make('name')->label('Nama Murni')->searchable()->sortable(),
                TextColumn::make('job_title')->label('Jabatan')->limit(30),
                TextColumn::make('articles_count')->counts('articles')->label('Artikel'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()->visible(fn () => self::canDeleteAny())])]);
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
