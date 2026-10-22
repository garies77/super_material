<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class SiteSettings extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.pages.site-settings';

    protected static ?string $title = 'Pengaturan situs';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $settings = Setting::all_();
        if (isset($settings['hero_banner_active'])) {
            $settings['hero_banner_active'] = Setting::getBool('hero_banner_active');
        }
        $settings['site_brand_name'] ??= Setting::get('site_brand_name', 'Supermaterial');
        $settings['site_brand_accent'] ??= Setting::get('site_brand_accent', 'material');
        foreach (Setting::THEME_DEFAULTS as $key => $default) {
            $settings[$key] = Setting::themeColor($key);
        }
        $this->form->fill($settings);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Hero & Banner Kampanye Beranda')
                ->description('Kelola gambar banner hero pada halaman utama. Sangat fleksibel untuk kampanye bundling bulanan, diskon seasonal, atau promo khusus.')
                ->collapsible()
                ->columns(2)
                ->schema([
                    Toggle::make('hero_banner_active')
                        ->label('Aktifkan Banner Kampanye Hero')
                        ->helperText('Tampilkan materi visual kampanye bundling / promo di area hero beranda.')
                        ->default(true),
                    Select::make('hero_banner_style')
                        ->label('Gaya Tampilan Hero')
                        ->options([
                            'card' => 'Showcase Card di Samping Form Pencarian (Direkomendasikan - 2 Kolom)',
                            'banner' => 'Banner Strip Lebar di Atas Kategori',
                            'background' => 'Latar Belakang Gambar Hero Penuh (Dark Overlay)',
                        ])
                        ->default('card'),
                    FileUpload::make('hero_banner_image')
                        ->label('Gambar Banner / Poster Kampanye')
                        ->image()
                        ->disk('public')
                        ->directory('banners')
                        ->visibility('public')
                        ->helperText('Unggah gambar materi kampanye bundling atau flyer promo (JPG, PNG, WebP). Bebas diganti kapan saja.')
                        ->columnSpanFull(),
                    TextInput::make('hero_banner_badge')
                        ->label('Teks Label / Badge Promo')
                        ->placeholder('Contoh: Promo Bundling Bulan Ini')
                        ->helperText('Muncul sebagai badge sorotan beranimasi.'),
                    TextInput::make('hero_banner_title')
                        ->label('Judul Kampanye')
                        ->placeholder('Contoh: Paket Bundling Renovasi Rumah Hemat 30%'),
                    Textarea::make('hero_banner_desc')
                        ->label('Deskripsi Singkat Kampanye')
                        ->placeholder('Contoh: Beli paket semen + cat tembok + pelapis anti bocor hemat lebih dari 30%.')
                        ->rows(2)
                        ->columnSpanFull(),
                    TextInput::make('hero_banner_cta_text')
                        ->label('Teks Tombol Aksi (CTA)')
                        ->placeholder('Contoh: Lihat Paket Bundling / Pesan via WhatsApp'),
                    TextInput::make('hero_banner_cta_url')
                        ->label('Link URL Tujuan (CTA)')
                        ->placeholder('Contoh: /produk atau link WhatsApp atau URL halaman promo')
                        ->helperText('Bisa berupa link halaman (/produk) atau link WhatsApp / marketplace.')
                        ->columnSpanFull(),
                ]),
            Section::make('Umum')->columns(2)->schema([
                TextInput::make('site_tagline')->label('Tagline')->columnSpanFull(),
                Textarea::make('site_description')->label('Deskripsi situs (beranda)')->rows(3)->columnSpanFull(),
                TextInput::make('whatsapp_number')->label('Nomor WhatsApp default')->helperText('Format 08xx atau 628xx.'),
                TextInput::make('contact_email')->label('Email kontak')->email(),
                TextInput::make('contact_address')->label('Alamat')->columnSpanFull(),
            ]),
            Section::make('Logo & Identitas Brand')
                ->description('Atur nama brand yang tampil di header/footer dan unggah logo situs. Anda bebas mengubah nama brand kapan saja.')
                ->columns(2)
                ->schema([
                    TextInput::make('site_brand_name')
                        ->label('Nama Brand / Situs')
                        ->placeholder('Contoh: Supermaterial atau Mitra Bangunan')
                        ->helperText('Teks nama brand yang tampil di header dan footer. Bebas diubah kapan saja.')
                        ->required(),
                    TextInput::make('site_brand_accent')
                        ->label('Kata Aksen Brand (Warna Oranye)')
                        ->placeholder('Contoh: material')
                        ->helperText('Kata di dalam nama brand yang diberi warna oranye (misal: "material" pada Supermaterial). Kosongkan jika brand satu warna.'),
                    FileUpload::make('site_logo')
                        ->label('Upload File Logo')
                        ->image()
                        ->disk('public')
                        ->directory('logos')
                        ->visibility('public')
                        ->imagePreviewHeight('80')
                        ->helperText('Unggah logo situs dari perangkat Anda (PNG, JPG, SVG, WebP). Tampil mendampingi nama brand di header & footer.')
                        ->columnSpanFull(),
                    TextInput::make('org_name')
                        ->label('Nama Legal Organisasi (Schema SEO)')
                        ->placeholder(config('app.name'))
                        ->helperText('Digunakan untuk metadata schema SEO Google (Organization).'),
                    TextInput::make('org_logo_url')
                        ->label('Atau URL Logo Eksternal')
                        ->url()
                        ->placeholder('https://domain.com/logo.png')
                        ->helperText('Alternatif jika logo di-host di CDN eksternal (diabaikan jika file logo di atas sudah diunggah).'),
                    FileUpload::make('site_og_image')
                        ->label('Gambar Default Berbagi / SERP Thumbnail (Open Graph)')
                        ->image()
                        ->disk('public')
                        ->directory('seo')
                        ->visibility('public')
                        ->imagePreviewHeight('100')
                        ->helperText('Rasio 1.91:1 (rekomendasi 1200 x 630 px). Digunakan sebagai thumbnail default Google SERP dan preview sosial (WhatsApp, FB, X) untuk Beranda, Katalog, dan halaman yang belum memiliki gambar khusus.')
                        ->columnSpanFull(),
                ]),
            Section::make('Warna Tema Situs')
                ->description('Ubah warna situs publik tanpa coding. Perubahan langsung terlihat di seluruh halaman setelah disimpan.')
                ->columns(3)
                ->schema([
                    ColorPicker::make('theme_primary')
                        ->label('Warna Utama (Primary)')
                        ->helperText('Tombol utama, tautan, harga, dan penanda aktif.')
                        ->required(),
                    ColorPicker::make('theme_secondary')
                        ->label('Warna Kedua (Secondary)')
                        ->helperText('Warna gelap: teks judul, tombol gelap, hero, dan footer.')
                        ->required(),
                    ColorPicker::make('theme_accent')
                        ->label('Warna Aksen (Accent)')
                        ->helperText('Highlight, hover tombol, gradasi, dan teks aksen di area gelap.')
                        ->required(),
                ]),
            Section::make('Pelacakan')->columns(2)->schema([
                TextInput::make('ga4_id')->label('Google Analytics 4 ID')->placeholder('G-XXXXXXXXXX'),
                TextInput::make('meta_pixel_id')->label('Meta Pixel ID (opsional)'),
            ]),
        ]);
    }

    public function save(): void
    {
        foreach ($this->form->getState() as $key => $value) {
            Setting::put($key, $value);
        }

        Notification::make()->title('Pengaturan disimpan')->success()->send();
    }
}
