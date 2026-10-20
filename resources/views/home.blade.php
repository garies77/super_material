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
@endphp
<x-layouts.app :canonical="url('/')">
    @push('jsonld')<x-jsonld :data="$org" />@endpush

    <section class="relative overflow-hidden bg-ink-950 text-white">
        <div class="hero-grid absolute inset-0"></div>
        <div class="absolute -right-32 -top-32 h-96 w-96 rounded-full bg-brand-500/25 blur-3xl"></div>
        <div class="absolute -bottom-40 left-1/4 h-80 w-80 rounded-full bg-brand-600/20 blur-3xl"></div>
        <div class="container-x relative py-20 sm:py-28">
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
