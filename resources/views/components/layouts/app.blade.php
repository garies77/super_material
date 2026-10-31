@props([
    'title' => null,
    'description' => null,
    'canonical' => null,
    'image' => null,
    'imageAlt' => null,
    'imageWidth' => null,
    'imageHeight' => null,
    'imageType' => null,
    'noindex' => false,
    'nofollow' => false,
    'type' => 'website',
    'articlePublishedTime' => null,
    'articleModifiedTime' => null,
    'articleAuthor' => null,
    'articleSection' => null,
    'articleTags' => [],
])
@php
    use App\Models\Category;
    use App\Models\Setting;

    $siteName = config('app.name');
    $fullTitle = $title ?: $siteName.' — '.Setting::get('site_tagline', 'Katalog Bahan Bangunan');
    $metaDesc = $description ?: Setting::get('site_description', 'Katalog bahan bangunan lengkap dengan harga acuan dan tautan beli ke marketplace dan WhatsApp.');
    $canon = $canonical ?: url()->current();
    $menuCategories = Category::active()->whereNull('parent_id')->orderBy('sort_order')->orderBy('name')->take(7)->get();
    $ga4 = Setting::get('ga4_id');
    $pixel = Setting::get('meta_pixel_id');

    // Direktif robots (PRD 9.11.4): bawaan max-snippet/max-image-preview/max-video-preview untuk halaman terindeks
    $robots = [$noindex ? 'noindex' : 'index', $nofollow ? 'nofollow' : 'follow'];
    if (! $noindex) {
        array_push($robots, 'max-snippet:-1', 'max-image-preview:large', 'max-video-preview:-1');
    }

    // Spesifikasi Open Graph tidak punya tipe global produk; selain artikel dipetakan ke website (PRD 9.10.3)
    $ogType = $type === 'article' ? 'article' : 'website';

    // Organization & WebSite di seluruh situs (PRD 9.15.2), WebSite tanpa SearchAction (9.12.2)
    $siteLd = [
        '@context' => 'https://schema.org',
        '@graph' => [
            array_filter([
                '@type' => 'Organization',
                '@id' => url('/').'#organization',
                'name' => Setting::get('org_name', Setting::brandName()),
                'url' => url('/'),
                'logo' => Setting::logoUrl(),
            ]),
            [
                '@type' => 'WebSite',
                '@id' => url('/').'#website',
                'name' => Setting::brandName(),
                'url' => url('/'),
                'inLanguage' => 'id-ID',
                'publisher' => ['@id' => url('/').'#organization'],
            ],
        ],
    ];

    // Resolusi gambar Open Graph & Google SERP Thumbnail
    $metaImage = $image ?: Setting::defaultOgImage();
    $metaImageAlt = $imageAlt ?: $fullTitle;

    $metaImageWidth = null;
    $metaImageHeight = null;
    $metaImageType = null;

    if (! empty($metaImage)) {
        $metaImageWidth = $imageWidth ?: match ($type) {
            'article' => 1200,
            'product' => 800,
            default => 1200,
        };
        $metaImageHeight = $imageHeight ?: match ($type) {
            'article' => 675,
            'product' => 800,
            default => 630,
        };

        $ext = strtolower(pathinfo(parse_url($metaImage, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        $metaImageType = $imageType ?: match ($ext) {
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'jpg', 'jpeg' => 'image/jpeg',
            default => null,
        };
    }
@endphp
<!DOCTYPE html>
<html lang="id" prefix="og: https://ogp.me/ns#">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $fullTitle }}</title>
    <meta name="description" content="{{ $metaDesc }}">
    <link rel="canonical" href="{{ $canon }}">
    <meta name="robots" content="{{ implode(', ', $robots) }}">

    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:type" content="{{ $ogType }}">
    <meta property="og:locale" content="id_ID">
    <meta property="og:title" content="{{ $fullTitle }}">
    <meta property="og:description" content="{{ $metaDesc }}">
    <meta property="og:url" content="{{ $canon }}">
    @if($type === 'article')
        @if(!empty($articlePublishedTime))<meta property="article:published_time" content="{{ $articlePublishedTime }}">@endif
        @if(!empty($articleModifiedTime))<meta property="article:modified_time" content="{{ $articleModifiedTime }}">@endif
        @if(!empty($articleAuthor))<meta property="article:author" content="{{ $articleAuthor }}">@endif
        @if(!empty($articleSection))<meta property="article:section" content="{{ $articleSection }}">@endif
        @foreach(collect($articleTags)->all() as $tag)
            @if(!empty($tag))<meta property="article:tag" content="{{ is_object($tag) ? ($tag->name ?? '') : $tag }}">@endif
        @endforeach
    @endif
    @if(!empty($metaImage))
        <meta property="og:image" content="{{ $metaImage }}">
        @if(str_starts_with($metaImage, 'https://'))
            <meta property="og:image:secure_url" content="{{ $metaImage }}">
        @endif
        @if(!empty($metaImageWidth))
            <meta property="og:image:width" content="{{ $metaImageWidth }}">
        @endif
        @if(!empty($metaImageHeight))
            <meta property="og:image:height" content="{{ $metaImageHeight }}">
        @endif
        <meta property="og:image:alt" content="{{ $metaImageAlt }}">
        @if(!empty($metaImageType))
            <meta property="og:image:type" content="{{ $metaImageType }}">
        @endif
    @endif

    <meta name="twitter:card" content="{{ !empty($metaImage) ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $fullTitle }}">
    <meta name="twitter:description" content="{{ $metaDesc }}">
    @if(!empty($metaImage))
        <meta name="twitter:image" content="{{ $metaImage }}">
        <meta name="twitter:image:alt" content="{{ $metaImageAlt }}">
    @endif

    {{-- Favicon mengikuti logo di Pengaturan situs (App\Support\Favicon) --}}
    <link rel="icon" href="{{ \App\Support\Favicon::url() }}" type="{{ \App\Support\Favicon::type() }}">
    <link rel="apple-touch-icon" href="{{ \App\Support\Favicon::url() }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    {{-- Font tidak memblokir render: dimuat sebagai media="print" lalu diaktifkan (display=swap); <noscript> untuk tanpa JS --}}
    <link id="gfonts" href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet" media="print">
    <script nonce="{{ Vite::cspNonce() }}">(function(l){if(l.sheet){l.media='all'}else{l.addEventListener('load',function(){l.media='all'})}})(document.getElementById('gfonts'))</script>
    <noscript><link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"></noscript>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>{!! Setting::themeCss() !!}</style>

    @if($ga4)
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $ga4 }}"></script>
        <script nonce="{{ Vite::cspNonce() }}">window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config','{{ $ga4 }}');</script>
    @endif
    @if($pixel)
        <script nonce="{{ Vite::cspNonce() }}">!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','{{ $pixel }}');fbq('track','PageView');</script>
    @endif
    <x-jsonld :data="$siteLd" />
    @stack('jsonld')
</head>
<body class="flex min-h-screen flex-col">
    <a href="#konten" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-white focus:px-4 focus:py-2">Lewati ke konten</a>

    <header class="sticky top-0 z-40 border-b border-ink-100 bg-white/85 backdrop-blur-lg">
        <div class="container-x flex h-16 items-center justify-between gap-2">
            <a href="{{ route('home') }}" class="flex shrink-0 items-center gap-2.5" aria-label="{{ $siteName }} — beranda">
                @if($siteLogo = Setting::logoUrl())
                    <img src="{{ $siteLogo }}" alt="{{ $siteName }}" @if($logoSize = \App\Support\ImageDerivatives::dimensions($siteLogo)) width="{{ (int) round(36 * $logoSize[0] / max(1, $logoSize[1])) }}" height="36" @endif fetchpriority="low" class="h-9 w-auto max-h-9 max-w-[180px] object-contain">
                @else
                    <span class="hidden h-9 w-9 place-items-center rounded-xl bg-ink-900 text-brand-400 min-[400px]:grid">
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M3 9l9-6 9 6v11a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z"/><path d="M9 21V12h6v9"/></svg>
                    </span>
                @endif
                <span class="font-display text-lg font-bold tracking-tight text-ink-900 sm:text-xl">{!! Setting::brandHtml('text-brand-600') !!}</span>
            </a>

            {{-- Navigasi utama dari Navbar Builder (Pengaturan Tampilan) --}}
            <nav class="flex items-center gap-0.5 sm:gap-1" aria-label="Navigasi utama">
                @include('partials.site-navbar', ['items' => \App\Support\SiteLayout::navbar()])
                <a href="{{ route('search') }}" data-search-open role="button" aria-label="Cari produk atau artikel" aria-haspopup="dialog" aria-controls="search-overlay" aria-expanded="false"
                   class="ml-1 grid h-11 w-11 place-items-center rounded-xl border border-ink-200 bg-white text-ink-800 transition hover:border-brand-400 hover:text-brand-700">
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                </a>
            </nav>
        </div>
    </header>

    {{-- Overlay pencarian (data-nosnippet mencegah teks input/contoh masuk snippet Google).
         Konteks mengikuti halaman: katalog/produk = produk saja, artikel = artikel saja, lainnya = keduanya. --}}
    @php
        $searchType = request()->routeIs('product.*', 'category.show') ? 'produk'
            : (request()->routeIs('article.*', 'author.show') ? 'artikel' : \App\Support\Search::type(request('type')));
        $searchPlaceholder = match ($searchType) {
            'produk' => 'Cari produk, merek, kategori, atau SKU…',
            'artikel' => 'Cari judul atau topik artikel…',
            default => 'Cari produk, merek, atau artikel…',
        };
    @endphp
    <div id="search-overlay" data-nosnippet data-search-overlay data-search-live="{{ route('search.live') }}" data-search-type="{{ $searchType }}"
         role="dialog" aria-modal="true" aria-label="Pencarian" class="fixed inset-0 z-50 hidden">
        <div data-search-close class="absolute inset-0 bg-ink-950/60 backdrop-blur-sm"></div>
        <div class="relative mx-auto mt-3 flex max-h-[calc(100dvh-1.5rem)] w-[calc(100%-1.5rem)] max-w-2xl flex-col rounded-2xl bg-white p-3 shadow-2xl sm:mt-24 sm:max-h-[calc(100dvh-8rem)] sm:p-4">
            <form action="{{ route('search') }}" method="get" role="search" class="flex shrink-0 items-center gap-2">
                @if($searchType)<input type="hidden" name="type" value="{{ $searchType }}">@endif
                <label for="search-input" class="sr-only">Kata kunci</label>
                <svg viewBox="0 0 24 24" class="ml-2 h-5 w-5 shrink-0 text-ink-400" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input id="search-input" name="q" type="search" value="{{ request('q') }}" autocomplete="off" placeholder="{{ $searchPlaceholder }}"
                       role="combobox" aria-autocomplete="list" aria-controls="search-results" aria-expanded="false" maxlength="100"
                       class="min-w-0 flex-1 bg-transparent px-2 py-3 text-base outline-none placeholder:text-ink-400">
                <span data-search-spinner class="hidden h-5 w-5 shrink-0 animate-spin rounded-full border-2 border-ink-200 border-t-brand-500" aria-hidden="true"></span>
                <button type="submit" class="btn btn-primary !px-4 !py-2.5">Cari</button>
                <button type="button" data-search-close class="grid h-10 w-10 shrink-0 place-items-center rounded-lg text-ink-400 hover:bg-ink-50 hover:text-ink-900" aria-label="Tutup pencarian">
                    <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </form>
            <div id="search-results" data-search-results role="listbox" aria-label="Hasil pencarian" class="mt-2 min-h-0 flex-1 overflow-y-auto overscroll-contain"></div>
            <p data-search-status aria-live="polite" class="mt-2 shrink-0 px-2 text-xs text-ink-400">Contoh: semen 50 kg, keramik 40x40, besi ulir. Tekan <kbd class="rounded border border-ink-200 px-1">Esc</kbd> untuk menutup.</p>
        </div>
    </div>

    <main id="konten" class="flex-1">
        {{-- Kampanye banner di atas konten untuk halaman selain artikel (artikel memakai posisi slotnya sendiri) --}}
        @if($pageBanner = $smBannerPreview ?? \App\Models\Ad::bannerForRequest(request()))
            <div class="container-x pt-6">
                @include('partials.promo-slot', ['ad' => $pageBanner, 'slot' => 'page_top', 'article' => null, 'preview' => isset($smBannerPreview), 'class' => 'my-0'])
            </div>
        @endif
        {{ $slot }}
    </main>

    @include('partials.site-footer', ['footer' => \App\Support\SiteLayout::footer()])

    @include('partials.sm-campaign')
</body>
</html>
