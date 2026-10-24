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
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Kampanye: satu menu untuk banner (di halaman atau pop-up) dan sidebar, dengan halaman tujuan,
 * jadwal, prioritas, penargetan, dan pratinjau.
 */
class AdResource extends Resource
{
    protected static ?string $model = Ad::class;

    protected static ?string $slug = 'kampanye';

    protected static ?string $modelLabel = 'Kampanye';

    protected static ?string $pluralModelLabel = 'Kampanye';

    protected static ?string $navigationLabel = 'Kampanye';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|\UnitEnum|null $navigationGroup = 'Konten';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return in_array(auth()->user()?->role, ['admin', 'editor'], true);
    }

    public static function form(Schema $schema): Schema
    {
        $isAdmin = fn () => auth()->user()?->isAdmin() ?? false;
        $format = fn (string ...$formats) => fn (Get $get) => in_array($get('format'), $formats, true);
        $popup = fn (Get $get) => $get('placement') === 'banner' && $get('display') === 'popup';
        $inContent = fn (Get $get) => ! $popup($get) && in_array($get('slot'), ['in_content_1', 'in_content_2'], true);

        // Kiri: isi kampanye (di mana tampil, materi, pop-up). Kanan: kontrol (status, jadwal, penargetan). Satu kolom di bawah xl.
        return $schema->columns(['default' => 1, 'lg' => 1, 'xl' => 12])->components([
            Group::make()->columnSpan(['xl' => 7, '2xl' => 8])->schema([
                Section::make('Detail kampanye')->description('Nama untuk tim, label yang dilihat pengunjung, dan di mana kampanye tampil.')->columns(2)->schema([
                    TextInput::make('name')->label('Nama internal')->required()->maxLength(255),
                    Select::make('label')->label('Label tampil')->options(Ad::LABELS)->default('Sorotan')->required(),
                    Radio::make('placement')->label('Format tampilan')->options(Ad::PLACEMENTS)->default('banner')->required()->live()->inline()
                        ->descriptions(['banner' => 'Lebar penuh di area konten, atau pop-up.', 'sidebar' => 'Kolom samping (tersedia di halaman artikel).']),
                    Radio::make('display')->label('Tampil sebagai')->options(Ad::DISPLAYS)->default('inline')->required()->live()->inline()
                        ->visible(fn (Get $get) => $get('placement') === 'banner'),
                    CheckboxList::make('pages')->label('Tampilkan di halaman')->required()->live()->columns(3)->columnSpanFull()
                        ->options(fn (Get $get) => $get('placement') === 'sidebar'
                            ? array_intersect_key(Ad::PAGES, array_flip(Ad::SIDEBAR_PAGES))
                            : Ad::PAGES)
                        ->default(['article'])
                        ->helperText(fn (Get $get) => match (true) {
                            $get('placement') === 'sidebar' => 'Sidebar hanya tersedia di halaman artikel.',
                            $popup($get) => 'Pop-up muncul di halaman terpilih sesuai pemicu dan frekuensi di bawah.',
                            default => 'Di halaman artikel posisi banner mengikuti pilihan "Posisi di artikel"; di halaman lain banner tampil di atas konten.',
                        }),
                    Select::make('slot')->label('Posisi di artikel')->options(Ad::ARTICLE_SLOTS)->default('top')->live()
                        ->visible(fn (Get $get) => $get('placement') === 'banner' && ! $popup($get) && in_array('article', (array) $get('pages'), true))
                        ->required(fn (Get $get) => $get('placement') === 'banner' && ! $popup($get) && in_array('article', (array) $get('pages'), true)),
                    TextInput::make('paragraph_after')->label('Tampil setelah paragraf ke-')->numeric()->minValue(1)
                        ->placeholder(fn (Get $get) => (string) (Ad::DEFAULT_PARAGRAPH[$get('slot')] ?? 3))
                        ->helperText('Kosongkan untuk bawaan (Dalam isi 1: 3, Dalam isi 2: 8). Hanya tampil bila artikel lebih panjang dari ini.')
                        ->visible($inContent),
                    Toggle::make('show_scroll_hint')->label('Tampilkan "Scroll untuk melanjutkan"')->visible($inContent)->columnSpanFull(),
                ]),

                Section::make('Materi iklan')->description('Pilih jenis materi; isian di bawahnya menyesuaikan.')->columns(2)->schema([
                    // Bawaan "Gambar" agar bagian ini tidak pernah tampil kosong; berlaku juga untuk pop-up
                    Select::make('format')->label('Jenis materi')->required()->live()->default('image')->selectablePlaceholder(false)->columnSpanFull()
                        ->options(function (Get $get, ?Ad $record) use ($isAdmin, $popup) {
                            if ($popup($get)) {
                                return ['image' => 'Hanya gambar', 'text' => 'Gambar dengan teks & tombol'];
                            }

                            return $isAdmin() || $record?->format === 'html' ? Ad::FORMATS : array_diff_key(Ad::FORMATS, ['html' => true]);
                        })
                        ->disabled(fn (?Ad $record) => $record?->format === 'html' && ! $isAdmin()),
                    TextInput::make('target_url')->label('Tautan tujuan')->maxLength(2000)
                        ->helperText('URL penuh (https://…) untuk situs mitra/marketplace (tab baru) atau path /produk/… untuk halaman Supermaterial sendiri. Kartu produk boleh dikosongkan (memakai halaman produk).')
                        ->rule('regex:#^(https?://[^\s]+|/[^/\s][^\s]*)$#i')
                        ->required(fn (Get $get) => in_array($get('format'), ['image', 'text'], true))
                        ->visible($format('image', 'text', 'product'))->columnSpanFull(),
                    FileUpload::make('image')->label('Gambar desktop')->image()->disk('public')->directory('kampanye')->visibility('public')
                        ->getUploadedFileNameForStorageUsing(DescriptiveFileName::from('name', 'kampanye'))
                        ->helperText(fn (Get $get) => $popup($get)
                            ? 'Pop-up: disarankan 1200×750 (rasio 16:10).'
                            : 'Tinggi slot tetap 250 px (sidebar 300 px); disarankan 728×250 atau 300×250.')
                        ->required(fn (Get $get) => $get('format') === 'image' || $popup($get))
                        ->visible(fn (Get $get) => $get('format') === 'image' || $popup($get)),
                    FileUpload::make('image_mobile')->label('Gambar mobile (opsional)')->image()->disk('public')->directory('kampanye')->visibility('public')
                        ->getUploadedFileNameForStorageUsing(DescriptiveFileName::from('name', 'kampanye', 'mobile'))
                        ->helperText('Dipakai pada layar < 640 px.')
                        ->visible(fn (Get $get) => $get('format') === 'image' || $popup($get)),
                    TextInput::make('image_alt')->label('Teks alternatif gambar')->maxLength(255)->columnSpanFull()
                        ->helperText('Jelaskan isi gambar untuk pembaca layar.')
                        ->visible(fn (Get $get) => $get('format') === 'image' || $popup($get)),
                    Select::make('product_id')->label('Produk dari katalog')->searchable()->columnSpanFull()
                        ->options(fn () => Product::published()->orderBy('name')->pluck('name', 'id'))
                        ->required($format('product'))->visible($format('product')),
                    TextInput::make('title')->label('Judul')->maxLength(255)->required($format('text'))->visible($format('text'))->columnSpanFull(),
                    Textarea::make('body')->label('Teks')->rows(3)->visible($format('text'))->columnSpanFull(),
                    TextInput::make('cta_label')->label('Teks tombol (CTA)')->maxLength(60)->visible($format('text', 'product'))->columnSpanFull(),
                    Textarea::make('html')->label('HTML kustom')->rows(8)->columnSpanFull()
                        ->helperText('Disanitasi saat disimpan: <script>, atribut on*, dan iframe dihapus.')
                        ->required($format('html'))->visible($format('html'))
                        ->disabled(fn () => ! $isAdmin())
                        ->dehydrated(fn () => $isAdmin()),
                ]),

                Section::make('Pengaturan pop-up')->description('Kapan dan seberapa sering pop-up muncul.')->columns(2)->visible($popup)->schema([
                    Select::make('device')->label('Perangkat')->options(Ad::DEVICES)->default('all')->required(),
                    Select::make('trigger')->label('Pemicu')->options(Ad::TRIGGERS)->default('delay')->required()->live(),
                    TextInput::make('delay_seconds')->label('Jeda (detik)')->numeric()->minValue(1)->maxValue(120)->default(5)
                        ->visible(fn (Get $get) => $get('trigger') === 'delay'),
                    TextInput::make('scroll_percent')->label('Gulir (%)')->numeric()->minValue(1)->maxValue(100)->default(30)
                        ->visible(fn (Get $get) => $get('trigger') === 'scroll'),
                    Select::make('frequency')->label('Frekuensi')->options(Ad::FREQUENCIES)->default('days')->required()->live(),
                    TextInput::make('frequency_days')->label('Setiap (hari)')->numeric()->minValue(1)->maxValue(365)->default(7)
                        ->visible(fn (Get $get) => $get('frequency') === 'days'),
                    Toggle::make('allow_never_again')->label('Tampilkan tombol "Jangan tampilkan lagi"')->columnSpanFull(),
                ]),
            ]),

            Group::make()->columnSpan(['xl' => 5, '2xl' => 4])->schema([
                Section::make('Status & penjadwalan')->schema([
                    Toggle::make('is_active')->label('Aktif')->default(true)
                        ->helperText('Nonaktif: tidak tampil di situs walau dalam jadwal.'),
                    DateTimePicker::make('starts_at')->label('Mulai (WIB)')->seconds(false)->timezone('Asia/Jakarta')
                        ->helperText('Kosong = langsung tampil.'),
                    DateTimePicker::make('ends_at')->label('Selesai (WIB)')->seconds(false)->timezone('Asia/Jakarta')->after('starts_at')
                        ->helperText('Kosong = tanpa batas waktu.'),
                    TextInput::make('priority')->label('Prioritas')->numeric()->default(0)->required()
                        ->helperText('Lebih tinggi tampil lebih dulu; prioritas sama dirotasi bergantian.'),
                ]),

                Section::make('Penargetan audiens')->schema([
                    Select::make('targeting')->label('Penargetan isi')->options(Ad::TARGETING)->default('all')->required()->live()
                        ->helperText('Tag dan artikel tertentu hanya berlaku di halaman artikel.'),
                    Select::make('target_category_ids')->label('Kategori')->multiple()->searchable()
                        ->options(fn () => Category::orderBy('name')->pluck('name', 'id'))
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
                TextColumn::make('placement')->label('Format')->badge()
                    ->state(fn (Ad $r) => $r->placement === 'sidebar' ? 'Sidebar' : ($r->isPopup() ? 'Banner pop-up' : 'Banner')),
                TextColumn::make('pages')->label('Halaman')->wrap()
                    ->state(fn (Ad $r) => collect($r->pageList())->map(fn ($p) => Ad::PAGES[$p] ?? $p)->implode(', ')),
                TextColumn::make('status')->label('Status')->badge()->state(fn (Ad $r) => $r->displayStatus())
                    ->color(fn (string $state) => match ($state) {
                        'Aktif' => 'success', 'Terjadwal' => 'info', default => 'gray'
                    }),
                TextColumn::make('priority')->label('Prioritas')->sortable(),
                TextColumn::make('starts_at')->label('Mulai')->dateTime('d M Y H:i', 'Asia/Jakarta')->placeholder('—')->toggleable(),
                TextColumn::make('ends_at')->label('Selesai')->dateTime('d M Y H:i', 'Asia/Jakarta')->placeholder('—')->toggleable(),
                TextColumn::make('impressions_count')->label('Impresi')->numeric()->sortable(),
                TextColumn::make('clicks_count')->label('Klik')->numeric()->sortable(),
                TextColumn::make('ctr')->label('CTR')->state(fn (Ad $r) => $r->impressions_count
                    ? number_format($r->clicks_count / $r->impressions_count * 100, 2, ',', '.').'%' : '—'),
            ])
            ->filters([
                SelectFilter::make('placement')->label('Format')->options(Ad::PLACEMENTS),
                SelectFilter::make('display')->label('Tampil sebagai')->options(Ad::DISPLAYS),
                SelectFilter::make('format')->label('Jenis materi')->options(Ad::FORMATS),
            ])
            ->recordActions([
                self::previewAction(),
                EditAction::make(),
                ReplicateAction::make()->label('Duplikat')->modalDescription('Salinan dibuat nonaktif agar bisa disunting sebelum tayang.')
                    // Kolom hitungan dari withCount() bukan kolom tabel
                    ->excludeAttributes(['impressions_count', 'clicks_count'])
                    ->beforeReplicaSaved(fn (Ad $replica) => $replica->fill([
                        'name' => $replica->name.' (salinan)', 'is_active' => false, 'legacy_campaign_id' => null,
                    ])),
                DeleteAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function previewAction(): Action
    {
        return Action::make('preview')->label('Pratinjau')->icon('heroicon-o-eye')
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
