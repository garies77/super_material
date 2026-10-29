{{--
    Layout halaman login (App\Filament\Pages\Auth\Login::$layout): dua kolom di desktop, panel brand di kiri dan
    form Filament di kanan. Turunan dari filament-panels::components.layout.simple sehingga form, validasi, dan
    render hook bawaan tetap berjalan. Gaya: .sm-auth-* di resources/css/filament/admin/theme.css
--}}
@php
    use Filament\Support\Enums\Width;
    use Filament\Support\Facades\FilamentView;
    use Filament\View\PanelsRenderHook;

    $livewire ??= null;
    $renderHookScopes = $livewire?->getRenderHookScopes();
    $brand = filament()->getBrandName();

    $highlights = [
        ['heroicon-o-cube', 'Katalog & harga terpusat', 'Produk, varian, foto, dan tautan marketplace dalam satu tempat.'],
        ['heroicon-o-document-magnifying-glass', 'Konten siap bersaing di Google', 'Artikel dan produk dengan skor kelengkapan yang terukur.'],
        ['heroicon-o-chart-bar', 'Kinerja yang terpantau', 'Klik ke marketplace dan kampanye tercatat otomatis.'],
    ];
@endphp

<x-filament-panels::layout.base :livewire="$livewire">
    <div class="sm-auth">
        <aside class="sm-auth-brand" aria-hidden="true">
            <div class="sm-auth-brand-top">
                <img src="{{ asset('favicon.svg') }}" alt="" class="sm-auth-mark" width="40" height="40">
                <span class="sm-auth-brand-name">{{ $brand }}</span>
            </div>

            <div class="sm-auth-brand-body">
                <p class="sm-auth-eyebrow">Panel admin</p>
                <h2 class="sm-auth-title">Kelola toko bahan bangunan Anda dengan lebih rapi.</h2>

                <ul class="sm-auth-highlights">
                    @foreach ($highlights as [$icon, $title, $text])
                        <li>
                            <span class="sm-auth-highlight-icon"><x-filament::icon :icon="$icon" /></span>
                            <span>
                                <strong>{{ $title }}</strong>
                                <span>{{ $text }}</span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <p class="sm-auth-brand-footer">&copy; {{ now()->year }} {{ $brand }}</p>
        </aside>

        <div class="sm-auth-main">
            {{ FilamentView::renderHook(PanelsRenderHook::SIMPLE_LAYOUT_START, scopes: $renderHookScopes) }}

            <main id="fi-main-content" tabindex="-1" class="fi-simple-main fi-width-{{ Width::Medium->value }} sm-auth-card">
                {{ $slot }}
            </main>

            <p class="sm-auth-help">
                <x-filament::icon icon="heroicon-m-shield-check" class="sm-auth-help-icon" />
                <span>Akses khusus tim. Lupa password? Hubungi Admin untuk mengatur ulang.</span>
            </p>

            {{ FilamentView::renderHook(PanelsRenderHook::FOOTER, scopes: $renderHookScopes) }}

            {{ FilamentView::renderHook(PanelsRenderHook::SIMPLE_LAYOUT_END, scopes: $renderHookScopes) }}
        </div>
    </div>
</x-filament-panels::layout.base>
