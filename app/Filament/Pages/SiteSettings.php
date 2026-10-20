<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
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
            Section::make('Organisasi (JSON-LD)')->columns(2)->schema([
                TextInput::make('org_name')->label('Nama organisasi'),
                TextInput::make('org_logo_url')->label('URL logo')->url(),
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
