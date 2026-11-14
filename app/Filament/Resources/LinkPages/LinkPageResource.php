<?php

namespace App\Filament\Resources\LinkPages;

use App\Filament\Pages\LinkReport;
use App\Filament\Resources\LinkPages\Pages\CreateLinkPage;
use App\Filament\Resources\LinkPages\Pages\EditLinkPage;
use App\Filament\Resources\LinkPages\Pages\ListLinkPages;
use App\Filament\Support\DescriptiveFileName;
use App\Filament\Support\LinkBioPreview;
use App\Models\LinkItem;
use App\Models\LinkPage;
use App\Models\LinkStat;
use App\Models\Product;
use App\Models\SalesChannel;
use App\Support\LinkBioDesign;
use App\Support\LinkTarget;
use App\Support\Reports\ClickReport;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Link Bio (seperti Linktree): halaman /l/{slug} berisi tombol tautan, produk, dan WhatsApp untuk bio Instagram/TikTok.
 * Editor: pengaturan di kiri (tab Konten, Header, Desain, Pengaturan), pratinjau ponsel langsung di kanan.
 */
class LinkPageResource extends Resource
{
    protected static ?string $model = LinkPage::class;

    protected static ?string $slug = 'link-bio';

    protected static ?string $modelLabel = 'Link Bio';

    protected static ?string $pluralModelLabel = 'Link Bio';

    protected static ?string $navigationLabel = 'Link Bio';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static string|\UnitEnum|null $navigationGroup = 'Pemasaran';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    /** Kunci desain yang diisi ulang saat tema siap pakai dipilih (tata letak header tidak ikut berubah). */
    private const PRESET_KEYS = ['bg_type', 'bg_color', 'bg_color_2', 'text_color', 'button_style', 'button_radius', 'button_color', 'button_text_color', 'accent_color', 'font'];

    public static function canAccess(): bool
    {
        return in_array(auth()->user()?->role, ['admin', 'editor'], true);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(['default' => 1, 'lg' => 1, 'xl' => 12])->components([
            Tabs::make('editor')->extraAttributes(['class' => 'sm-lb-editor'])->columnSpan(['xl' => 7, '2xl' => 8])->persistTabInQueryString()->tabs([
                Tab::make('Konten')->icon(Heroicon::OutlinedSquare3Stack3d)->schema(self::contentTab()),
                Tab::make('Header')->icon(Heroicon::OutlinedUserCircle)->schema(self::headerTab()),
                Tab::make('Desain')->icon(Heroicon::OutlinedPaintBrush)->schema(self::designTab()),
                Tab::make('Pengaturan')->icon(Heroicon::OutlinedCog6Tooth)->schema(self::settingsTab()),
            ]),

            Group::make([LinkBioPreview::make()])->columnSpan(['xl' => 5, '2xl' => 4]),
        ]);
    }

    /** Field yang memperbarui pratinjau saja (tanpa merender ulang seluruh form). */
    private static function live(Field $field, bool $typing = false): Field
    {
        return ($typing ? $field->live(debounce: 600) : $field->live())->afterStateUpdated(LinkBioPreview::refresh());
    }

    /** @return array<int, mixed> */
    private static function contentTab(): array
    {
        $type = fn (string ...$types) => fn (Get $get) => in_array($get('type'), $types, true);

        return [
            Section::make('Profil')->columns(2)->schema([
                self::live(TextInput::make('title')->label('Nama tampil')->required()->maxLength(255), typing: true)
                    ->afterStateUpdated(function (Set $set, ?string $state, string $operation) {
                        if ($operation === 'create') {
                            $set('slug', Str::slug((string) $state));
                        }
                    }),
                self::live(Textarea::make('bio')->label('Bio')->rows(2)->maxLength(300), typing: true)->columnSpanFull(),
            ]),

            Section::make('Tombol & tautan')->description('Seret untuk mengurutkan. Klik setiap tombol tercatat di Laporan Link Bio.')->schema([
                Repeater::make('items')->relationship()->label('')->orderColumn('sort')->defaultItems(0)
                    ->reorderableWithDragAndDrop()->collapsible()->cloneable()->addActionLabel('Tambah tombol')->columns(2)
                    ->afterStateUpdated(LinkBioPreview::refresh())
                    ->itemLabel(fn (array $state) => ($state['label'] ?? null)
                        ? ($state['label'].(($state['is_active'] ?? true) ? '' : ' (nonaktif)'))
                        : 'Tombol baru')
                    ->schema([
                        Select::make('type')->label('Jenis')->options(LinkItem::TYPES)->default('link')->required()->live()->selectablePlaceholder(false),
                        self::live(TextInput::make('label')->label(fn (Get $get) => $get('type') === 'heading' ? 'Teks judul' : 'Teks tombol')->required()->maxLength(120), typing: true),

                        Group::make(LinkTarget::fields())->columns(2)->columnSpanFull()->visible($type('link')),

                        self::live(Select::make('product_id')->label('Produk')->searchable()->required($type('product'))
                            ->options(fn () => Product::published()->orderBy('name')->pluck('name', 'id')))->visible($type('product')),
                        Select::make('sales_channel_id')->label('Beli lewat')->visible($type('product'))
                            ->options(fn () => SalesChannel::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                            ->placeholder('Halaman produk di situs')
                            ->helperText('Kosong: menuju halaman produk. Marketplace: lewat /go agar klik masuk laporan produk.'),

                        TextInput::make('whatsapp_number')->label('Nomor WhatsApp')->tel()->maxLength(30)->visible($type('whatsapp'))
                            ->regex('/^[0-9+()\-\s.]{6,25}$/')->placeholder('Kosong = nomor di Pengaturan situs'),
                        TextInput::make('whatsapp_message')->label('Pesan awal')->maxLength(500)->visible($type('whatsapp')),

                        self::live(FileUpload::make('thumbnail_path')->label('Gambar kecil (opsional)')->image()->imagePreset('square_small')
                            ->disk('public')->directory('link-bio')->visibility('public'))
                            ->visible($type('link', 'product', 'whatsapp'))
                            ->helperText(fn (Get $get) => $get('type') === 'product' ? 'Kosong = foto produk.' : null),

                        Group::make([
                            self::live(Toggle::make('is_active')->label('Aktif')->default(true)->inline(false)),
                            self::live(Toggle::make('is_highlighted')->label('Tonjolkan')->inline(false))->visible($type('link', 'product', 'whatsapp')),
                        ])->columns(2),

                        DateTimePicker::make('starts_at')->label('Mulai tampil (WIB)')->seconds(false)->timezone(ClickReport::TIMEZONE),
                        DateTimePicker::make('ends_at')->label('Berhenti tampil (WIB)')->seconds(false)->timezone(ClickReport::TIMEZONE)->after('starts_at'),
                    ]),
            ]),

            Section::make('Media sosial')->description('Ikon di bawah bio. URL harus https dan dari domain resmi platform.')->schema([
                Repeater::make('socials')->label('')->defaultItems(0)->reorderableWithDragAndDrop()->addActionLabel('Tambah akun')->columns(2)
                    ->afterStateUpdated(LinkBioPreview::refresh())
                    ->schema([
                        self::live(Select::make('platform')->label('Platform')->options(LinkPage::socialOptions())->required()),
                        self::live(TextInput::make('url')->label('URL profil')->required()->maxLength(500), typing: true)
                            ->rule(fn (Get $get) => function (string $attribute, mixed $value, Closure $fail) use ($get) {
                                if (! LinkPage::isValidSocialUrl($get('platform'), $value)) {
                                    $fail('URL harus https dan berada di domain resmi platform yang dipilih.');
                                }
                            }),
                    ]),
            ]),
        ];
    }

    /** @return array<int, mixed> */
    private static function headerTab(): array
    {
        $layout = fn (string ...$layouts) => fn (Get $get) => in_array($get('design.layout'), $layouts, true);

        return [
            Section::make('Tata letak')->schema([
                ToggleButtons::make('design.layout')->label('')->options(LinkBioDesign::LAYOUTS)->inline()->required()
                    ->default('classic')->live()
                    ->icons([
                        'classic' => Heroicon::OutlinedUserCircle,
                        'hero' => Heroicon::OutlinedPhoto,
                        'banner' => Heroicon::OutlinedRectangleGroup,
                        'cutout' => Heroicon::OutlinedScissors,
                        'shape' => Heroicon::OutlinedSparkles,
                    ])
                    ->helperText(fn (Get $get) => LinkBioDesign::LAYOUT_HINTS[$get('design.layout')] ?? null),
                self::live(ToggleButtons::make('design.shape')->label('Bentuk foto')->options(LinkBioDesign::SHAPES)->inline()->default('circle'))
                    ->visible($layout('shape')),
            ]),

            Section::make('Gambar')->columns(2)->schema([
                self::live(FileUpload::make('avatar_path')->label(fn (Get $get) => $get('design.layout') === 'hero' ? 'Foto utama (lebar penuh)' : 'Foto profil / logo')
                    ->image()->disk('public')->directory('link-bio')->visibility('public')->maxSize(4096)
                    ->getUploadedFileNameForStorageUsing(DescriptiveFileName::from('title', 'link-bio')))
                    ->helperText(fn (Get $get) => $get('design.layout') === 'cutout' ? 'Pakai PNG tanpa latar agar hasilnya rapi.' : 'Kosong = huruf pertama nama.'),
                self::live(FileUpload::make('banner_path')->label('Gambar banner')
                    ->image()->disk('public')->directory('link-bio')->visibility('public')->maxSize(4096)
                    ->getUploadedFileNameForStorageUsing(DescriptiveFileName::from('title', 'link-bio', 'banner')))
                    ->helperText('Disarankan 1200×400 (rasio 3:1).')
                    ->visible($layout('banner')),
            ]),

            Section::make('Judul')->schema([
                self::live(ToggleButtons::make('design.title_size')->label('Ukuran nama')->options(LinkBioDesign::TITLE_SIZES)->inline()->default('normal')),
            ]),
        ];
    }

    /** @return array<int, mixed> */
    private static function designTab(): array
    {
        $defaults = LinkBioDesign::preset('light');
        $color = fn (string $key, string $label) => self::live(ColorPicker::make("design.{$key}")->label($label)->default($defaults[$key])
            ->regex('/^#[0-9a-fA-F]{6}$/')->validationMessages(['regex' => 'Pakai kode warna hex 6 digit, mis. #0B3D91.']));
        $background = fn (string ...$types) => fn (Get $get) => in_array($get('design.bg_type'), $types, true);

        return [
            Section::make('Tema siap pakai')->description('Mengisi semua warna, tombol, dan font sekaligus. Setelah itu tiap pengaturan masih bisa diubah.')->schema([
                Select::make('theme')->label('Tema')->options(LinkBioDesign::presetOptions())->default('light')->required()
                    ->selectablePlaceholder(false)->live()
                    ->afterStateUpdated(function (Set $set, Get $get, ?string $state) {
                        $preset = LinkBioDesign::preset($state);
                        foreach (self::PRESET_KEYS as $key) {
                            $set("design.{$key}", $preset[$key]);
                        }
                    }),
            ]),

            Section::make('Latar')->columns(2)->schema([
                ToggleButtons::make('design.bg_type')->label('Jenis latar')->options(LinkBioDesign::BACKGROUNDS)->inline()->default($defaults['bg_type'])
                    ->live()->columnSpanFull(),
                $color('bg_color', 'Warna latar')->label(fn (Get $get) => $get('design.bg_type') === 'gradient' ? 'Warna atas' : 'Warna latar'),
                $color('bg_color_2', 'Warna bawah')->visible($background('gradient')),
                self::live(FileUpload::make('design.bg_image_path')->label('Gambar latar')->image()->disk('public')->directory('link-bio')
                    ->visibility('public')->maxSize(4096))
                    ->helperText('Gambar diberi lapisan gelap tipis agar teks tetap terbaca.')
                    ->visible($background('image'))->columnSpanFull(),
            ]),

            Section::make('Tombol')->columns(2)->schema([
                self::live(ToggleButtons::make('design.button_style')->label('Gaya')->options(LinkBioDesign::BUTTON_STYLES)->inline()->default($defaults['button_style']))
                    ->columnSpanFull(),
                self::live(ToggleButtons::make('design.button_radius')->label('Sudut')->options(LinkBioDesign::BUTTON_RADII)->inline()->default($defaults['button_radius']))
                    ->columnSpanFull(),
                $color('button_color', 'Warna tombol'),
                $color('button_text_color', 'Warna teks tombol'),
                $color('accent_color', 'Warna tombol yang ditonjolkan')
                    ->helperText('Dipakai tombol dengan "Tonjolkan" aktif dan foto pengganti.'),
            ]),

            Section::make('Teks')->columns(2)->schema([
                $color('text_color', 'Warna teks'),
                self::live(Select::make('design.font')->label('Font')->default($defaults['font'])->selectablePlaceholder(false)
                    ->options(array_map(fn (array $font) => $font[0], LinkBioDesign::FONTS))),
            ]),
        ];
    }

    /** @return array<int, mixed> */
    private static function settingsTab(): array
    {
        return [
            Section::make('Alamat & status')->schema([
                TextInput::make('slug')->label('Alamat')->required()->unique(ignoreRecord: true)->maxLength(100)
                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')->prefix('/l/')
                    ->validationMessages(['regex' => 'Gunakan huruf kecil, angka, dan tanda hubung.']),
                Toggle::make('is_published')->label('Terbit')->default(false)
                    ->helperText('Draf hanya bisa dilihat tim lewat tombol Lihat halaman, dan tidak dihitung di laporan.'),
                Text::make(fn (?LinkPage $record) => $record
                    ? 'Pasang di bio Instagram: '.$record->url().'?utm_source=instagram'
                    : 'Alamat tersedia setelah disimpan.'),
                Text::make(fn (?LinkPage $record) => $record ? 'Pasang di bio TikTok: '.$record->url().'?utm_source=tiktok' : '')
                    ->visible(fn (?LinkPage $record) => (bool) $record),
                Text::make('Aplikasi Instagram & TikTok sering tidak mengirim asal kunjungan; tambahan ?utm_source membuat sumbernya tetap terbaca di laporan.')
                    ->visible(fn (?LinkPage $record) => (bool) $record),
            ]),

            Section::make('SEO')->schema([
                TextInput::make('meta_title')->label('Judul halaman')->maxLength(70)->placeholder('Kosong = nama tampil'),
                Textarea::make('meta_description')->label('Deskripsi')->rows(2)->maxLength(160)->placeholder('Kosong = bio'),
            ]),
        ];
    }

    public static function table(Table $table): Table
    {
        $since = fn () => now(ClickReport::TIMEZONE)->subDays(29)->toDateString();

        return $table
            ->defaultSort('updated_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withSum(['stats as views_30d' => fn ($q) => $q->where('link_item_id', LinkStat::PAGE_VIEW)->where('date', '>=', $since())], 'count')
                ->withSum(['stats as clicks_30d' => fn ($q) => $q->where('link_item_id', '>', LinkStat::PAGE_VIEW)->where('date', '>=', $since())], 'count')
                ->withCount('items'))
            ->columns([
                TextColumn::make('title')->label('Nama')->searchable()->weight('medium')
                    ->description(fn (LinkPage $r) => '/l/'.$r->slug),
                TextColumn::make('link')->label('Tautan untuk bio')->state(fn (LinkPage $r) => $r->url())
                    ->copyable()->copyMessage('Tautan disalin')->color('gray')->toggleable(),
                IconColumn::make('is_published')->label('Terbit')->boolean(),
                TextColumn::make('items_count')->label('Tombol')->numeric(),
                TextColumn::make('views_30d')->label('Tayangan 30 hr')->numeric()->default(0)->sortable(),
                TextColumn::make('clicks_30d')->label('Klik 30 hr')->numeric()->default(0)->sortable(),
            ])
            ->recordActions([
                self::openAction(),
                Action::make('report')->label('Laporan')->icon('heroicon-o-chart-bar')->color('gray')
                    ->url(fn (LinkPage $record) => LinkReport::getUrl(['filters' => ['page' => $record->id]])),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function openAction(): Action
    {
        return Action::make('open')->label('Lihat halaman')->icon('heroicon-o-arrow-top-right-on-square')->color('gray')
            ->url(fn (LinkPage $record) => $record->url())->openUrlInNewTab();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLinkPages::route('/'),
            'create' => CreateLinkPage::route('/create'),
            'edit' => EditLinkPage::route('/{record}/edit'),
        ];
    }
}
