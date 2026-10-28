<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Models\Setting;
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
use Filament\Widgets\AccountWidget;
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
            // Sidebar bisa diciutkan jadi deretan ikon (tooltip di tiap ikon); status tersimpan di browser
            ->sidebarCollapsibleOnDesktop()
            // Tombol buka/tutup sidebar: ikon panel (jendela dengan kolom kiri) di kedua status, bukan panah
            ->icons(array_fill_keys([
                PanelsIconAlias::SIDEBAR_COLLAPSE_BUTTON,
                PanelsIconAlias::SIDEBAR_COLLAPSE_BUTTON_RTL,
                PanelsIconAlias::SIDEBAR_EXPAND_BUTTON,
                PanelsIconAlias::SIDEBAR_EXPAND_BUTTON_RTL,
            ], new HtmlString('<svg viewBox="0 0 24 24" width="100%" height="100%" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="3"/><path d="M9.5 4v16"/></svg>')))
            ->renderHook(PanelsRenderHook::STYLES_AFTER, fn () => view('filament.admin-styles'))
            // Gamifikasi: confetti saat misi kelengkapan 100% atau konten diajukan dengan skor tinggi
            ->renderHook(PanelsRenderHook::BODY_END, fn () => view('filament.celebrate'))
            // Grup tanpa ikon: Filament melarang ikon di grup sekaligus di itemnya, dan ikon item diperlukan untuk mode ciut
            ->navigationGroups([
                NavigationGroup::make('Katalog'),
                NavigationGroup::make('Konten'),
                NavigationGroup::make('Laporan')->collapsed(),
                NavigationGroup::make('Pengaturan Tampilan')->collapsed(),
                NavigationGroup::make('Pengaturan')->collapsed(),
            ])
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
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
