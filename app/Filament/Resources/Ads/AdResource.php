<?php

namespace App\Filament\Resources\Ads;

use App\Filament\Resources\Ads\Pages\CreateAd;
use App\Filament\Resources\Ads\Pages\EditAd;
use App\Filament\Resources\Ads\Pages\ListAds;
use App\Filament\Support\DescriptiveFileName;
use App\Models\Ad;
use App\Models\Article;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Iklan internal artikel: slot, jadwal, penargetan, dan pratinjau (PRD 8.8). */
class AdResource extends Resource
{
    protected static ?string $model = Ad::class;

    protected static ?string $modelLabel = 'Iklan artikel';

    protected static ?string $pluralModelLabel = 'Iklan artikel';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|\UnitEnum|null $navigationGroup = 'Konten';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return in_array(auth()->user()?->role, ['admin', 'editor'], true);
    }

    public static function form(Schema $schema): Schema
    {
        $isAdmin = fn () => auth()->user()?->isAdmin() ?? false;
        $format = fn (string ...$formats) => fn (Get $get) => in_array($get('format'), $formats, true);

        return $schema->components([
            Section::make('Iklan')->columns(2)->schema([
                TextInput::make('name')->label('Nama internal')->required()->maxLength(255),
                Select::make('label')->label('Label tampil')->options(Ad::LABELS)->default('Iklan')->required(),
                Select::make('slot')->label('Slot')->options(Ad::SLOTS)->required()->live(),
                TextInput::make('paragraph_after')->label('Tampil setelah paragraf ke-')->numeric()->minValue(1)
                    ->placeholder(fn (Get $get) => (string) (Ad::DEFAULT_PARAGRAPH[$get('slot')] ?? 3))
                    ->helperText('Kosongkan untuk bawaan (Dalam isi 1: 3, Dalam isi 2: 8). Slot hanya tampil bila artikel lebih panjang dari ini.')
                    ->visible(fn (Get $get) => in_array($get('slot'), ['in_content_1', 'in_content_2'], true)),
                Toggle::make('show_scroll_hint')->label('Tampilkan "Scroll untuk melanjutkan"')
                    ->visible(fn (Get $get) => in_array($get('slot'), ['in_content_1', 'in_content_2'], true)),
                Select::make('format')->label('Format')->required()->live()
                    ->options(fn (?Ad $record) => $isAdmin() || $record?->format === 'html'
                        ? Ad::FORMATS
                        : array_diff_key(Ad::FORMATS, ['html' => true]))
                    ->disabled(fn (?Ad $record) => $record?->format === 'html' && ! $isAdmin()),
            ]),

            Section::make('Materi')->columns(2)->schema([
                TextInput::make('target_url')->label('Tautan tujuan')->maxLength(2000)
                    ->helperText('URL penuh (https://…) untuk situs mitra/marketplace — otomatis rel="sponsored nofollow" dan tab baru. Path /produk/… untuk halaman Supermaterial sendiri. Kartu produk boleh dikosongkan (memakai halaman produk).')
                    ->rule('regex:#^(https?://[^\s]+|/[^/\s][^\s]*)$#i')
                    ->required(fn (Get $get) => in_array($get('format'), ['image', 'text'], true))
                    ->visible($format('image', 'text', 'product'))->columnSpanFull(),
                FileUpload::make('image')->label('Gambar desktop')->image()->disk('public')->directory('promo')->visibility('public')
                    ->getUploadedFileNameForStorageUsing(DescriptiveFileName::from('name', 'promo'))
                    ->helperText('Tinggi slot tetap 250 px (sidebar 300 px); disarankan 728×250 atau 300×250.')
                    ->required($format('image'))->visible($format('image')),
                FileUpload::make('image_mobile')->label('Gambar mobile (opsional)')->image()->disk('public')->directory('promo')->visibility('public')
                    ->getUploadedFileNameForStorageUsing(DescriptiveFileName::from('name', 'promo', 'mobile'))
                    ->helperText('Dipakai pada layar < 640 px, disarankan 336×250.')
                    ->visible($format('image')),
                TextInput::make('image_alt')->label('Teks alternatif gambar')->maxLength(255)->visible($format('image')),
                Select::make('product_id')->label('Produk dari katalog')->searchable()
                    ->options(fn () => Product::published()->orderBy('name')->pluck('name', 'id'))
                    ->required($format('product'))->visible($format('product')),
                TextInput::make('title')->label('Judul')->maxLength(255)->required($format('text'))->visible($format('text')),
                Textarea::make('body')->label('Teks')->rows(3)->visible($format('text'))->columnSpanFull(),
                TextInput::make('cta_label')->label('Teks tombol (CTA)')->maxLength(60)->visible($format('text', 'product')),
                Textarea::make('html')->label('HTML kustom')->rows(8)->columnSpanFull()
                    ->helperText('Disanitasi saat disimpan: <script>, atribut on*, dan iframe dihapus. Tautan keluar otomatis diberi rel="sponsored nofollow".')
                    ->required($format('html'))->visible($format('html'))
                    ->disabled(fn () => ! $isAdmin())
                    ->dehydrated(fn () => $isAdmin()),
            ]),

            Section::make('Jadwal & penargetan')->columns(2)->schema([
                DateTimePicker::make('starts_at')->label('Mulai (WIB)')->seconds(false)->timezone('Asia/Jakarta'),
                DateTimePicker::make('ends_at')->label('Selesai (WIB)')->seconds(false)->timezone('Asia/Jakarta')->after('starts_at'),
                Toggle::make('is_active')->label('Aktif')->default(true),
                TextInput::make('priority')->label('Prioritas')->numeric()->default(0)->required()
                    ->helperText('Lebih tinggi tampil lebih dulu; prioritas sama dirotasi bergantian.'),
                Select::make('targeting')->label('Tampil pada')->options(Ad::TARGETING)->default('all')->required()->live(),
                Select::make('target_category_ids')->label('Kategori artikel')->multiple()->searchable()
                    ->options(fn () => Category::whereHas('articles')->orderBy('name')->pluck('name', 'id'))
                    ->required(fn (Get $get) => $get('targeting') === 'categories')
                    ->visible(fn (Get $get) => $get('targeting') === 'categories'),
                Select::make('target_tag_ids')->label('Tag')->multiple()->searchable()
                    ->options(fn () => Tag::orderBy('name')->pluck('name', 'id'))
                    ->required(fn (Get $get) => $get('targeting') === 'tags')
                    ->visible(fn (Get $get) => $get('targeting') === 'tags'),
                Select::make('target_article_ids')->label('Artikel')->multiple()->searchable()
                    ->options(fn () => Article::published()->latest('published_at')->pluck('title', 'id'))
                    ->required(fn (Get $get) => $get('targeting') === 'articles')
                    ->visible(fn (Get $get) => $get('targeting') === 'articles'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('priority', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                'events as impressions_count' => fn ($e) => $e->where('type', 'impression')->where('is_bot', false),
                'events as clicks_count' => fn ($e) => $e->where('type', 'click')->where('is_bot', false),
            ]))
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->limit(40),
                TextColumn::make('slot')->label('Slot')->badge()->formatStateUsing(fn ($state) => Ad::SLOTS[$state] ?? $state),
                TextColumn::make('format')->label('Format')->formatStateUsing(fn ($state) => Ad::FORMATS[$state] ?? $state),
                TextColumn::make('priority')->label('Prioritas')->sortable(),
                TextColumn::make('starts_at')->label('Mulai')->dateTime('d M Y H:i', 'Asia/Jakarta')->placeholder('—'),
                TextColumn::make('ends_at')->label('Selesai')->dateTime('d M Y H:i', 'Asia/Jakarta')->placeholder('—'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('impressions_count')->label('Impresi')->numeric()->sortable(),
                TextColumn::make('clicks_count')->label('Klik')->numeric()->sortable(),
                TextColumn::make('ctr')->label('CTR')->state(fn (Ad $r) => $r->impressions_count
                    ? number_format($r->clicks_count / $r->impressions_count * 100, 2, ',', '.').'%' : '—'),
            ])
            ->filters([
                SelectFilter::make('slot')->options(Ad::SLOTS),
                SelectFilter::make('format')->options(Ad::FORMATS),
            ])
            ->recordActions([self::previewAction(), EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function previewAction(): Action
    {
        return Action::make('preview')->label('Pratinjau slot')->icon('heroicon-o-eye')
            ->url(fn (Ad $record) => route('ad.preview', $record))->openUrlInNewTab();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAds::route('/'),
            'create' => CreateAd::route('/create'),
            'edit' => EditAd::route('/{record}/edit'),
        ];
    }
}
