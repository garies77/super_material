<?php

namespace App\Filament\Resources\Articles;

use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Filament\Support\SeoFields;
use App\Models\Article;
use BackedEnum;
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
use Filament\Schemas\Components\Section;
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

    protected static ?string $recordTitleAttribute = 'title';

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        return $user?->role === 'author' ? $query->where('author_id', $user->id) : $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Artikel Utama')->columns(2)->schema([
                TextInput::make('title')->label('Judul (H1)')->required()->maxLength(255)->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, ?string $state, string $operation) => $operation === 'create' ? $set('slug', Str::slug($state)) : null),
                TextInput::make('slug')->required()->unique(ignoreRecord: true)
                    ->helperText('Mengubah slug otomatis membuat redirect 301 dari URL lama.'),
                Textarea::make('summary')->label('Ringkasan')->rows(2)->columnSpanFull()
                    ->helperText('Dipakai sebagai meta description dan ringkasan kartu artikel.'),
                RichEditor::make('content')->label('Isi Artikel')->required()->columnSpanFull()
                    ->helperText('Gunakan heading H2 & H3 untuk daftar isi otomatis.'),
            ]),

            Section::make('Gambar Utama & Foto')->columns(2)->schema([
                FileUpload::make('image')->label('Foto Utama (16:9)')->image()->directory('articles')->columnSpanFull(),
                TextInput::make('image_caption')->label('Keterangan Foto')->placeholder('Contoh: Suasana pengaplikasian semen instan untuk dinding bata ringan'),
                TextInput::make('image_credit')->label('Kredit Foto')->placeholder('Contoh: Dok. Supermaterial / Antara'),
            ]),

            Section::make('Redaksi & Metadata Berita (CNN Style)')->columns(3)->schema([
                Select::make('author_id')->label('Penulis')->relationship('author', 'name')->searchable()->preload(),
                TextInput::make('author_initials')->label('Inisial Penulis')->placeholder('gar')->maxLength(20),
                TextInput::make('editor_name')->label('Nama Editor')->placeholder('Gema Suaria'),
                TextInput::make('editor_initials')->label('Inisial Editor')->placeholder('ed')->maxLength(20),
                TextInput::make('dateline')->label('Dateline')->default('Medan, Supermaterial --')->placeholder('Medan, Supermaterial --')->columnSpan(2),
                Toggle::make('is_featured')->label('Artikel Unggulan (Headline)')->default(false),
                DateTimePicker::make('published_at')->label('Tanggal & Jam Terbit')
                    ->helperText('Kosong = Draf. Isi tanggal mendatang untuk penjadwalan terbit otomatis.'),
            ]),

            Section::make('Kategori, Tag & Relasi')->columns(2)->schema([
                Select::make('categories')->label('Kategori Artikel')->relationship('categories', 'name')->multiple()->searchable()->preload(),
                Select::make('tags')->label('Topik Terkait (Tag)')->relationship('tags', 'name')->multiple()->searchable()->preload()
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

            SeoFields::make(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('published_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('Judul')->searchable()->limit(60),
                TextColumn::make('author.name')->label('Penulis'),
                IconColumn::make('is_featured')->label('Headline')->boolean(),
                TextColumn::make('views_count')->label('Dibaca')->numeric()->sortable(),
                TextColumn::make('status')->label('Status')->badge()
                    ->state(fn (Article $r) => ! $r->published_at ? 'Draf' : ($r->published_at->isFuture() ? 'Terjadwal' : 'Terbit'))
                    ->color(fn (string $state) => match ($state) { 'Terbit' => 'success', 'Terjadwal' => 'warning', default => 'gray' }),
                TextColumn::make('published_at')->label('Terbit')->dateTime('d M Y H:i')->sortable(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
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
