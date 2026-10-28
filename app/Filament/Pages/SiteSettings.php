<?php

namespace App\Filament\Pages;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Support\SudoMode;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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

    protected static ?int $navigationSort = 1;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $settings = Setting::all_();
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
                ->description('Teks hero, banner kampanye, urutan seksi, produk, dan artikel beranda kini diatur di satu tempat: Kustomisasi Beranda. Data banner lama tetap terpakai.')
                ->afterHeader([
                    Action::make('openHomepage')->label('Buka Kustomisasi Beranda')->icon('heroicon-o-home')
                        ->url(fn () => ManageHomepage::getUrl()),
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
                        ->label('Unggah logo')
                        ->image()
                        ->disk('public')
                        ->directory('logos')
                        ->visibility('public')
                        ->imagePreviewHeight('80')
                        ->helperText('PNG, JPG, SVG, atau WebP. Logo dimuat di setiap halaman, jadi usahakan di bawah 50 KB: PNG/WebP tinggi ±96 px, atau SVG vektor murni. SVG hasil ekspor Figma yang berisi gambar bitmap biasanya ratusan KB.')
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
                        ->imagePreset('og')
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
            Section::make('Konfirmasi')->schema([
                TextInput::make('confirm_password')->label('Password Anda')->password()->revealable()->dehydrated(false)
                    ->helperText('Wajib diisi bila nama website, nama singkat, atau palet warna diubah (PRD Keamanan 2.2).'),
            ]),
        ]);
    }

    /** Kunci branding yang perubahannya memerlukan konfirmasi password. */
    public const SENSITIVE_KEYS = ['site_brand_name', 'site_brand_accent', 'theme_primary', 'theme_secondary', 'theme_accent'];

    public function save(): void
    {
        $state = $this->form->getState();
        $changes = collect(self::SENSITIVE_KEYS)
            ->filter(fn ($key) => array_key_exists($key, $state) && (string) $state[$key] !== (string) Setting::get($key))
            ->mapWithKeys(fn ($key) => [$key => ['from' => Setting::get($key), 'to' => $state[$key]]])
            ->all();

        if ($changes) {
            SudoMode::confirm($this->data['confirm_password'] ?? null, 'branding.update', 'data.confirm_password');
        }

        foreach ($state as $key => $value) {
            Setting::put($key, $value);
        }

        if ($changes) {
            AuditLog::record(request(), 'branding.update', null, $changes);
        }
        $this->data['confirm_password'] = null;

        Notification::make()->title('Pengaturan disimpan')->success()->send();
    }
}
