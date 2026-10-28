<?php

namespace App\Filament\Pages;

use App\Models\Ad;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\CustomForm;
use App\Models\HomeSection;
use App\Models\Product;
use App\Models\Setting;
use App\Services\HomepageService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

/**
 * Pusat kendali beranda: urutan, visibilitas, dan isi setiap seksi (tabel home_sections).
 * Banner kampanye hero tetap disimpan di Setting hero_banner_* agar data lama & gambar OG tidak berubah.
 */
class ManageHomepage extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.pages.manage-homepage';

    protected static ?string $title = 'Kustomisasi Beranda';

    protected static ?string $slug = 'kustomisasi-beranda';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan Tampilan';

    protected static ?int $navigationSort = 1;

    /** Field form banner hero => kunci Setting lama. */
    private const BANNER_KEYS = [
        'active' => 'hero_banner_active', 'style' => 'hero_banner_style', 'image' => 'hero_banner_image',
        'badge' => 'hero_banner_badge', 'title' => 'hero_banner_title', 'desc' => 'hero_banner_desc',
        'cta_text' => 'hero_banner_cta_text', 'cta_url' => 'hero_banner_cta_url',
    ];

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewHome')->label('Lihat Beranda Publik')->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')->url(url('/'))->openUrlInNewTab(),
        ];
    }

    public function mount(): void
    {
        $banner = collect(self::BANNER_KEYS)->map(fn ($key) => Setting::get($key))->all();
        $banner['active'] = Setting::getBool('hero_banner_active', true);
        $banner['style'] ??= 'card';

        $this->form->fill(['sections' => HomeSection::ordered()->get()->map(fn (HomeSection $s) => [
            'type' => $s->type,
            'data' => [
                'key' => $s->key,
                'title' => $s->title,
                'is_active' => $s->is_active,
                'settings' => self::cleanSettings($s->type, $s->settings ?? []),
            ] + ($s->type === 'hero' ? ['banner' => $banner] : []),
        ])->all()]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Builder::make('sections')->hiddenLabel()
                ->blocks([
                    $this->heroBlock(),
                    $this->categoriesBlock(),
                    $this->productsBlock(),
                    $this->articlesBlock(),
                    $this->bannerBlock(),
                    $this->ctaBlock(),
                    $this->formBlock(),
                ])
                ->reorderableWithDragAndDrop()->collapsible()->collapsed()->blockNumbers(false)
                ->addActionLabel('Tambah seksi')->blockPickerColumns(3),
        ]);
    }

    /** Toggle tampil + kunci internal, dipakai semua jenis seksi. */
    private function base(string $titleLabel = 'Judul seksi', bool $titleRequired = true): array
    {
        return [
            Hidden::make('key'),
            Grid::make(['default' => 1, 'md' => 3])->schema([
                TextInput::make('title')->label($titleLabel)->maxLength(150)->required($titleRequired)->live(onBlur: true)->columnSpan(['md' => 2]),
                Toggle::make('is_active')->label('Tampilkan di beranda')->default(true)->inline(false),
            ]),
        ];
    }

    private function ctaLink(string $text, string $url): Grid
    {
        return Grid::make(2)->schema([
            TextInput::make('settings.cta_text')->label('Teks tautan')->placeholder($text)->maxLength(60),
            TextInput::make('settings.cta_url')->label('URL tautan')->placeholder($url)->maxLength(500)
                ->helperText('Kosongkan salah satu untuk menyembunyikan tautan.'),
        ]);
    }

    private function label(string $type): \Closure
    {
        return fn (?array $state) => $state === null
            ? HomeSection::TYPES[$type]
            : HomeSection::TYPES[$type].(! in_array($type, ['hero', 'categories']) && filled($state['title'] ?? null) ? ' — '.$state['title'] : '')
                .(($state['is_active'] ?? true) ? '' : ' (disembunyikan)');
    }

    private function heroBlock(): Block
    {
        return Block::make('hero')->label($this->label('hero'))->icon('heroicon-o-sparkles')->maxItems(1)->schema([
            Hidden::make('key'),
            Hidden::make('title'),
            Toggle::make('is_active')->label('Tampilkan seksi hero')->default(true)
                ->helperText('Bila dimatikan, judul H1 tetap ada (tersembunyi) untuk mesin pencari.'),

            Fieldset::make('Teks hero')->columns(2)->schema([
                TextInput::make('settings.badge')->label('Label kecil di atas judul')->maxLength(60)->placeholder('Katalog bahan bangunan'),
                TextInput::make('settings.search_placeholder')->label('Teks contoh di kotak pencarian')->maxLength(80),
                TextInput::make('settings.heading')->label('Judul utama (H1)')->required()->maxLength(120),
                TextInput::make('settings.heading_accent')->label('Frasa aksen (warna gradasi oranye)')->maxLength(80)
                    ->helperText('Tampil setelah judul utama.'),
                Textarea::make('settings.description')->label('Deskripsi')->rows(2)->maxLength(300)->columnSpanFull()
                    ->helperText('Kosongkan untuk memakai deskripsi situs dari Pengaturan situs.'),
                Toggle::make('settings.show_search')->label('Tampilkan kotak pencarian')->default(true),
                Repeater::make('settings.trust_badges')->label('Poin jaminan (ikon centang)')->columnSpanFull()
                    ->schema([TextInput::make('text')->hiddenLabel()->required()->maxLength(60)])
                    ->grid(['md' => 3])->reorderableWithDragAndDrop()->maxItems(6)->defaultItems(0)->addActionLabel('Tambah poin'),
            ]),

            Fieldset::make('Banner kampanye hero')->columns(2)->schema([
                Toggle::make('banner.active')->label('Aktifkan banner kampanye')->live(),
                Select::make('banner.style')->label('Gaya tampilan')->selectablePlaceholder(false)->options([
                    'card' => 'Kartu di samping pencarian (2 kolom, direkomendasikan)',
                    'banner' => 'Strip promo lebar di bawah pencarian',
                    'background' => 'Gambar latar penuh (gelap)',
                ]),
                FileUpload::make('banner.image')->label('Gambar poster / flyer promo')->image()
                    ->imagePreset(fn (Get $get) => match ($get('banner.style')) {
                        'background' => 'hero_background',
                        'banner' => 'banner_desktop',
                        default => 'hero_card',
                    })
                    ->disk('public')->directory('banners')->visibility('public')->columnSpanFull()
                    ->required(fn (Get $get) => (bool) $get('banner.active'))
                    ->helperText('JPG, PNG, atau WebP. Kartu: 1200×750 px (16:10). Latar penuh: minimal 1920×900 px dengan objek utama di sisi kanan. Gambar ini juga dipakai sebagai gambar pratinjau saat beranda dibagikan.'),
                TextInput::make('banner.badge')->label('Badge promo beranimasi')->maxLength(60)->placeholder('Promo Bundling Spesial'),
                TextInput::make('banner.title')->label('Judul kampanye')->maxLength(120),
                Textarea::make('banner.desc')->label('Deskripsi singkat')->rows(2)->maxLength(300)->columnSpanFull(),
                TextInput::make('banner.cta_text')->label('Teks tombol')->maxLength(60)->placeholder('Lihat Promo Bundling'),
                TextInput::make('banner.cta_url')->label('URL tujuan tombol')->maxLength(500)->placeholder('/produk')
                    ->helperText('Halaman situs (/produk), WhatsApp, atau marketplace.'),
            ]),
        ]);
    }

    private function categoriesBlock(): Block
    {
        return Block::make('categories')->label($this->label('categories'))->icon('heroicon-o-squares-2x2')->maxItems(1)->schema([
            ...$this->base('Nama seksi (tidak tampil di situs)', false),
            Grid::make(2)->schema([
                Select::make('settings.source')->label('Kategori yang ditampilkan')->live()->selectablePlaceholder(false)
                    ->options(['all' => 'Semua kategori induk aktif', 'selected' => 'Pilih kategori tertentu'])->default('all'),
                Select::make('settings.columns')->label('Kolom di desktop')->selectablePlaceholder(false)
                    ->options([4 => '4 kolom', 3 => '3 kolom'])->default(4)
                    ->helperText('Tablet 3 kolom, ponsel 2 kolom.'),
            ]),
            Select::make('settings.category_ids')->label('Kategori (seret untuk mengurutkan)')->multiple()->reorderable()->searchable()
                ->options(fn () => Category::active()->orderBy('name')->pluck('name', 'id'))
                ->visible(fn (Get $get) => $get('settings.source') === 'selected')
                ->required(fn (Get $get) => $get('settings.source') === 'selected'),
        ]);
    }

    private function productsBlock(): Block
    {
        $mode = fn (string $m) => fn (Get $get) => $get('settings.selection_mode') === $m;

        return Block::make('products')->label($this->label('products'))->icon('heroicon-o-cube')->schema([
            ...$this->base(),
            Grid::make(2)->schema([
                Select::make('settings.selection_mode')->label('Cara memilih produk')->options(HomeSection::PRODUCT_MODES)
                    ->default('featured')->selectablePlaceholder(false)->live(),
                Select::make('settings.limit')->label('Jumlah produk')->options([4 => '4', 8 => '8', 12 => '12', 16 => '16'])
                    ->default(8)->selectablePlaceholder(false),
            ]),
            Select::make('settings.product_ids')->label('Produk (ketik untuk mencari, seret untuk mengurutkan)')
                ->multiple()->reorderable()->searchable()->maxItems(16)
                ->getSearchResultsUsing(fn (string $search) => Product::published()->where('name', 'like', "%{$search}%")->orderBy('name')->limit(50)->pluck('name', 'id')->all())
                ->getOptionLabelsUsing(fn (array $values) => Product::whereIn('id', $values)->pluck('name', 'id')->all())
                ->visible($mode('manual'))->required($mode('manual'))
                ->helperText('Hanya produk yang dipublikasikan yang tampil di beranda.'),
            Select::make('settings.category_id')->label('Kategori produk')->searchable()
                ->options(fn () => Category::active()->orderBy('name')->pluck('name', 'id'))
                ->visible($mode('category'))->required($mode('category'))
                ->helperText('Termasuk produk di subkategorinya, terbaru lebih dulu.'),
            $this->ctaLink('Lihat semua produk →', '/produk'),
        ]);
    }

    private function articlesBlock(): Block
    {
        $mode = fn (string $m) => fn (Get $get) => $get('settings.sort_by') === $m;

        return Block::make('articles')->label($this->label('articles'))->icon('heroicon-o-newspaper')->schema([
            ...$this->base(),
            Grid::make(2)->schema([
                Select::make('settings.sort_by')->label('Artikel yang ditampilkan')->options(HomeSection::ARTICLE_MODES)
                    ->default('latest')->selectablePlaceholder(false)->live(),
                Select::make('settings.limit')->label('Jumlah artikel')->options([3 => '3', 6 => '6'])
                    ->default(3)->selectablePlaceholder(false),
            ]),
            Select::make('settings.article_ids')->label('Artikel pilihan (3–6, seret untuk mengurutkan)')
                ->multiple()->reorderable()->searchable()->minItems(3)->maxItems(6)
                ->getSearchResultsUsing(fn (string $search) => Article::published()->where('title', 'like', "%{$search}%")->latest('published_at')->limit(50)->pluck('title', 'id')->all())
                ->getOptionLabelsUsing(fn (array $values) => Article::whereIn('id', $values)->pluck('title', 'id')->all())
                ->visible($mode('manual'))->required($mode('manual'))
                ->helperText('Artikel draf atau terjadwal tidak tampil sampai terbit.'),
            Select::make('settings.category_id')->label('Kategori artikel')->searchable()
                ->options(fn () => Category::whereHas('articles')->orderBy('name')->pluck('name', 'id'))
                ->visible($mode('category'))->required($mode('category')),
            $this->ctaLink('Semua artikel →', '/artikel'),
        ]);
    }

    private function bannerBlock(): Block
    {
        $custom = fn (Get $get) => $get('settings.source') !== 'campaign';

        return Block::make('banner')->label($this->label('banner'))->icon('heroicon-o-photo')->schema([
            ...$this->base('Nama banner (untuk teks alternatif)'),
            Select::make('settings.source')->label('Sumber banner')->live()->selectablePlaceholder(false)->default('custom')
                ->options(['custom' => 'Unggah gambar sendiri', 'campaign' => 'Pakai kampanye dari menu Kampanye']),
            Grid::make(2)->visible($custom)->schema([
                FileUpload::make('settings.image')->label('Gambar desktop')->image()->imagePreset('banner_desktop')->altTextField('settings.alt')->disk('public')->directory('banners')->visibility('public')
                    ->required($custom)->helperText('Disarankan 1200×300 px (4:1).'),
                FileUpload::make('settings.image_mobile')->label('Gambar ponsel (opsional)')->image()->imagePreset('banner_mobile')->altTextField('settings.alt')->disk('public')->directory('banners')->visibility('public')
                    ->helperText('Disarankan 800×600 px (4:3). Kosong = memakai gambar desktop.'),
                TextInput::make('settings.url')->label('URL tujuan saat diklik')->maxLength(500)->placeholder('/produk atau https://…'),
                TextInput::make('settings.alt')->label('Teks alternatif gambar')->maxLength(150)
                    ->helperText('Jelaskan isi banner untuk pembaca layar & SEO.'),
            ]),
            Select::make('settings.ad_id')->label('Kampanye')->searchable()
                ->options(fn () => Ad::where('placement', 'banner')->where('display', 'inline')->orderBy('name')->pluck('name', 'id'))
                ->visible(fn (Get $get) => $get('settings.source') === 'campaign')
                ->required(fn (Get $get) => $get('settings.source') === 'campaign')
                ->helperText('Hanya kampanye format Banner yang tampil di dalam halaman. Tampil hanya saat kampanye aktif & dalam jadwal; klik tercatat di Laporan Kampanye.'),
        ]);
    }

    private function formBlock(): Block
    {
        return Block::make('form')->label($this->label('form'))->icon('heroicon-o-clipboard-document-list')->schema([
            ...$this->base('Judul seksi', false),
            Select::make('settings.form_id')->label('Formulir')->required()->searchable()
                ->options(fn () => CustomForm::embedOptions())
                ->helperText('Hanya formulir dengan mode tampil "sematan" yang bisa dipilih. Buat formulir di menu Formulir.'),
            Textarea::make('settings.description')->label('Teks pengantar (opsional)')->rows(2)->maxLength(300)
                ->helperText('Kosong = deskripsi formulir. Judul kosong = judul formulir.'),
        ]);
    }

    private function ctaBlock(): Block
    {
        return Block::make('cta')->label($this->label('cta'))->icon('heroicon-o-chat-bubble-left-right')->schema([
            ...$this->base('Judul penawaran'),
            Textarea::make('settings.description')->label('Deskripsi ajakan')->rows(2)->maxLength(300),
            Grid::make(2)->schema([
                TextInput::make('settings.button_text')->label('Teks tombol')->default('Chat WhatsApp')->maxLength(40),
                TextInput::make('settings.whatsapp_number')->label('Nomor WhatsApp khusus (opsional)')->tel()->maxLength(20)
                    ->helperText('Kosong = nomor WhatsApp default di Pengaturan situs. Format 08xx atau 628xx.'),
            ]),
            TextInput::make('settings.message')->label('Pesan pembuka otomatis')->maxLength(200)
                ->default('Halo, saya ingin konsultasi kebutuhan material proyek.'),
        ]);
    }

    private static function cleanSettings(string $type, array $settings): array
    {
        if ($type === 'hero') {
            $settings['trust_badges'] = HomeSection::normalizeBadges($settings['trust_badges'] ?? []);
        }

        return $settings;
    }

    public function save(): void
    {
        $items = array_values($this->form->getState()['sections'] ?? []);
        $keep = [];

        foreach ($items as $i => $item) {
            $d = $item['data'];
            if ($item['type'] === 'hero' && isset($d['banner'])) {
                foreach (self::BANNER_KEYS as $field => $key) {
                    Setting::put($key, $d['banner'][$field] ?? null);
                }
            }

            $key = filled($d['key'] ?? null) ? $d['key'] : $item['type'].'_'.Str::lower(Str::random(6));
            HomeSection::updateOrCreate(['key' => $key], [
                'type' => $item['type'],
                'title' => $item['type'] === 'hero' ? 'Hero & Kampanye' : ($d['title'] ?? null),
                'is_active' => (bool) ($d['is_active'] ?? true),
                'sort_order' => $i + 1,
                'settings' => self::cleanSettings($item['type'], $d['settings'] ?? []),
            ]);
            $keep[] = $key;
        }

        HomeSection::whereNotIn('key', $keep)->get()->each->delete();
        HomepageService::flush();

        AuditLog::record(request(), 'homepage.update', null, [
            'sections' => count($keep),
            'active' => collect($items)->where('data.is_active', true)->count(),
        ]);

        $this->mount(); // isi ulang agar seksi baru mendapat kunci tetap
        Notification::make()->title('Beranda disimpan')->body('Perubahan langsung tampil di beranda publik.')->success()->send();
    }
}
