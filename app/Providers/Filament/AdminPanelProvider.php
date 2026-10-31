<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Models\Setting;
use App\Support\Favicon;
use Filament\FontProviders\GoogleFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsIconAlias;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->profile()
            ->brandName('Supermaterial')
            ->brandLogo(fn () => Setting::logoUrl())
            ->brandLogoHeight('2rem')
            // Mengikuti logo di Pengaturan situs; tanpa logo memakai monogram public/favicon.svg
            ->favicon(fn () => Favicon::url())
            // Sidebar bisa diciutkan jadi deretan ikon (tooltip di tiap ikon); status tersimpan di browser
            ->sidebarCollapsibleOnDesktop()
            // Tombol buka/tutup sidebar: ikon panel (jendela dengan kolom kiri) di kedua status, bukan panah
            ->icons(array_fill_keys([
                PanelsIconAlias::SIDEBAR_COLLAPSE_BUTTON,
                PanelsIconAlias::SIDEBAR_COLLAPSE_BUTTON_RTL,
                PanelsIconAlias::SIDEBAR_EXPAND_BUTTON,
                PanelsIconAlias::SIDEBAR_EXPAND_BUTTON_RTL,
            ], new HtmlString('<svg viewBox="0 0 24 24" width="100%" height="100%" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="3"/><path d="M9.5 4v16"/></svg>')))
            // Tema & design token (resources/css/filament/admin/theme.css, dikompilasi Vite: npm run build)
            ->viteTheme('resources/css/filament/admin/theme.css')
            // Font sama dengan situs publik: Plus Jakarta Sans (teks) + Outfit (judul, lihat theme.css); Google Fonts sudah diizinkan CSP
            ->font('Plus Jakarta Sans', url: 'https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap', provider: GoogleFontProvider::class)
            // Gamifikasi: confetti saat misi kelengkapan 100% atau konten diajukan dengan skor tinggi
            ->renderHook(PanelsRenderHook::BODY_END, fn () => view('filament.celebrate'))
            // Grup tanpa ikon: Filament melarang ikon di grup sekaligus di itemnya, dan ikon item diperlukan untuk mode ciut
            ->navigationGroups([
                // Pekerjaan harian di atas (terbuka), alat & konfigurasi di bawah (tertutup)
                NavigationGroup::make('Katalog'),
                NavigationGroup::make('Konten'),
                NavigationGroup::make('Pemasaran'),
                NavigationGroup::make('Laporan')->collapsed(),
                NavigationGroup::make('Tampilan situs')->collapsed(),
                NavigationGroup::make('Pengaturan')->collapsed(),
            ])
            // Oranye brand (= theme_primary situs) dan abu-abu kebiruan yang selaras dengan navy brand (#121826)
            ->colors([
                'primary' => Color::hex('#f59309'),
                'gray' => Color::Slate,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            // Dashboard per peran (urutan $sort): antrean tinjauan → kartu XP → papan misi → statistik.
            // Widget "Welcome" bawaan Filament tidak dipakai; profil ada di menu akun.
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
