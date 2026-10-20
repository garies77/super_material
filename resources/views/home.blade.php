@php
    use App\Models\Setting;
    $org = [
        '@context' => 'https://schema.org',
        '@graph' => [
            array_filter([
                '@type' => 'Organization',
                'name' => Setting::get('org_name', config('app.name')),
                'url' => url('/'),
                'logo' => Setting::get('org_logo_url'),
            ]),
            [
                '@type' => 'WebSite',
                'name' => config('app.name'),
                'url' => url('/'),
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => route('search').'?q={search_term_string}',
                    'query-input' => 'required name=search_term_string',
                ],
            ],
        ],
    ];
    $heroBannerActive = Setting::getBool('hero_banner_active', true);
    $heroBannerImage = Setting::imageUrl('hero_banner_image');
    $heroBannerBadge = Setting::get('hero_banner_badge', 'Promo Bundling Spesial');
    $heroBannerTitle = Setting::get('hero_banner_title');
    $heroBannerDesc = Setting::get('hero_banner_desc');
    $heroBannerCtaText = Setting::get('hero_banner_cta_text', 'Lihat Promo Bundling');
    $heroBannerCtaUrl = Setting::get('hero_banner_cta_url', route('product.index'));
    $heroBannerStyle = Setting::get('hero_banner_style', 'card');
    $hasHeroBanner = $heroBannerActive && !empty($heroBannerImage);
@endphp
<x-layouts.app :canonical="url('/')">
    @push('jsonld')<x-jsonld :data="$org" />@endpush

    <section class="relative overflow-hidden bg-ink-950 text-white">
        <div class="hero-grid absolute inset-0"></div>
        <div class="absolute -right-32 -top-32 h-96 w-96 rounded-full bg-brand-500/25 blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-40 left-1/4 h-80 w-80 rounded-full bg-brand-600/20 blur-3xl pointer-events-none"></div>

        <div class="container-x relative py-16 sm:py-24">
            @if($hasHeroBanner && $heroBannerStyle === 'card')
                <div class="grid items-center gap-10 lg:grid-cols-12 lg:gap-12">
                    {{-- Left column: Text & Search --}}
                    <div class="lg:col-span-7">
                        <p class="rise inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-4 py-1.5 text-xs font-semibold text-brand-300">
                            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-brand-400"></span> Katalog bahan bangunan
                        </p>
                        <h1 class="rise-2 mt-5 font-display text-4xl font-extrabold leading-[1.1] text-white sm:text-5xl xl:text-6xl">
                            Temukan bahan bangunan <span class="bg-gradient-to-r from-brand-300 to-brand-500 bg-clip-text text-transparent">tepat untuk proyek Anda</span>
                        </h1>
                        <p class="rise-3 mt-5 max-w-xl text-base leading-7 text-ink-200 sm:text-lg sm:leading-8">
                            {{ Setting::get('site_description', 'Bandingkan produk, baca panduan memilih, lalu beli lewat Shopee, Tokopedia, Lazada, TikTok Shop, atau konsultasi langsung via WhatsApp.') }}
                        </p>
                        <form action="{{ route('search') }}" method="get" role="search" class="rise-3 mt-8 flex max-w-xl gap-2 rounded-2xl bg-white p-2 shadow-2xl shadow-black/40">
                            <label for="q-hero" class="sr-only">Cari produk</label>
                            <input id="q-hero" name="q" type="search" placeholder="Cari semen, keramik, baja ringan…" class="min-w-0 flex-1 rounded-xl px-4 py-3 text-ink-900 outline-none placeholder:text-ink-400">
                            <button class="btn btn-primary" type="submit">Cari</button>
                        </form>
                        <div class="rise-3 mt-6 flex flex-wrap items-center gap-4 text-xs font-medium text-ink-300">
                            <span class="inline-flex items-center gap-1.5"><svg class="h-4 w-4 text-brand-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg> 100% Produk Asli & SNI</span>
                            <span class="inline-flex items-center gap-1.5"><svg class="h-4 w-4 text-brand-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg> Transaksi Toko Resmi</span>
                            <span class="inline-flex items-center gap-1.5"><svg class="h-4 w-4 text-brand-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg> Konsultasi WhatsApp Cepat</span>
                        </div>
                    </div>

                    {{-- Right column: Campaign Showcase Card --}}
                    <div class="lg:col-span-5">
                        <div class="group relative rounded-3xl border border-white/15 bg-gradient-to-b from-white/10 to-white/5 p-3.5 backdrop-blur-xl shadow-2xl shadow-black/50 transition-all duration-300 hover:border-brand-400/40 hover:shadow-brand-500/20">
                            {{-- Banner visual --}}
                            <a href="{{ $heroBannerCtaUrl }}" class="block relative aspect-[16/10] overflow-hidden rounded-2xl bg-ink-900 shadow-inner group-hover:opacity-95 transition">
                                <img src="{{ $heroBannerImage }}" alt="{{ $heroBannerTitle ?: 'Promo Bundling Supermaterial' }}" class="h-full w-full object-cover transition duration-700 ease-out group-hover:scale-105" loading="eager" fetchpriority="high">
                                <div class="absolute inset-0 bg-gradient-to-t from-ink-950/80 via-transparent to-black/20"></div>

                                @if($heroBannerBadge)
                                    <span class="absolute top-3 left-3 inline-flex items-center gap-1.5 rounded-full bg-brand-600/90 px-3 py-1 text-xs font-bold text-white shadow-lg backdrop-blur-sm">
                                        <span class="h-2 w-2 rounded-full bg-amber-300 animate-ping"></span>
                                        {{ $heroBannerBadge }}
                                    </span>
                                @endif
                            </a>

                            {{-- Card Details --}}
                            <div class="p-3.5 sm:p-4">
                                @if($heroBannerTitle)
                                    <h2 class="font-display text-lg font-bold leading-snug text-white transition group-hover:text-brand-300 sm:text-xl">
                                        <a href="{{ $heroBannerCtaUrl }}">{{ $heroBannerTitle }}</a>
                                    </h2>
                                @endif
                                @if($heroBannerDesc)
                                    <p class="mt-2 text-xs sm:text-sm leading-relaxed text-ink-300">
                                        {{ $heroBannerDesc }}
                                    </p>
                                @endif
                                <div class="mt-4">
                                    <a href="{{ $heroBannerCtaUrl }}" class="flex w-full items-center justify-between rounded-xl bg-gradient-to-r from-brand-600 to-brand-500 px-4 py-2.5 text-center text-sm font-bold text-white shadow-lg shadow-brand-600/30 transition-all hover:from-brand-500 hover:to-brand-400 hover:shadow-brand-500/50">
                                        <span>{{ $heroBannerCtaText }}</span>
                                        <svg class="h-4 w-4 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @elseif($hasHeroBanner && $heroBannerStyle === 'banner')
                <div class="max-w-4xl">
                    <p class="rise inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-4 py-1.5 text-xs font-semibold text-brand-300">
                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-brand-400"></span> Katalog bahan bangunan
                    </p>
                    <h1 class="rise-2 mt-6 font-display text-4xl font-extrabold leading-[1.08] text-white sm:text-6xl">
                        Temukan bahan bangunan <span class="bg-gradient-to-r from-brand-300 to-brand-500 bg-clip-text text-transparent">tepat untuk proyek Anda</span>
                    </h1>
                    <p class="rise-3 mt-6 max-w-xl text-lg leading-8 text-ink-200">{{ Setting::get('site_description', 'Bandingkan produk, baca panduan memilih, lalu beli lewat Shopee, Tokopedia, Lazada, TikTok Shop, atau konsultasi langsung via WhatsApp.') }}</p>
                    <form action="{{ route('search') }}" method="get" role="search" class="rise-3 mt-9 flex max-w-xl gap-2 rounded-2xl bg-white p-2 shadow-2xl shadow-black/30">
                        <label for="q-hero" class="sr-only">Cari produk</label>
                        <input id="q-hero" name="q" type="search" placeholder="Cari semen, keramik, baja ringan…" class="min-w-0 flex-1 rounded-xl px-4 py-3 text-ink-900 outline-none placeholder:text-ink-400">
                        <button class="btn btn-primary" type="submit">Cari</button>
                    </form>
                </div>

                {{-- Wide banner promo strip --}}
                <div class="mt-12 rounded-3xl border border-white/15 bg-gradient-to-r from-white/10 via-white/5 to-white/10 p-3 sm:p-5 backdrop-blur-xl shadow-2xl">
                    <div class="grid items-center gap-6 md:grid-cols-12">
                        <a href="{{ $heroBannerCtaUrl }}" class="block overflow-hidden rounded-2xl md:col-span-6 lg:col-span-5 aspect-[16/9] bg-ink-900 group">
                            <img src="{{ $heroBannerImage }}" alt="{{ $heroBannerTitle ?: 'Promo Bundling' }}" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                        </a>
                        <div class="md:col-span-6 lg:col-span-7">
                            @if($heroBannerBadge)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-600/90 px-3 py-1 text-xs font-bold text-white shadow-sm">
                                    <span class="h-2 w-2 rounded-full bg-amber-300 animate-ping"></span> {{ $heroBannerBadge }}
                                </span>
                            @endif
                            @if($heroBannerTitle)
                                <h3 class="mt-3 font-display text-2xl font-bold text-white sm:text-3xl">{{ $heroBannerTitle }}</h3>
                            @endif
                            @if($heroBannerDesc)
                                <p class="mt-2 text-sm text-ink-200 leading-relaxed">{{ $heroBannerDesc }}</p>
                            @endif
                            <div class="mt-5">
                                <a href="{{ $heroBannerCtaUrl }}" class="btn btn-primary inline-flex items-center gap-2">
                                    <span>{{ $heroBannerCtaText }}</span>
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @elseif($hasHeroBanner && $heroBannerStyle === 'background')
                <div class="absolute inset-0 z-0">
                    <img src="{{ $heroBannerImage }}" alt="" class="h-full w-full object-cover opacity-25 filter blur-[1px]">
                    <div class="absolute inset-0 bg-gradient-to-r from-ink-950 via-ink-950/90 to-ink-950/70"></div>
                </div>
                <div class="relative z-10">
                    @if($heroBannerBadge)
                        <a href="{{ $heroBannerCtaUrl }}" class="rise mb-4 inline-flex items-center gap-2 rounded-full border border-brand-400/40 bg-brand-500/20 px-4 py-1.5 text-xs font-semibold text-brand-200 backdrop-blur-md transition hover:bg-brand-500/30">
                            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-brand-400"></span> {{ $heroBannerBadge }} →
                        </a>
                    @else
                        <p class="rise inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-4 py-1.5 text-xs font-semibold text-brand-300">
                            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-brand-400"></span> Katalog bahan bangunan
                        </p>
                    @endif
                    <h1 class="rise-2 mt-4 max-w-3xl font-display text-4xl font-extrabold leading-[1.08] text-white sm:text-6xl">
                        Temukan bahan bangunan <span class="bg-gradient-to-r from-brand-300 to-brand-500 bg-clip-text text-transparent">tepat untuk proyek Anda</span>
                    </h1>
                    <p class="rise-3 mt-6 max-w-xl text-lg leading-8 text-ink-200">{{ Setting::get('site_description', 'Bandingkan produk, baca panduan memilih, lalu beli lewat Shopee, Tokopedia, Lazada, TikTok Shop, atau konsultasi langsung via WhatsApp.') }}</p>
                    <form action="{{ route('search') }}" method="get" role="search" class="rise-3 mt-9 flex max-w-xl gap-2 rounded-2xl bg-white p-2 shadow-2xl shadow-black/30">
                        <label for="q-hero" class="sr-only">Cari produk</label>
                        <input id="q-hero" name="q" type="search" placeholder="Cari semen, keramik, baja ringan…" class="min-w-0 flex-1 rounded-xl px-4 py-3 text-ink-900 outline-none placeholder:text-ink-400">
                        <button class="btn btn-primary" type="submit">Cari</button>
                    </form>
                </div>
            @else
                <p class="rise inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-4 py-1.5 text-xs font-semibold text-brand-300">
                    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-brand-400"></span> Katalog bahan bangunan
                </p>
                <h1 class="rise-2 mt-6 max-w-3xl font-display text-4xl font-extrabold leading-[1.08] text-white sm:text-6xl">
                    Temukan bahan bangunan <span class="bg-gradient-to-r from-brand-300 to-brand-500 bg-clip-text text-transparent">tepat untuk proyek Anda</span>
                </h1>
                <p class="rise-3 mt-6 max-w-xl text-lg leading-8 text-ink-200">{{ Setting::get('site_description', 'Bandingkan produk, baca panduan memilih, lalu beli lewat Shopee, Tokopedia, Lazada, TikTok Shop, atau konsultasi langsung via WhatsApp.') }}</p>
                <form action="{{ route('search') }}" method="get" role="search" class="rise-3 mt-9 flex max-w-xl gap-2 rounded-2xl bg-white p-2 shadow-2xl shadow-black/30">
                    <label for="q-hero" class="sr-only">Cari produk</label>
                    <input id="q-hero" name="q" type="search" placeholder="Cari semen, keramik, baja ringan…" class="min-w-0 flex-1 rounded-xl px-4 py-3 text-ink-900 outline-none placeholder:text-ink-400">
                    <button class="btn btn-primary" type="submit">Cari</button>
                </form>
            @endif
        </div>
    </section>

    @if($categories->isNotEmpty())
    <section class="container-x -mt-10 relative">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            @foreach($categories as $c)
                <a href="{{ $c->url() }}" class="card card-hover group flex items-center gap-4 p-4">
                    <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-brand-50 font-display text-xl font-bold text-brand-600 transition group-hover:bg-brand-500 group-hover:text-white">{{ mb_strtoupper(mb_substr($c->name, 0, 1)) }}</span>
                    <span class="min-w-0">
                        <span class="block truncate font-display font-semibold text-ink-900">{{ $c->name }}</span>
                        <span class="text-xs text-ink-400">{{ $c->products_count }} produk</span>
                    </span>
                </a>
            @endforeach
        </div>
    </section>
    @endif

    <section class="container-x mt-20">
        <div class="mb-8 flex items-end justify-between">
            <h2 class="section-title">Produk pilihan</h2>
            <a href="{{ route('product.index') }}" class="text-sm font-semibold text-brand-700 hover:underline">Lihat semua produk →</a>
        </div>
        @if($featured->isEmpty())
            <p class="card p-10 text-center text-ink-400">Produk akan segera hadir.</p>
        @else
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                @foreach($featured as $p)<x-product-card :product="$p" />@endforeach
            </div>
        @endif
    </section>

    @if($articles->isNotEmpty())
    <section class="container-x mt-20">
        <div class="mb-8 flex items-end justify-between">
            <h2 class="section-title">Panduan &amp; artikel</h2>
            <a href="{{ route('article.index') }}" class="text-sm font-semibold text-brand-700 hover:underline">Semua artikel →</a>
        </div>
        <div class="grid gap-5 md:grid-cols-3">
            @foreach($articles as $a)<x-article-card :article="$a" />@endforeach
        </div>
    </section>
    @endif

    <section class="container-x mt-20">
        <div class="relative overflow-hidden rounded-3xl bg-ink-900 px-8 py-12 text-center text-white sm:px-16">
            <div class="absolute -right-20 -top-20 h-64 w-64 rounded-full bg-brand-500/30 blur-3xl"></div>
            <h2 class="relative font-display text-3xl font-bold text-white">Butuh material dalam jumlah besar?</h2>
            <p class="relative mx-auto mt-3 max-w-lg text-ink-200">Konsultasikan kebutuhan proyek Anda — kami bantu hitung dan rekomendasikan produk yang tepat.</p>
            @php $wa = preg_replace('/\D+/', '', (string) Setting::get('whatsapp_number')); @endphp
            @if($wa)
                <a href="https://wa.me/{{ str_starts_with($wa, '0') ? '62'.substr($wa, 1) : $wa }}?text={{ rawurlencode('Halo, saya ingin konsultasi kebutuhan material proyek.') }}" rel="nofollow noopener" target="_blank" class="btn btn-primary relative mt-7">Chat WhatsApp</a>
            @endif
        </div>
    </section>
</x-layouts.app>
