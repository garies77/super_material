{{--
    Seksi Hero. Teks dari pengaturan seksi; banner kampanye dari Setting hero_banner_* ($data['banner']).
    Satu-satunya H1 di beranda.
--}}
@php
    $banner = $data['banner'] ?? null;
    $style = $banner['style'] ?? null;
    $badge = $section->setting('badge');
    $heading = $section->setting('heading', 'Temukan bahan bangunan');
    $accent = $section->setting('heading_accent');
    $description = $section->setting('description') ?: \App\Models\Setting::get('site_description', 'Bandingkan produk, baca panduan memilih, lalu beli lewat Shopee, Tokopedia, Lazada, TikTok Shop, atau konsultasi langsung via WhatsApp.');
    $showSearch = (bool) $section->setting('show_search', true);
    $placeholder = $section->setting('search_placeholder', 'Cari semen, keramik, baja ringan…');
    $trustBadges = collect(\App\Models\HomeSection::normalizeBadges($section->setting('trust_badges', [])))->pluck('text');
    $twoCol = in_array($style, ['card', 'background']);
    // Banner kampanye biasanya PNG besar dan menjadi elemen LCP beranda: kirim WebP sesuai lebar layar
    $bannerSrcset = $banner ? \App\Support\ImageDerivatives::srcset($banner['image'], [640, 1024, 1600]) : '';
@endphp
<section class="relative overflow-hidden bg-ink-950 text-white">
    @if($style === 'background')
        {{-- Latar penuh: gelap di kiri untuk teks H1, terang di kanan untuk visual promo --}}
        <div class="absolute inset-0 z-0 overflow-hidden">
            <img src="{{ \App\Support\ImageDerivatives::resized($banner['image'], 1600) }}" @if($bannerSrcset) srcset="{{ $bannerSrcset }}" sizes="100vw" @endif width="1600" height="900" fetchpriority="high" alt="{{ $banner['title'] ?: 'Promo Bundling' }}"
                 class="h-full w-full object-cover object-right lg:object-center opacity-85 transition duration-500">
            <div class="absolute inset-0 bg-gradient-to-r from-ink-950 via-ink-950/85 sm:via-ink-950/70 to-transparent"></div>
            <div class="absolute inset-0 bg-gradient-to-t from-ink-950/90 via-transparent to-ink-950/30 sm:from-ink-950/60"></div>
        </div>
    @else
        <div class="hero-grid absolute inset-0"></div>
        <div class="absolute -right-32 -top-32 h-96 w-96 rounded-full bg-brand-500/25 blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-40 left-1/4 h-80 w-80 rounded-full bg-brand-600/20 blur-3xl pointer-events-none"></div>
    @endif

    <div class="container-x relative py-16 sm:py-24">
        <div @class([
            'grid items-center gap-10 lg:grid-cols-12 lg:gap-12' => $style === 'card',
            'max-w-4xl' => $style === 'banner',
            'relative z-10 max-w-2xl' => $style === 'background',
        ])>
            <div @class(['lg:col-span-7' => $style === 'card'])>
                @if($style === 'background' && $banner['badge'])
                    <a href="{{ $banner['cta_url'] }}" class="rise mb-4 inline-flex items-center gap-2 rounded-full border border-brand-400/40 bg-brand-500/20 px-4 py-1.5 text-xs font-semibold text-brand-200 backdrop-blur-md transition hover:bg-brand-500/30">
                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-brand-400"></span> {{ $banner['badge'] }} →
                    </a>
                @elseif($badge)
                    <p class="rise inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-4 py-1.5 text-xs font-semibold text-brand-300">
                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-brand-400"></span> {{ $badge }}
                    </p>
                @endif

                <h1 @class([
                    'rise-2 font-display text-4xl font-extrabold text-white',
                    'mt-5 leading-[1.1] sm:text-5xl xl:text-6xl' => $style === 'card',
                    'mt-4 leading-[1.08] sm:text-5xl xl:text-6xl' => $style === 'background',
                    'mt-6 leading-[1.08] sm:text-6xl' => ! $twoCol,
                    'max-w-3xl' => ! $style,
                ])>{{ $heading }}@if($accent) <span class="bg-gradient-to-r from-brand-300 to-brand-500 bg-clip-text text-transparent">{{ $accent }}</span>@endif</h1>

                @if($description)
                    <p @class([
                        'rise-3 max-w-xl text-ink-200',
                        'text-base leading-7 sm:text-lg sm:leading-8' => $twoCol,
                        'mt-5' => $style === 'card',
                        'mt-6' => $style !== 'card',
                        'text-lg leading-8' => ! $twoCol,
                    ])>{{ $description }}</p>
                @endif

                @if($showSearch)
                    <form action="{{ route('search') }}" method="get" role="search" @class([
                        'rise-3 flex max-w-xl gap-2 rounded-2xl bg-white p-2 shadow-2xl',
                        'mt-8 shadow-black/40' => $twoCol,
                        'mt-9 shadow-black/30' => ! $twoCol,
                    ])>
                        <label for="q-hero" class="sr-only">Cari produk</label>
                        <input id="q-hero" name="q" type="search" placeholder="{{ $placeholder }}" class="min-w-0 flex-1 rounded-xl px-4 py-3 text-ink-900 outline-none placeholder:text-ink-400">
                        <button class="btn btn-primary" type="submit">Cari</button>
                    </form>
                @endif

                @if($trustBadges->isNotEmpty())
                    <ul class="rise-3 mt-6 flex flex-wrap items-center gap-4 text-xs font-medium text-ink-300">
                        @foreach($trustBadges as $text)
                            <li class="inline-flex items-center gap-1.5"><svg class="h-4 w-4 text-brand-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg> {{ $text }}</li>
                        @endforeach
                    </ul>
                @endif

                @if($style === 'background' && $banner['title'])
                    <div class="rise-3 mt-6 inline-flex flex-wrap items-center gap-3 rounded-2xl border border-white/15 bg-ink-950/60 p-2.5 sm:px-4 sm:py-2.5 backdrop-blur-md">
                        <span class="rounded bg-brand-600 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider text-white">Promo</span>
                        <span class="text-xs sm:text-sm font-semibold text-white">{{ $banner['title'] }}</span>
                        <a href="{{ $banner['cta_url'] }}" class="inline-flex items-center gap-1 text-xs font-bold text-amber-300 hover:text-amber-200 transition">
                            <span>{{ $banner['cta_text'] ?: 'Lihat Promo' }}</span>
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                        </a>
                    </div>
                @endif
            </div>

            @if($style === 'card')
                {{-- Kartu showcase kampanye --}}
                <div class="lg:col-span-5">
                    <div class="group relative rounded-3xl border border-white/15 bg-gradient-to-b from-white/10 to-white/5 p-3.5 backdrop-blur-xl shadow-2xl shadow-black/50 transition-all duration-300 hover:border-brand-400/40 hover:shadow-brand-500/20">
                        <a href="{{ $banner['cta_url'] }}" class="block relative aspect-[16/10] overflow-hidden rounded-2xl bg-ink-900 shadow-inner group-hover:opacity-95 transition">
                            <img src="{{ \App\Support\ImageDerivatives::resized($banner['image'], 1024) }}" @if($bannerSrcset) srcset="{{ $bannerSrcset }}" sizes="(min-width: 1024px) 480px, calc(100vw - 3rem)" @endif width="800" height="500" alt="{{ $banner['title'] ?: 'Promo Bundling Supermaterial' }}" class="h-full w-full object-cover transition duration-700 ease-out group-hover:scale-105" loading="eager" fetchpriority="high">
                            <div class="absolute inset-0 bg-gradient-to-t from-ink-950/80 via-transparent to-black/20"></div>
                            @if($banner['badge'])
                                <span class="absolute top-3 left-3 inline-flex items-center gap-1.5 rounded-full bg-brand-600/90 px-3 py-1 text-xs font-bold text-white shadow-lg backdrop-blur-sm">
                                    <span class="h-2 w-2 rounded-full bg-amber-300 animate-ping"></span>
                                    {{ $banner['badge'] }}
                                </span>
                            @endif
                        </a>
                        <div class="p-3.5 sm:p-4">
                            @if($banner['title'])
                                <h2 class="font-display text-lg font-bold leading-snug text-white transition group-hover:text-brand-300 sm:text-xl">
                                    <a href="{{ $banner['cta_url'] }}">{{ $banner['title'] }}</a>
                                </h2>
                            @endif
                            @if($banner['desc'])
                                <p class="mt-2 text-xs sm:text-sm leading-relaxed text-ink-300">{{ $banner['desc'] }}</p>
                            @endif
                            <div class="mt-4">
                                <a href="{{ $banner['cta_url'] }}" class="flex w-full items-center justify-between rounded-xl bg-gradient-to-r from-brand-600 to-brand-500 px-4 py-2.5 text-center text-sm font-bold text-white shadow-lg shadow-brand-600/30 transition-all hover:from-brand-500 hover:to-brand-400 hover:shadow-brand-500/50">
                                    <span>{{ $banner['cta_text'] }}</span>
                                    <svg class="h-4 w-4 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        @if($style === 'banner')
            {{-- Strip promo lebar --}}
            <div class="mt-12 rounded-3xl border border-white/15 bg-gradient-to-r from-white/10 via-white/5 to-white/10 p-3 sm:p-5 backdrop-blur-xl shadow-2xl">
                <div class="grid items-center gap-6 md:grid-cols-12">
                    <a href="{{ $banner['cta_url'] }}" class="block overflow-hidden rounded-2xl md:col-span-6 lg:col-span-5 aspect-[16/9] bg-ink-900 group">
                        <img src="{{ \App\Support\ImageDerivatives::resized($banner['image'], 1024) }}" @if($bannerSrcset) srcset="{{ $bannerSrcset }}" sizes="(min-width: 1024px) 420px, calc(100vw - 3rem)" @endif width="800" height="450" loading="lazy" alt="{{ $banner['title'] ?: 'Promo Bundling' }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                    </a>
                    <div class="md:col-span-6 lg:col-span-7">
                        @if($banner['badge'])
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-600/90 px-3 py-1 text-xs font-bold text-white shadow-sm">
                                <span class="h-2 w-2 rounded-full bg-amber-300 animate-ping"></span> {{ $banner['badge'] }}
                            </span>
                        @endif
                        @if($banner['title'])
                            <h2 class="mt-3 font-display text-2xl font-bold text-white sm:text-3xl">{{ $banner['title'] }}</h2>
                        @endif
                        @if($banner['desc'])
                            <p class="mt-2 text-sm text-ink-200 leading-relaxed">{{ $banner['desc'] }}</p>
                        @endif
                        <div class="mt-5">
                            <a href="{{ $banner['cta_url'] }}" class="btn btn-primary inline-flex items-center gap-2">
                                <span>{{ $banner['cta_text'] }}</span>
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</section>
