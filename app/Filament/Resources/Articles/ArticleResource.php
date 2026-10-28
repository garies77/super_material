<?php

namespace App\Filament\Resources\Articles;

use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Filament\Support\CustomCodeFields;
use App\Filament\Support\DescriptiveFileName;
use App\Filament\Support\SeoFields;
use App\Filament\Support\SeoScorePanel;
use App\Models\Article;
use App\Rules\MinImagePixels;
use App\Support\SeoScore;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;

    protected static ?string $modelLabel = 'Artikel';

    protected static ?string $pluralModelLabel = 'Artikel';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static string|\UnitEnum|null $navigationGroup = 'Konten';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        return $user?->role === 'author' ? $query->whereHas('author', fn ($q) => $q->where('user_id', $user->id)) : $query;
    }

    /**
     * Skor SEO dari isi form yang belum disimpan: salinan artikel diisi nilai form, kategori & tag dari state.
     *
     * @return array{score: int, checks: array<string, bool>, pending: bool}
     */
    public static function seoPreview(Get $get, ?Article $record): array
    {
        $article = $record ? clone $record : new Article;
        $article->forceFill(collect(['title', 'summary', 'image_caption', 'author_id', 'meta_title', 'meta_description'])
            ->mapWithKeys(fn (string $field) => [$field => $get($field)])->all());
        $article->image = SeoScorePanel::storedPaths($get('image'))[0] ?? null;
        $article->og_image = SeoScorePanel::storedPaths($get('og_image'))[0] ?? null;
        $article->setRelation('categories', collect((array) $get('categories')));
        $article->setRelation('tags', collect((array) $get('tags')));

        return SeoScore::forArticle($article) + ['pending' => SeoScorePanel::hasPendingUpload($get('image'))];
    }

    public static function form(Schema $schema): Schema
    {
        // Dua kolom utama: 7:5 di desktop menengah (xl), 8:4 di layar lebar (2xl); satu kolom di bawah 1280 px. Kiri: konten di kiri, publikasi & relasi di kolom pendamping; satu kolom di layar kecil
        // Panel skor SEO di kolom kanan; field pemicu hanya me-render ulang panel ini
        $seoPanel = SeoScorePanel::make(fn (Get $get, ?Article $record) => self::seoPreview($get, $record));
        $refreshSeo = SeoScorePanel::refresh();

        return $schema->columns(['default' => 1, 'lg' => 1, 'xl' => 12])->components([
            Group::make()->columnSpan(['xl' => 7, '2xl' => 8])->schema([
                Section::make('Artikel Utama')->columns(2)->schema([
                    TextInput::make('title')->label('Judul (H1)')->required()->maxLength(255)->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, ?string $state, string $operation) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
                    TextInput::make('slug')->required()->unique(ignoreRecord: true)
                        ->helperText('Mengubah slug otomatis membuat redirect 301 dari URL lama.'),
                    Textarea::make('summary')->label('Ringkasan')->rows(2)->columnSpanFull()
                        ->helperText('Dipakai sebagai meta description dan ringkasan kartu artikel.')
                        ->live(onBlur: true)->afterStateUpdated($refreshSeo),
                    RichEditor::make('content')->label('Isi Artikel')->required()->columnSpanFull()
                        ->helperText('Gunakan heading H2 & H3 untuk daftar isi otomatis.'),
                ]),

                Section::make('Gambar Utama & Foto')->columns(2)->schema([
                    FileUpload::make('image')->label('Foto Utama (16:9)')->image()->imagePreset('article')->disk('public')->directory('articles')->visibility('public')->columnSpanFull()
                        ->getUploadedFileNameForStorageUsing(DescriptiveFileName::from('title', 'articles'))
                        ->rule(new MinImagePixels(50000))
                        ->helperText('Minimal 50.000 piksel (disarankan 1200×675 untuk 16:9). Sistem otomatis membuat turunan 16:9, 4:3, dan 1:1 untuk Google Images & Discover (PRD 9.13.3 & SEO-01).')
                        ->live()->afterStateUpdated($refreshSeo),
                    TextInput::make('image_caption')->label('Keterangan Foto')->live(onBlur: true)->afterStateUpdated($refreshSeo)->placeholder('Contoh: Suasana pengaplikasian semen instan untuk dinding bata ringan'),
                    TextInput::make('image_credit')->label('Kredit Foto')->placeholder('Contoh: Dok. Supermaterial / Antara'),
                ]),

                CustomCodeFields::make(),

                SeoFields::make(),
            ]),

            // Kolom pendamping
            Group::make()->columnSpan(['xl' => 5, '2xl' => 4])->schema([
                Section::make('Skor SEO')->schema([$seoPanel]),

                Section::make('Publikasi & Penulis')->schema([
                    // Bawaan: profil penulis milik akun yang login. Penulis terkunci ke profilnya sendiri
                    // (ditegakkan juga di backend saat simpan); Admin/Editor bebas memilih atau membuat profil kontributor.
                    Select::make('author_id')->label('Penulis')->relationship('author', 'name')->searchable()->preload()->live()->afterStateUpdated($refreshSeo)
                        ->default(fn () => auth()->user()?->author()->value('id'))
                        ->disabled(fn () => auth()->user()?->role === 'author')
                        ->dehydrated()
                        ->createOptionForm(fn () => auth()->user()?->role === 'author' ? null : [
                            TextInput::make('name')->label('Nama lengkap')->required()->maxLength(150),
                            TextInput::make('slug')->required()->maxLength(150)->unique('authors', 'slug'),
                            TextInput::make('job_title')->label('Jabatan / keahlian')->maxLength(100),
                        ])
                        ->helperText(fn () => auth()->user()?->role === 'author'
                            ? 'Artikel diterbitkan atas nama profil penulis Anda.'
                            : 'Bawaan: profil Anda. Pilih kontributor lain atau tambah profil baru bila perlu.'),
                    DateTimePicker::make('published_at')->label('Tanggal & Jam Terbit (WIB)')->seconds(false)->timezone('Asia/Jakarta')
                        ->helperText('Kosong = Draf. Isi waktu mendatang untuk terbit terjadwal.')
                        ->suffixAction(Action::make('publishNow')->label('Terbitkan Sekarang')->icon('heroicon-o-bolt')
                            ->tooltip('Isi dengan waktu sekarang (WIB)')
                            ->action(fn (Set $set) => $set('published_at', now()))), // waktu aplikasi; field mengubahnya ke WIB sendiri
                    Toggle::make('is_featured')->label('Artikel Unggulan (Headline)')->default(false),
                    Select::make('schema_type')->label('Jenis Data Terstruktur')->options(Article::SCHEMA_TYPES)
                        ->default('BlogPosting')->required()->selectablePlaceholder(false)
                        ->helperText('@type JSON-LD artikel (PRD 9.13.1).'),
                ]),

                Section::make('Kategori, Tag & Relasi')->schema([
                    Select::make('categories')->label('Kategori Artikel')->relationship('categories', 'name')->multiple()->searchable()->preload()->live()->afterStateUpdated($refreshSeo),
                    Select::make('tags')->label('Topik Terkait (Tag)')->relationship('tags', 'name')->multiple()->searchable()->preload()->live()->afterStateUpdated($refreshSeo)
                        ->createOptionForm([
                            TextInput::make('name')->label('Nama Tag')->required(),
                            TextInput::make('slug')->label('Slug')->unique('tags', 'slug'),
                        ]),
                    Select::make('relatedArticles')->label('Artikel Terkait (Manual)')->relationship('relatedArticles', 'title')->multiple()->searchable()->preload(),
                    Select::make('products')->label('Produk Terkait (Dibahas dalam Artikel)')->relationship('products', 'name')->multiple()->searchable()->preload(),
                ]),

                Section::make('FAQ')->collapsed()->schema([
                    Repeater::make('faqs')->relationship()->label('')->defaultItems(0)->orderColumn('sort_order')->schema([
                        TextInput::make('question')->label('Pertanyaan')->required(),
                        Textarea::make('answer')->label('Jawaban')->required()->rows(2),
                    ]),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Draf (belum ada tanggal terbit) di paling atas, lalu terjadwal/terbit terbaru; tanpa ini draf baru tenggelam di halaman terakhir
            ->defaultSort(fn (Builder $query) => $query
                ->orderByRaw('CASE WHEN published_at IS NULL THEN 0 ELSE 1 END')
                ->orderByDesc('published_at')
                ->orderByDesc('created_at'))
            ->columns([
                TextColumn::make('title')->label('Judul')->searchable()->limit(60),
                TextColumn::make('author.name')->label('Penulis'),
                IconColumn::make('is_featured')->label('Headline')->boolean(),
                TextColumn::make('views_count')->label('Dibaca')->numeric()->sortable(),
                TextColumn::make('seo_score')->label('Skor SEO')->badge()
                    ->state(fn (Article $r) => SeoScore::forArticle($r)['score'].'%')
                    ->color(fn (string $state) => (int) $state >= 80 ? 'success' : ((int) $state >= 50 ? 'warning' : 'danger'))
                    ->tooltip(fn (Article $r) => collect(SeoScore::forArticle($r)['checks'])->reject()->keys()->map(fn ($c) => '✗ '.$c)->implode("\n") ?: 'Semua pemeriksaan terpenuhi'),
                TextColumn::make('status')->label('Status')->badge()
                    ->state(fn (Article $r) => ! $r->published_at ? 'Draf' : ($r->published_at->isFuture() ? 'Terjadwal' : 'Terbit'))
                    ->color(fn (string $state) => match ($state) {
                        'Terbit' => 'success', 'Terjadwal' => 'warning', default => 'gray'
                    }),
                TextColumn::make('published_at')->label('Terbit (WIB)')->dateTime('d M Y H:i', 'Asia/Jakarta')->sortable(),
            ])
            ->recordActions([
                Action::make('publishNow')->label('Terbitkan')->icon('heroicon-o-paper-airplane')->color('success')
                    ->visible(fn (Article $r) => ! $r->published_at || $r->published_at->isFuture())
                    ->action(function (Article $r, Action $action) {
                        $r->update(['published_at' => now()]);
                        $action->success();
                    })
                    ->successNotificationTitle('Artikel diterbitkan'),
                Action::make('unpublish')->label('Jadikan Draf')->icon('heroicon-o-eye-slash')->color('warning')
                    ->visible(fn (Article $r) => $r->published_at && ! $r->published_at->isFuture())
                    ->requiresConfirmation()->modalHeading('Jadikan draf?')
                    ->modalDescription('Artikel tidak lagi tampil di situs sampai diterbitkan kembali.')
                    ->action(function (Article $r, Action $action) {
                        $r->update(['published_at' => null]);
                        $action->success();
                    })
                    ->successNotificationTitle('Artikel dijadikan draf'),
                EditAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([
                BulkAction::make('publishNow')->label('Terbitkan sekarang')->icon('heroicon-o-paper-airplane')->requiresConfirmation()
                    ->action(fn ($records) => $records->each->update(['published_at' => now()]))->deselectRecordsAfterCompletion(),
                DeleteBulkAction::make(),
            ])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListArticles::route('/'),
            'create' => CreateArticle::route('/create'),
            'edit' => EditArticle::route('/{record}/edit'),
        ];
    }
}
