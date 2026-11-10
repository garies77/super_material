@php
    $crumbs = [['Produk', route('product.index')]];
    if ($product->category) {
        foreach ($product->category->breadcrumbs() as $c) {
            $crumbs[] = [$c->name, $c->url()];
        }
    }
    $crumbs[] = [$product->name, null];

    $mediaList = $product->allMedia();
    $activeVariants = $product->variants->filter(fn ($v) => $v->is_active ?? true)->values();
    
    // Check if variant preselected via URL query ?varian=slug
    $initialVariantSlug = request()->query('varian');
    $initialVariant = $activeVariants->firstWhere('slug', $initialVariantSlug) ?: $activeVariants->first();

    // Group variants by option_group
    $variantGroups = $activeVariants->groupBy(fn ($v) => $v->option_group ?: 'Pilihan');

    $marketplaceChannels = $channels->reject->isWhatsapp()->values();
    $whatsappChannel = $channels->first->isWhatsapp();

    // Prepare JSON for interactive gallery and variants
    // Galeri memakai turunan WebP sesuai ukuran tampil (berkas asli bisa ratusan KB); gambar eksternal/SVG tetap apa adanya
    $resized = fn (?string $url, int $width) => \App\Support\ImageDerivatives::resized($url, $width);
    $gallerySrcset = fn (?string $url) => \App\Support\ImageDerivatives::srcset($url, [480, 800, 1200]);
    $gallerySizes = '(min-width: 1024px) 560px, calc(100vw - 4rem)';

    $mediaData = $mediaList->map(function ($m, $i) use ($resized, $gallerySrcset) {
        $still = $m->isVideo() ? ($m->posterUrl() ?: $m->url()) : $m->url();

        return [
            'index' => $i,
            'type' => $m->type,
            'rawUrl' => $m->url(),
            'rawPosterUrl' => $m->posterUrl() ?: $m->url(),
            'url' => $resized($still, 800),
            'srcset' => $gallerySrcset($still),
            'fullUrl' => $resized($still, 1600),
            'embedUrl' => $m->embedUrl(),
            'alt' => $m->alt_text ?: '',
            'isVideo' => $m->isVideo(),
        ];
    })->values();

    $priceHidden = $product->isPriceHidden();
    $rupiah = fn (?int $amount) => $amount ? 'Rp'.number_format($amount, 0, ',', '.') : null;
    $priceSuffix = $product->unit ? ' / '.$product->unit : '';

    // Harga awal dirender server (tanpa menunggu JavaScript); harga tersembunyi tidak ikut dikirim ke browser
    $initialPrice = ($initialVariant && ! $priceHidden) ? $rupiah($initialVariant->effectivePrice()) : null;
    $initialCompare = $initialPrice ? $rupiah($initialVariant->effectiveCompareAtPrice()) : null;
    $initialDiscount = $priceHidden ? null : ($initialVariant?->discountPercentage() ?: $product->maxDiscountPercentage());
    $initialPriceText = $initialPrice ? $initialPrice.$priceSuffix : ($product->priceLabel() ?? 'Cek harga di toko');

    $variantsData = $activeVariants->map(function ($v) use ($priceHidden, $rupiah, $resized, $gallerySrcset) {
        return [
            'id' => $v->id,
            'name' => $v->name,
            'slug' => $v->slug,
            'sku' => $v->sku,
            'group' => $v->option_group ?: 'Pilihan',
            'price' => $priceHidden ? null : $v->effectivePrice(),
            'compareAtPrice' => $priceHidden ? null : $v->effectiveCompareAtPrice(),
            'discount' => $priceHidden ? null : $v->discountPercentage(),
            'formattedPrice' => $priceHidden ? null : $rupiah($v->effectivePrice()),
            'formattedCompare' => $priceHidden ? null : $rupiah($v->effectiveCompareAtPrice()),
            'image' => $v->imageUrl(),
            'displayImage' => $resized($v->imageUrl(), 800),
            'displaySrcset' => $gallerySrcset($v->imageUrl()),
        ];
    })->values();

    // SEO Structured Data (JSON-LD) - PRD 9.10.2, 9.14.2, 9.14.3 & SEO-04
    $mainImageUrl = $product->primaryImageUrl();
    $imageMultiRatio = \App\Support\ImageDerivatives::multiRatioUrls($mainImageUrl);
    $allImages = ! empty($imageMultiRatio) ? $imageMultiRatio : $product->allImageUrls();
    if (empty($allImages) && $mainImageUrl) {
        $allImages = [$mainImageUrl];
    }

    $brandLd = $product->brand ? ['@type' => 'Brand', 'name' => $product->brand->name] : null;
    $hasVariants = $activeVariants->isNotEmpty();

    if ($hasVariants) {
        // Pola Single-Page ProductGroup (PRD 9.10.2 & 9.14.2 & SEO-04)
        $variesByKeys = $variantGroups->keys()->map(function ($g) {
            $lower = strtolower($g);
            return match (true) {
                str_contains($lower, 'ukuran') || str_contains($lower, 'dimensi') => 'https://schema.org/size',
                str_contains($lower, 'warna') => 'https://schema.org/color',
                default => 'https://schema.org/model',
            };
        })->unique()->values()->all();

        $variantsLd = $activeVariants->map(function ($v) use ($product, $mainImageUrl) {
            $vPrice = $v->effectivePrice();
            $vCompare = $v->effectiveCompareAtPrice();
            $vImage = $v->imageUrl() ?: $mainImageUrl;

            $vOffer = null;
            if ($vPrice && ! $product->isPriceHidden()) {
                $priceSpecs = null;
                if ($vCompare && $vCompare > $vPrice) {
                    $priceSpecs = [
                        [
                            '@type' => 'UnitPriceSpecification',
                            'priceType' => 'https://schema.org/SalePrice',
                            'priceCurrency' => 'IDR',
                            'price' => $vPrice,
                        ],
                        [
                            '@type' => 'UnitPriceSpecification',
                            'priceType' => 'https://schema.org/StrikethroughPrice',
                            'priceCurrency' => 'IDR',
                            'price' => $vCompare,
                        ],
                    ];
                }

                $vOffer = array_filter([
                    '@type' => 'Offer',
                    'url' => $product->url() . '?varian=' . $v->slug,
                    'priceCurrency' => 'IDR',
                    'price' => $vPrice,
                    'priceSpecification' => $priceSpecs,
                    'itemCondition' => 'https://schema.org/NewCondition',
                    'availability' => $product->schemaAvailability(),
                    'priceValidUntil' => $v->schemaPriceValidUntil(),
                ]);
            }

            return array_filter([
                '@type' => 'Product',
                'name' => $product->name . ' - ' . $v->name,
                'sku' => $v->sku ?: ($product->slug . '-' . $v->slug),
                'image' => $vImage ? [$vImage] : null,
                'offers' => $vOffer,
            ]);
        })->all();

        $ld = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'ProductGroup',
            'productGroupID' => $product->sku ?: $product->slug,
            'name' => $product->name,
            'description' => $product->seoDescription(),
            'url' => $product->url(),
            'image' => !empty($allImages) ? $allImages : null,
            'brand' => $brandLd,
            'category' => $product->category?->name,
            'variesBy' => ! empty($variesByKeys) ? $variesByKeys : ['https://schema.org/model'],
            'hasVariant' => $variantsLd,
            'aggregateRating' => $product->aggregateRatingSchema(),
        ]);
    } else {
        // Single Product (Tanpa Varian)
        $singlePrice = $product->effectivePriceMin();
        $singleOffer = null;
        if ($singlePrice && ! $product->isPriceHidden()) {
            $singleOffer = array_filter([
                '@type' => 'Offer',
                'url' => $product->url(),
                'priceCurrency' => 'IDR',
                'price' => $singlePrice,
                'itemCondition' => 'https://schema.org/NewCondition',
                'availability' => $product->schemaAvailability(),
                'priceValidUntil' => $product->schemaPriceValidUntil(),
            ]);
        }

        $ld = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'sku' => $product->sku ?: $product->slug,
            'mpn' => $product->mpn ?: null,
            'name' => $product->name,
            'description' => $product->seoDescription(),
            'image' => !empty($allImages) ? $allImages : null,
            'brand' => $brandLd,
            'category' => $product->category?->name,
            'url' => $product->url(),
            'offers' => $singleOffer,
            'aggregateRating' => $product->aggregateRatingSchema(),
        ]);
    }

    // Semua video produk dengan judul, tanggal unggah, durasi, dan chapter masing-masing
    $videosLd = \App\Support\Seo\VideoSchema::many(
        $mediaList->filter(fn ($m) => $m->isVideo())
            ->map(fn ($m) => $m->toVideoData($product->url(), 'Video '.$product->name)),
        $product->seoDescription(),
    );
    if ($videosLd) {
        $ld['video'] = $videosLd;
    }

    $faqLd = $product->faqs->isNotEmpty() ? [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => $product->faqs->map(fn ($f) => [
            '@type' => 'Question', 'name' => $f->question,
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f->answer],
        ])->all(),
    ] : null;
@endphp

<x-layouts.app :title="$product->seoTitle()" :description="$product->seoDescription()"
               :canonical="$product->canonical_url ?: $product->url()"
               :image="$mainImageUrl"
               :image-alt="$product->name"
               :image-width="800"
               :image-height="800"
               type="product"
               :noindex="$product->noindex"
               :nofollow="$product->nofollow">
    @push('jsonld')
        <x-jsonld :data="$ld" />
        @if($faqLd)<x-jsonld :data="$faqLd" />@endif
    @endpush

    {{-- Script data untuk State Galeri & Interaksi Varian --}}
    <script nonce="{{ Vite::cspNonce() }}">
        window.__PRODUCT_DATA__ = {
            id: {{ $product->id }},
            name: @json($product->name),
            slug: @json($product->slug),
            unit: @json($product->unit ?: 'item'),
            priceSuffix: @json($priceSuffix),
            gallerySizes: @json($gallerySizes),
            preselected: @json($initialVariantSlug && $initialVariant && $initialVariant->slug === $initialVariantSlug),
            url: @json($product->url()),
            isPriceHidden: @json($product->isPriceHidden()),
            defaultPrice: @json($product->priceLabel()),
            defaultMinPrice: @json($product->effectivePriceMin()),
            defaultMaxPrice: @json($product->effectivePriceMax()),
            media: @json($mediaData),
            variants: @json($variantsData),
            initialVariantId: @json($initialVariant?->id),
            hasVariants: @json($activeVariants->isNotEmpty()),
            waNumber: @json($product->whatsappNumber()),
            {{-- Lewat /go/ agar klik WhatsApp tercatat di statistik seperti kanal marketplace --}}
            waGoUrl: @json($whatsappChannel ? route('go', ['product' => $product->slug, 'channel' => $whatsappChannel->slug]) : null),
            marketplaceChannels: @json($marketplaceChannels->map(fn($c) => ['name' => $c->name, 'slug' => $c->slug, 'color' => $c->color])),
        };
    </script>

    <div class="container-x pt-6 pb-28 lg:pb-16" id="product-detail-page">
        {{-- Breadcrumb navigasi --}}
        <x-breadcrumb :items="$crumbs" />

        {{-- Main Product Card: Galeri (Kiri) & Info / Checkout (Kanan) --}}
        <div class="mt-4 rounded-2xl border border-ink-100 bg-white p-4 shadow-sm md:p-6 lg:p-8">
            <div class="grid gap-8 lg:grid-cols-[1fr_1.15fr] lg:gap-12">
                
                {{-- ==================== KOLOM KIRI: GALERI MEDIA (RASIO 1:1) ==================== --}}
                <div class="min-w-0 space-y-4" id="media-gallery">
                    {{-- Viewer Media Utama (Rasio 1:1) --}}
                    <div class="relative aspect-square w-full overflow-hidden rounded-2xl border border-ink-100 bg-[#f8fafc]">
                        {{-- Tag Badge Diskon di Galeri --}}
                        <div id="gallery-discount-badge" class="{{ $initialDiscount ? '' : 'hidden' }} absolute left-3 top-3 z-10 rounded-md bg-rose-500 px-2.5 py-1 text-xs font-extrabold uppercase tracking-wide text-white shadow-md">
                            <span id="gallery-discount-text">-{{ $initialDiscount }}%</span>
                        </div>

                        {{-- Tombol Zoom Lightbox --}}
                        <button type="button" id="btn-open-lightbox" aria-label="Perbesar media"
                                class="absolute right-3 top-3 z-10 flex h-9 w-9 items-center justify-center rounded-full bg-white/90 text-ink-700 shadow-md backdrop-blur transition hover:scale-105 hover:bg-white hover:text-ink-900">
                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>
                        </button>

                        {{-- Mobile Index Indicator Badge (1 / N) --}}
                        @if($mediaList->count() > 1)
                            <div class="absolute bottom-3 right-3 z-10 rounded-full bg-ink-900/75 px-2.5 py-0.5 text-xs font-semibold text-white backdrop-blur md:hidden">
                                <span id="mobile-indicator">1 / {{ $mediaList->count() }}</span>
                            </div>
                        @endif

                        {{-- Konten Utama (Gambar atau Video) --}}
                        <div id="main-media-container" class="relative h-full w-full">
                            @if($mediaList->isNotEmpty())
                                @php $firstMedia = $mediaList->first(); @endphp
                                @if($firstMedia->isVideo())
                                    <div class="relative h-full w-full cursor-pointer group" id="video-preview-trigger" data-embed="{{ $firstMedia->embedUrl() }}">
                                        <img id="main-image-el" src="{{ $mediaData[0]['url'] }}" @if($mediaData[0]['srcset']) srcset="{{ $mediaData[0]['srcset'] }}" sizes="{{ $gallerySizes }}" @endif alt="{{ $firstMedia->alt_text ?: $product->name }}" width="800" height="800" fetchpriority="high" class="h-full w-full object-contain p-2 md:p-4">
                                        <div class="absolute inset-0 flex items-center justify-center bg-black/20 transition group-hover:bg-black/30">
                                            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-rose-600 text-white shadow-xl transition group-hover:scale-110">
                                                <svg viewBox="0 0 24 24" class="ml-1 h-8 w-8 fill-current"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="video-player-container" class="hidden h-full w-full"></div>
                                @else
                                    <img id="main-image-el" src="{{ $mediaData[0]['url'] }}" @if($mediaData[0]['srcset']) srcset="{{ $mediaData[0]['srcset'] }}" sizes="{{ $gallerySizes }}" @endif alt="{{ $firstMedia->alt_text ?: $product->name }}" width="800" height="800" fetchpriority="high" class="h-full w-full object-contain p-2 md:p-4 transition duration-300">
                                @endif
                            @else
                                <div class="placeholder-art grid h-full w-full place-items-center">
                                    <span class="font-display text-8xl font-bold text-brand-500/50">{{ mb_strtoupper(mb_substr($product->name, 0, 1)) }}</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Strip Thumbnail dengan Panah Navigasi --}}
                    @if($mediaList->count() > 1)
                        <div class="relative">
                            {{-- Panah Scroll Kiri --}}
                            <button type="button" id="thumb-prev" aria-label="Geser gambar sebelumnya"
                                    class="absolute -left-3 top-1/2 z-10 hidden -translate-y-1/2 h-8 w-8 md:flex items-center justify-center rounded-full border border-ink-100 bg-white text-ink-700 shadow-md transition hover:bg-ink-50">
                                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                            </button>

                            {{-- Wadah Thumbnail (Scrollable) --}}
                            <div id="thumbnail-strip" class="no-scrollbar flex gap-2.5 overflow-x-auto py-1 px-1 scroll-smooth">
                                @foreach($mediaList as $index => $item)
                                    <button type="button" data-media-index="{{ $index }}"
                                            class="thumb-btn group relative h-16 w-16 md:h-20 md:w-20 shrink-0 overflow-hidden rounded-xl border-2 transition-all {{ $loop->first ? 'border-brand-600 ring-2 ring-brand-500/20' : 'border-ink-100 hover:border-brand-300' }}"
                                            aria-label="Pilih media {{ $index + 1 }}">
                                        <img src="{{ $resized($item->isVideo() ? ($item->posterUrl() ?: $item->url()) : $item->url(), 160) }}" alt="{{ $item->alt_text ?: 'Thumbnail ' . ($index + 1) }}"
                                             width="80" height="80" loading="lazy" class="h-full w-full object-cover">
                                        @if($item->isVideo())
                                            <div class="absolute inset-0 flex items-center justify-center bg-black/30">
                                                <div class="flex h-6 w-6 items-center justify-center rounded-full bg-rose-600 text-white shadow">
                                                    <svg viewBox="0 0 24 24" class="ml-0.5 h-3 w-3 fill-current"><polygon points="6 4 18 12 6 20 6 4"/></svg>
                                                </div>
                                            </div>
                                        @endif
                                    </button>
                                @endforeach
                            </div>

                            {{-- Panah Scroll Kanan --}}
                            <button type="button" id="thumb-next" aria-label="Geser gambar selanjutnya"
                                    class="absolute -right-3 top-1/2 z-10 hidden -translate-y-1/2 h-8 w-8 md:flex items-center justify-center rounded-full border border-ink-100 bg-white text-ink-700 shadow-md transition hover:bg-ink-50">
                                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                            </button>
                        </div>
                    @endif
                </div>

                {{-- ==================== KOLOM KANAN: DETAIL & PEMBELIAN (SHOPEE STYLE) ==================== --}}
                <div class="flex min-w-0 flex-col">
                    
                    {{-- Judul H1 & Badge --}}
                    <div class="flex flex-wrap items-center gap-2">
                        @if($product->badge)
                            <span class="rounded bg-brand-600 px-2 py-0.5 text-xs font-bold uppercase tracking-wider text-white shadow-sm">{{ $product->badge }}</span>
                        @endif
                        @if($product->brand)
                            <a href="{{ route('product.index', ['brand' => [$product->brand->id]]) }}" class="chip hover:bg-brand-100 hover:text-brand-700">
                                {{ $product->brand->name }}
                            </a>
                        @endif
                    </div>

                    <h1 class="mt-2.5 font-display text-2xl font-extrabold leading-tight text-ink-950 sm:text-3xl">
                        {{ $product->name }}
                    </h1>

                    {{-- Rating Stars & Jumlah Penilaian (Shopee style header) --}}
                    <div class="mt-3 flex flex-wrap items-center gap-3 text-sm">
                        @if($product->hasRating())
                            <div class="flex items-center gap-1.5 border-b border-brand-500 pb-0.5">
                                <span class="font-bold text-brand-700" data-rating-value>{{ $product->formattedRatingValue() }}</span>
                                <div class="flex text-amber-400">
                                    @for($i = 1; $i <= 5; $i++)
                                        <svg viewBox="0 0 20 20" class="h-4 w-4 {{ $i <= round($product->rating_value) ? 'fill-amber-400' : 'fill-ink-200' }}"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                    @endfor
                                </div>
                            </div>
                            <span class="text-ink-300">|</span>
                            <div class="text-ink-600">
                                <span class="font-semibold text-ink-900" data-rating-count>{{ $product->formattedRatingCount() }}</span> Penilaian
                            </div>
                            <span class="text-ink-300">|</span>
                        @endif
                        <div class="text-ink-500 text-xs">
                            Kategori: <a href="{{ $product->category?->url() ?? route('product.index') }}" class="font-medium text-brand-600 hover:underline">{{ $product->category?->name ?? 'Semua produk' }}</a>
                        </div>
                    </div>

                    {{-- BLOK HARGA BERGAYA SHOPEE --}}
                    <div class="mt-4 rounded-2xl bg-[#fafafa] p-4 border border-ink-100 sm:p-5">
                        <div class="flex flex-wrap items-baseline gap-3">
                            {{-- Harga Coret (Sebelum Diskon) --}}
                            <span id="price-compare" class="{{ $initialCompare ? '' : 'hidden' }} text-base text-ink-400 line-through md:text-lg">{{ $initialCompare }}</span>

                            {{-- Harga Jual Utama --}}
                            <span id="price-main" class="font-display text-2xl font-extrabold text-brand-600 sm:text-3xl md:text-4xl">{{ $initialPriceText }}</span>

                            {{-- Badge Diskon Otomatis --}}
                            <span id="price-discount-badge" class="{{ $initialDiscount ? '' : 'hidden' }} rounded bg-rose-500 px-2 py-0.5 text-xs font-bold text-white shadow-sm">
                                <span id="price-discount-pct">-{{ $initialDiscount }}%</span>
                            </span>
                        </div>
                        <p data-nosnippet class="mt-2 text-xs text-ink-500">
                            Harga acuan katalog Supermaterial. Harga final dan promo dapat disesuaikan pada kanal marketplace / WhatsApp rekanan.
                        </p>
                    </div>

                    {{-- Baris Info Pengiriman & Jaminan --}}
                    <div class="mt-4 divide-y divide-ink-100 rounded-xl border border-ink-100 bg-white p-3.5 text-xs text-ink-600 space-y-2">
                        <div class="flex items-start gap-2.5 pt-1">
                            <svg viewBox="0 0 24 24" class="h-4 w-4 shrink-0 text-brand-600 mt-0.5" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                            <div>
                                <span class="font-semibold text-ink-900">Pengiriman:</span>
                                <span>{{ $product->shipping_note ?: 'Pengiriman armada toko dari Medan · Estimasi 1-2 hari kerja' }}</span>
                            </div>
                        </div>
                        <div class="flex items-start gap-2.5 pt-2">
                            <svg viewBox="0 0 24 24" class="h-4 w-4 shrink-0 text-brand-600 mt-0.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            <div>
                                <span class="font-semibold text-ink-900">Jaminan:</span>
                                <span>{{ $product->guarantee_note ?: '100% Produk Asli Pabrik · Jaminan Kualitas Pabrikan Resmi' }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- PILIHAN VARIAN BERTINGKAT (Framed buttons Shopee style) --}}
                    @if($activeVariants->isNotEmpty())
                        <div class="mt-6 rounded-xl border border-ink-100 bg-white p-4 transition-all duration-300" id="variant-section-box">
                            @foreach($variantGroups as $groupName => $groupVariants)
                                <div class="mb-3.5 last:mb-0">
                                    <div class="flex items-center justify-between gap-3">
                                        <h2 class="max-w-[55%] shrink-0 font-display text-xs font-bold uppercase tracking-wider text-ink-500">
                                            {{ $groupName }}
                                        </h2>
                                        <span class="min-w-0 truncate text-right text-xs font-semibold text-brand-700" id="selected-variant-label">
                                            {{ $initialVariant ? $initialVariant->name : 'Pilih varian' }}
                                        </span>
                                    </div>
                                    <div class="mt-2.5 flex flex-wrap gap-2.5" role="radiogroup" aria-label="{{ $groupName }}">
                                        @foreach($groupVariants as $v)
                                            @php
                                                $isSelected = $initialVariant && $initialVariant->id === $v->id;
                                            @endphp
                                            <button type="button"
                                                    data-variant-id="{{ $v->id }}"
                                                    data-variant-slug="{{ $v->slug }}"
                                                    class="variant-btn relative flex min-h-11 items-center gap-2 rounded-xl border px-3.5 py-2 text-left text-sm transition-all {{ $isSelected ? 'border-brand-600 ring-2 ring-brand-500/20 bg-brand-50/40 text-brand-700 font-semibold shadow-sm' : 'border-ink-200 bg-white text-ink-800 hover:border-brand-400' }}"
                                                    role="radio"
                                                    aria-checked="{{ $isSelected ? 'true' : 'false' }}">
                                                @if($v->imageUrl())
                                                    <img src="{{ $resized($v->imageUrl(), 64) }}" alt="" width="32" height="32" loading="lazy" class="h-7 w-7 rounded object-cover">
                                                @endif
                                                <span>{{ $v->name }}</span>
                                                @if(! $priceHidden && $v->discountPercentage())
                                                    <span class="rounded bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold text-rose-700">-{{ $v->discountPercentage() }}%</span>
                                                @endif
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach

                            {{-- Pesan Validasi Jika Belum Memilih Varian --}}
                            <div id="variant-validation-alert" class="hidden mt-3 rounded-lg border border-rose-200 bg-rose-50 p-2.5 text-xs text-rose-700 flex items-center gap-2">
                                <svg viewBox="0 0 20 20" class="h-4 w-4 shrink-0 fill-rose-600"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                <span>Silakan pilih salah satu varian di atas terlebih dahulu.</span>
                            </div>
                        </div>
                    @endif

                    {{-- STEPPER KUANTITAS (Shopee Style) --}}
                    @if($product->show_quantity)
                        <div class="mt-5 flex items-center gap-4">
                            <span class="text-xs font-bold uppercase tracking-wider text-ink-500">Jumlah</span>
                            <div class="flex items-center rounded-xl border border-ink-200 bg-white shadow-sm">
                                <button type="button" id="qty-minus" aria-label="Kurangi jumlah"
                                        class="flex h-11 w-11 items-center justify-center text-ink-500 transition hover:bg-ink-50 hover:text-ink-900 disabled:opacity-40" disabled>
                                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                </button>
                                <input type="number" id="qty-input" value="1" min="1" max="9999" inputmode="numeric" aria-label="Jumlah"
                                       class="h-11 w-16 border-x border-ink-200 text-center font-display text-base font-semibold text-ink-900 focus:outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                                <button type="button" id="qty-plus" aria-label="Tambah jumlah"
                                        class="flex h-11 w-11 items-center justify-center text-ink-500 transition hover:bg-ink-50 hover:text-ink-900">
                                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                </button>
                            </div>
                            <span class="text-xs text-ink-500 font-medium">{{ $product->unit ?: 'item' }}</span>
                        </div>
                    @endif

                    {{-- TOMBOL AKSI PEMBELIAN (Chat WhatsApp & Beli Sekarang) --}}
                    <div class="mt-6 pt-2">
                        @if($channels->isEmpty())
                            <p class="rounded-xl bg-ink-100 p-4 text-sm text-ink-600">Tautan pembelian sedang diperbarui.</p>
                        @else
                            <div class="flex flex-col sm:flex-row gap-3">
                                {{-- Tombol WhatsApp (Outline Hijau Segar dengan Logo Asli WhatsApp) --}}
                                @if($whatsappChannel)
                                    <button type="button" id="btn-action-whatsapp"
                                            class="btn flex-1 justify-center gap-2.5 border-2 border-emerald-600 bg-emerald-50/60 py-3.5 font-bold text-emerald-800 transition-all hover:bg-emerald-600 hover:text-white shadow-sm hover:shadow-md">
                                        <x-channel-logo channel="whatsapp" class="h-6 w-6 shrink-0" />
                                        <span>Chat WhatsApp</span>
                                    </button>
                                @endif

                                {{-- Tombol Beli Sekarang (Solid Oranye Shopee / Brand dengan Logo Marketplace Asli) --}}
                                @if($marketplaceChannels->isNotEmpty())
                                    <button type="button" id="btn-action-buy-now"
                                            class="btn flex-1 justify-center gap-2.5 bg-brand-400 py-3.5 font-bold text-(--color-on-accent) shadow-lg transition-all hover:bg-brand-300 hover:shadow-xl hover:-translate-y-0.5">
                                        <div class="flex items-center -space-x-1.5 overflow-hidden">
                                            @foreach($marketplaceChannels->take(3) as $c)
                                                <span class="inline-flex h-5 w-5 rounded-md bg-white ring-2 ring-white overflow-hidden">
                                                    <x-channel-logo :channel="$c->slug" class="h-full w-full" />
                                                </span>
                                            @endforeach
                                        </div>
                                        <span>Beli Sekarang</span>
                                    </button>
                                @elseif($whatsappChannel)
                                    {{-- Jika tidak ada marketplace, tombol beli langsung mengarah ke WhatsApp konsultasi proyek --}}
                                    <button type="button" id="btn-action-buy-consult"
                                            class="btn flex-1 justify-center gap-2.5 bg-brand-600 py-3.5 font-bold text-white shadow-lg transition hover:bg-brand-700">
                                        <x-channel-logo channel="whatsapp" class="h-6 w-6 shrink-0" />
                                        <span>Pesan via WhatsApp</span>
                                    </button>
                                @endif
                            </div>
                        @endif
                    </div>

                </div>
            </div>
        </div>

        {{-- ==================== BAGIAN BAWAH: SPESIFIKASI & DESKRIPSI ==================== --}}
        <div class="mt-8 grid gap-8 lg:grid-cols-[1.25fr_1fr]">
            
            {{-- Kiri Bawah: Spesifikasi Teknis (Tabel 2 Kolom Shopee Style) --}}
            <div class="space-y-8">
                <section class="rounded-2xl border border-ink-100 bg-white p-4 shadow-sm sm:p-8">
                    <h2 class="font-display text-xl font-bold text-ink-950 sm:text-2xl">Spesifikasi Produk</h2>
                    
                    <div class="mt-4 divide-y divide-ink-100 text-sm">
                        {{-- Baris Kategori Hierarki --}}
                        <div class="grid grid-cols-[6.5rem_minmax(0,1fr)] gap-3 py-3 [overflow-wrap:anywhere] sm:grid-cols-[180px_minmax(0,1fr)] sm:gap-4">
                            <span class="text-ink-500 font-medium">Kategori</span>
                            <div class="flex flex-wrap items-center gap-1.5 text-brand-600 font-medium">
                                <a href="{{ route('product.index') }}" class="hover:underline">Produk</a>
                                @if($product->category)
                                    @foreach($product->category->breadcrumbs() as $c)
                                        <span class="text-ink-300">›</span>
                                        <a href="{{ $c->url() }}" class="hover:underline">{{ $c->name }}</a>
                                    @endforeach
                                @endif
                            </div>
                        </div>

                        {{-- Baris Merek --}}
                        @if($product->brand)
                            <div class="grid grid-cols-[6.5rem_minmax(0,1fr)] gap-3 py-3 [overflow-wrap:anywhere] sm:grid-cols-[180px_minmax(0,1fr)] sm:gap-4">
                                <span class="text-ink-500 font-medium">Merek</span>
                                <span class="font-semibold text-ink-900">{{ $product->brand->name }}</span>
                            </div>
                        @endif

                        {{-- Baris Satuan Penjualan --}}
                        @if($product->unit)
                            <div class="grid grid-cols-[6.5rem_minmax(0,1fr)] gap-3 py-3 [overflow-wrap:anywhere] sm:grid-cols-[180px_minmax(0,1fr)] sm:gap-4">
                                <span class="text-ink-500 font-medium">Satuan Penjualan</span>
                                <span class="font-semibold text-ink-900">{{ $product->unit }}</span>
                            </div>
                        @endif

                        {{-- Baris Spesifikasi Dinamis dari Database --}}
                        @if(! empty($product->specifications))
                            @foreach($product->specifications as $attr => $val)
                                <div class="grid grid-cols-[6.5rem_minmax(0,1fr)] gap-3 py-3 [overflow-wrap:anywhere] sm:grid-cols-[180px_minmax(0,1fr)] sm:gap-4">
                                    <span class="text-ink-500 font-medium">{{ $attr }}</span>
                                    <span class="font-semibold text-ink-900">{{ $val }}</span>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </section>

                {{-- Deskripsi Produk dengan Clamp "Lihat Selengkapnya" --}}
                @if($product->description)
                    <section class="rounded-2xl border border-ink-100 bg-white p-4 shadow-sm sm:p-8">
                        <h2 class="font-display text-xl font-bold text-ink-950 sm:text-2xl">Deskripsi Produk</h2>
                        
                        <div class="relative mt-4">
                            <div id="desc-wrapper" class="relative max-h-[300px] overflow-hidden transition-all duration-300">
                                <div class="content leading-relaxed text-ink-700">
                                    {!! $product->description !!}
                                </div>
                                <div id="desc-fade" class="pointer-events-none absolute inset-x-0 bottom-0 h-24 bg-gradient-to-t from-white to-transparent"></div>
                            </div>
                            <div class="mt-3 text-center">
                                <button type="button" id="desc-toggle-btn"
                                        class="inline-flex items-center gap-1.5 rounded-lg border border-ink-200 bg-white px-4 py-2 text-xs font-semibold text-brand-700 transition hover:bg-brand-50 hover:border-brand-300">
                                    <span id="desc-toggle-text">Lihat Selengkapnya</span>
                                    <svg id="desc-toggle-icon" viewBox="0 0 24 24" class="h-3.5 w-3.5 transition-transform" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                                </button>
                            </div>
                        </div>
                    </section>
                @endif

                @include('partials.custom-code', ['model' => $product])
            </div>

            {{-- Kanan Bawah: FAQ & Artikel Edukasi --}}
            <div class="space-y-8">
                {{-- FAQ Produk --}}
                @if($product->faqs->isNotEmpty())
                    <section class="rounded-2xl border border-ink-100 bg-white p-4 shadow-sm sm:p-8">
                        <div class="flex items-center gap-2">
                            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-brand-50 text-brand-700">
                                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            </span>
                            <h2 class="font-display text-xl font-bold text-ink-950">Pertanyaan Umum (FAQ)</h2>
                        </div>
                        <div class="mt-4 space-y-3">
                            @foreach($product->faqs as $f)
                                <details class="group rounded-xl border border-ink-100 p-4 transition-all open:border-brand-300 open:bg-brand-50/30">
                                    <summary class="flex cursor-pointer list-none items-center justify-between font-semibold text-ink-900">
                                        <span>{{ $f->question }}</span>
                                        <svg viewBox="0 0 24 24" class="h-4 w-4 shrink-0 text-ink-400 transition-transform duration-200 group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                                    </summary>
                                    <p class="mt-2.5 text-sm leading-relaxed text-ink-600">{{ $f->answer }}</p>
                                </details>
                            @endforeach
                        </div>
                    </section>
                @endif

                {{-- Artikel Terkait --}}
                @if($articles->isNotEmpty())
                    <section class="rounded-2xl border border-ink-100 bg-white p-4 shadow-sm sm:p-8">
                        <div class="flex items-center justify-between mb-4">
                            <h2 class="font-display text-xl font-bold text-ink-950">Panduan & Artikel</h2>
                            <a href="{{ route('article.index') }}" class="text-xs font-semibold text-brand-600 hover:underline">Semua Artikel →</a>
                        </div>
                        <div class="space-y-4">
                            @foreach($articles as $a)
                                <a href="{{ $a->url() }}" class="group block rounded-xl border border-ink-100 p-3.5 transition hover:border-brand-300 hover:bg-brand-50/20">
                                    <h3 class="font-display text-sm font-semibold text-ink-900 group-hover:text-brand-700">{{ $a->title }}</h3>
                                    <p class="mt-1 line-clamp-2 text-xs text-ink-500">{{ $a->summary }}</p>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>

        </div>

        {{-- ==================== CAROUSEL PRODUK TERKAIT (SHOPEE STYLE) ==================== --}}
        @if($related->isNotEmpty())
            <section class="mt-12">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <h2 class="font-display text-2xl font-bold text-ink-950">Produk Terkait & Rekomendasi</h2>
                        <p class="text-xs text-ink-500 mt-0.5">Pilihan material sejenis dalam kategori yang sama</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" id="rel-prev" aria-label="Produk sebelumnya"
                                class="flex h-9 w-9 items-center justify-center rounded-full border border-ink-200 bg-white text-ink-700 shadow-sm transition hover:bg-ink-50">
                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
                        </button>
                        <button type="button" id="rel-next" aria-label="Produk selanjutnya"
                                class="flex h-9 w-9 items-center justify-center rounded-full border border-ink-200 bg-white text-ink-700 shadow-sm transition hover:bg-ink-50">
                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                        </button>
                    </div>
                </div>

                {{-- Scroll Container --}}
                <div id="related-carousel" class="no-scrollbar flex gap-4 overflow-x-auto pb-4 scroll-smooth snap-x snap-mandatory">
                    @foreach($related as $p)
                        <div class="w-[200px] sm:w-[240px] shrink-0 snap-start">
                            <x-product-card :product="$p" />
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>

    {{-- ==================== MODAL PILIHAN KANAL MARKETPLACE ==================== --}}
    <div id="channel-modal" class="fixed inset-0 z-50 hidden flex items-end justify-center bg-black/60 sm:items-center sm:p-4" role="dialog" aria-modal="true" aria-labelledby="channel-modal-title">
        <div class="max-h-[90dvh] w-full max-w-md overflow-y-auto rounded-t-3xl bg-white p-5 pb-[calc(1.25rem+env(safe-area-inset-bottom))] shadow-2xl sm:rounded-3xl sm:p-6">
            <div class="flex items-center justify-between border-b border-ink-100 pb-4">
                <div>
                    <h3 id="channel-modal-title" class="font-display text-lg font-extrabold text-ink-950">Pilih Toko Rekanan Resmi</h3>
                    <p class="text-xs text-ink-500 mt-0.5">Beli di marketplace resmi rekanan Supermaterial</p>
                </div>
                <button type="button" id="close-channel-modal" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-ink-100 text-ink-600 hover:bg-ink-200" aria-label="Tutup modal">
                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            <div class="mt-4 space-y-3" id="channel-links-container">
                @foreach($marketplaceChannels as $channel)
                    <a href="{{ route('go', ['product' => $product->slug, 'channel' => $channel->slug]) }}"
                       target="_blank" rel="nofollow sponsored noopener"
                       data-base-href="{{ route('go', ['product' => $product->slug, 'channel' => $channel->slug]) }}"
                       class="channel-redirect-btn group flex items-center justify-between rounded-2xl p-3 text-white shadow-md transition-all hover:scale-[1.02] hover:shadow-lg"
                       style="background-color: {{ $channel->color }}">
                        <div class="flex items-center gap-3.5">
                            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-white shadow-sm shrink-0 p-1">
                                <x-channel-logo :channel="$channel->slug" class="h-7 w-7" />
                            </div>
                            <div class="text-left">
                                <span class="block font-display text-base font-bold leading-tight">Beli di {{ $channel->name }}</span>
                                <span class="text-[11px] text-white/85 font-medium">Toko Resmi Rekanan · Transaksi Terjamin</span>
                            </div>
                        </div>
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-white/20 transition group-hover:bg-white/30">
                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M7 17 17 7M8 7h9v9"/></svg>
                        </div>
                    </a>
                @endforeach

                @if($whatsappChannel)
                    <div class="pt-2 border-t border-ink-100">
                        <button type="button" id="modal-wa-btn"
                                class="w-full flex items-center justify-between rounded-2xl border-2 border-emerald-600 bg-emerald-50/70 p-3 text-emerald-900 shadow-sm transition hover:bg-emerald-600 hover:text-white group">
                            <div class="flex items-center gap-3.5">
                                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-white shadow-sm shrink-0 p-1">
                                    <x-channel-logo channel="whatsapp" class="h-7 w-7" />
                                </div>
                                <div class="text-left">
                                    <span class="block font-display text-base font-bold leading-tight">Konsultasi via WhatsApp</span>
                                    <span class="text-[11px] text-emerald-700 group-hover:text-white/90">Respon cepat tim proyek & cek stok</span>
                                </div>
                            </div>
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-200/50 group-hover:bg-white/20 transition">
                                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M7 17 17 7M8 7h9v9"/></svg>
                            </div>
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ==================== LIGHTBOX MODAL FULLSCREEN ==================== --}}
    <div id="lightbox-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/95 p-4" role="dialog" aria-modal="true" aria-label="Galeri produk">
        <button type="button" id="close-lightbox" aria-label="Tutup" class="absolute right-4 top-4 z-20 flex h-10 w-10 items-center justify-center rounded-full bg-white/20 text-white hover:bg-white/30">
            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>

        <button type="button" id="lightbox-prev" aria-label="Media sebelumnya" class="absolute left-4 top-1/2 z-20 -translate-y-1/2 flex h-11 w-11 items-center justify-center rounded-full bg-white/20 text-white hover:bg-white/30">
            <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
        </button>

        <div id="lightbox-content" class="relative max-h-[85vh] max-w-[90vw] overflow-hidden rounded-2xl flex items-center justify-center">
            {{-- Injected dynamically via JS --}}
        </div>

        <button type="button" id="lightbox-next" aria-label="Media selanjutnya" class="absolute right-4 top-1/2 z-20 -translate-y-1/2 flex h-11 w-11 items-center justify-center rounded-full bg-white/20 text-white hover:bg-white/30">
            <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
        </button>
    </div>

    {{-- ==================== MOBILE STICKY PURCHASE BAR ==================== --}}
    @if($channels->isNotEmpty())
        <div class="fixed inset-x-0 bottom-0 z-40 border-t border-ink-100 bg-white p-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] shadow-[0_-8px_24px_rgba(11,15,25,0.08)] lg:hidden">
            <div class="flex items-center gap-3">
                @if($whatsappChannel)
                    <button type="button" id="mobile-sticky-wa"
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-emerald-600 bg-emerald-50 text-emerald-700 shadow-sm p-1.5 transition hover:scale-105"
                            aria-label="Chat WhatsApp">
                        <x-channel-logo channel="whatsapp" class="h-full w-full" />
                    </button>
                @endif

                <div class="flex-1 min-w-0">
                    <p class="text-[11px] text-ink-500 truncate" id="mobile-sticky-title">{{ $product->name }}</p>
                    <p class="truncate font-display text-base font-extrabold text-brand-600" id="mobile-sticky-price">{{ $initialPrice ?: ($product->priceLabel() ?? 'Cek harga') }}</p>
                </div>

                <button type="button" id="mobile-sticky-buy"
                        class="btn shrink-0 bg-brand-400 px-5 py-3 text-sm font-bold text-(--color-on-accent) shadow-md hover:bg-brand-300">
                    <span>Beli Sekarang</span>
                </button>
            </div>
        </div>
    @endif

    {{-- ==================== JAVASCRIPT LOGIC UNTUK SINGLE PRODUCT ==================== --}}
    <script nonce="{{ Vite::cspNonce() }}">
        document.addEventListener('DOMContentLoaded', function () {
            const P = window.__PRODUCT_DATA__;
            let currentMediaIndex = 0;
            let selectedVariant = P.initialVariantId ? P.variants.find(v => v.id === P.initialVariantId) : null;
            let currentQty = 1;
            let showingVariantImage = false;

            // Teks dari admin (alt, nama varian) tidak boleh ditafsirkan sebagai HTML saat disisipkan lewat innerHTML
            const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
            const mainImageHtml = (src, srcset, alt) => `<img id="main-image-el" src="${esc(src)}"${srcset ? ` srcset="${esc(srcset)}" sizes="${esc(P.gallerySizes)}"` : ''} alt="${esc(alt)}" width="800" height="800" class="h-full w-full object-contain p-2 md:p-4 transition duration-300">`;
            const openExternal = (url) => window.open(url, '_blank', 'noopener');

            // Elements
            const mainMediaContainer = document.getElementById('main-media-container');
            const mainImgEl = document.getElementById('main-image-el');
            const priceMain = document.getElementById('price-main');
            const priceCompare = document.getElementById('price-compare');
            const priceDiscountBadge = document.getElementById('price-discount-badge');
            const priceDiscountPct = document.getElementById('price-discount-pct');
            const galleryDiscountBadge = document.getElementById('gallery-discount-badge');
            const galleryDiscountText = document.getElementById('gallery-discount-text');
            const mobileIndicator = document.getElementById('mobile-indicator');
            const variantValidationAlert = document.getElementById('variant-validation-alert');
            const variantSectionBox = document.getElementById('variant-section-box');
            const selectedVariantLabel = document.getElementById('selected-variant-label');
            const qtyInput = document.getElementById('qty-input');
            const qtyMinus = document.getElementById('qty-minus');
            const qtyPlus = document.getElementById('qty-plus');
            const mobileStickyPrice = document.getElementById('mobile-sticky-price');

            // Lightbox
            const lightboxModal = document.getElementById('lightbox-modal');
            const lightboxContent = document.getElementById('lightbox-content');
            const btnOpenLightbox = document.getElementById('btn-open-lightbox');
            const closeLightbox = document.getElementById('close-lightbox');
            const lightboxPrev = document.getElementById('lightbox-prev');
            const lightboxNext = document.getElementById('lightbox-next');

            // Channel Modal
            const channelModal = document.getElementById('channel-modal');
            const closeChannelModal = document.getElementById('close-channel-modal');

            // --- 1. MEDIA GALLERY SWITCHER ---
            function switchMedia(index) {
                if (!P.media || P.media.length === 0) return;
                currentMediaIndex = ((index % P.media.length) + P.media.length) % P.media.length;
                const m = P.media[currentMediaIndex];

                // Update active thumbnail styling
                document.querySelectorAll('.thumb-btn').forEach((btn, i) => {
                    if (i === currentMediaIndex) {
                        btn.className = 'thumb-btn group relative h-16 w-16 md:h-20 md:w-20 shrink-0 overflow-hidden rounded-xl border-2 border-brand-600 ring-2 ring-brand-500/20 transition-all';
                    } else {
                        btn.className = 'thumb-btn group relative h-16 w-16 md:h-20 md:w-20 shrink-0 overflow-hidden rounded-xl border-2 border-ink-100 hover:border-brand-300 transition-all';
                    }
                });

                // Update Mobile Indicator
                if (mobileIndicator) {
                    mobileIndicator.textContent = (currentMediaIndex + 1) + ' / ' + P.media.length;
                }

                // Render main media
                showingVariantImage = false;
                if (m.isVideo) {
                    mainMediaContainer.innerHTML = `
                        <div class="relative h-full w-full cursor-pointer group" id="video-preview-trigger">
                            ${mainImageHtml(m.url, m.srcset, m.alt)}
                            <div class="absolute inset-0 flex items-center justify-center bg-black/20 group-hover:bg-black/30 transition">
                                <div class="flex h-16 w-16 items-center justify-center rounded-full bg-rose-600 text-white shadow-xl group-hover:scale-110 transition">
                                    <svg viewBox="0 0 24 24" class="ml-1 h-8 w-8 fill-current"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                </div>
                            </div>
                        </div>
                    `;
                    document.getElementById('video-preview-trigger').addEventListener('click', function () {
                        playMainVideo(m.embedUrl);
                    });
                } else {
                    mainMediaContainer.innerHTML = mainImageHtml(m.url, m.srcset, m.alt);
                }
            }

            function playMainVideo(embedUrl) {
                if (!embedUrl) return;
                mainMediaContainer.innerHTML = `
                    <iframe src="${esc(embedUrl)}" title="Video Produk" class="h-full w-full rounded-2xl" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                `;
            }

            // Thumbnail: klik/tap memilih; hover hanya untuk perangkat dengan mouse (di layar sentuh memicu render ganda)
            const canHover = window.matchMedia('(hover: hover)').matches;
            document.querySelectorAll('.thumb-btn').forEach(btn => {
                const idx = parseInt(btn.dataset.mediaIndex, 10);
                btn.addEventListener('click', () => switchMedia(idx));
                if (canHover) btn.addEventListener('mouseenter', () => { if (idx !== currentMediaIndex || showingVariantImage) switchMedia(idx); });
            });

            // Geser (swipe) kiri/kanan pada gambar utama untuk berpindah media di layar sentuh
            if (P.media && P.media.length > 1) {
                let touchX = null;
                let touchY = null;
                mainMediaContainer.addEventListener('touchstart', (e) => {
                    touchX = e.touches[0].clientX;
                    touchY = e.touches[0].clientY;
                }, { passive: true });
                mainMediaContainer.addEventListener('touchend', (e) => {
                    if (touchX === null || mainMediaContainer.querySelector('iframe')) return;
                    const dx = e.changedTouches[0].clientX - touchX;
                    const dy = e.changedTouches[0].clientY - touchY;
                    touchX = null;
                    if (Math.abs(dx) < 40 || Math.abs(dx) < Math.abs(dy) * 1.5) return;
                    switchMedia(currentMediaIndex + (dx < 0 ? 1 : -1));
                    document.querySelector(`.thumb-btn[data-media-index="${currentMediaIndex}"]`)?.scrollIntoView({ block: 'nearest', inline: 'center' });
                }, { passive: true });
            }

            // Thumbnail Strip Scroll Buttons
            const thumbStrip = document.getElementById('thumbnail-strip');
            const thumbPrev = document.getElementById('thumb-prev');
            const thumbNext = document.getElementById('thumb-next');
            if (thumbPrev && thumbStrip) {
                thumbPrev.addEventListener('click', () => { thumbStrip.scrollBy({ left: -160, behavior: 'smooth' }); });
            }
            if (thumbNext && thumbStrip) {
                thumbNext.addEventListener('click', () => { thumbStrip.scrollBy({ left: 160, behavior: 'smooth' }); });
            }

            // Video Preview Trigger Click
            const vidTrigger = document.getElementById('video-preview-trigger');
            if (vidTrigger) {
                vidTrigger.addEventListener('click', function () {
                    playMainVideo(this.dataset.embed);
                });
            }

            // Lightbox Logic
            function openLightbox() {
                if (!P.media || P.media.length === 0) return;
                renderLightboxMedia();
                lightboxModal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }

            function closeLightboxModal() {
                lightboxModal.classList.add('hidden');
                document.body.style.overflow = '';
                lightboxContent.innerHTML = '';
            }

            function renderLightboxMedia() {
                const m = P.media[currentMediaIndex];
                if (m.isVideo && m.embedUrl) {
                    lightboxContent.innerHTML = `
                        <iframe src="${esc(m.embedUrl)}" title="Video Produk" class="w-[92vw] aspect-video md:w-[70vw] rounded-xl" frameborder="0" allow="autoplay; encrypted-media" allowfullscreen></iframe>
                    `;
                } else {
                    lightboxContent.innerHTML = `
                        <img src="${esc(m.fullUrl || m.url)}" alt="${esc(m.alt)}" class="max-h-[85vh] max-w-[92vw] object-contain rounded-xl">
                    `;
                }
            }

            // Tap di area gelap menutup lightbox
            lightboxModal.addEventListener('click', (e) => {
                if (e.target === lightboxModal) closeLightboxModal();
            });

            if (btnOpenLightbox) btnOpenLightbox.addEventListener('click', openLightbox);
            if (closeLightbox) closeLightbox.addEventListener('click', closeLightboxModal);
            if (lightboxPrev) {
                lightboxPrev.addEventListener('click', () => {
                    switchMedia(currentMediaIndex - 1);
                    renderLightboxMedia();
                });
            }
            if (lightboxNext) {
                lightboxNext.addEventListener('click', () => {
                    switchMedia(currentMediaIndex + 1);
                    renderLightboxMedia();
                });
            }
            window.addEventListener('keydown', (e) => {
                if (!lightboxModal.classList.contains('hidden')) {
                    if (e.key === 'Escape') closeLightboxModal();
                    if (e.key === 'ArrowLeft') { switchMedia(currentMediaIndex - 1); renderLightboxMedia(); }
                    if (e.key === 'ArrowRight') { switchMedia(currentMediaIndex + 1); renderLightboxMedia(); }
                }
            });

            // --- 2. VARIANT SELECTION & PRICING ---
            function selectVariant(v) {
                selectedVariant = v;
                if (!selectedVariant) return;

                // Update Buttons Visual State
                document.querySelectorAll('.variant-btn').forEach(btn => {
                    const id = parseInt(btn.dataset.variantId, 10);
                    if (id === v.id) {
                        btn.className = 'variant-btn relative flex min-h-11 items-center gap-2 rounded-xl border border-brand-600 ring-2 ring-brand-500/20 bg-brand-50/40 text-brand-700 font-semibold px-3.5 py-2 text-left text-sm shadow-sm transition-all';
                        btn.setAttribute('aria-checked', 'true');
                    } else {
                        btn.className = 'variant-btn relative flex min-h-11 items-center gap-2 rounded-xl border border-ink-200 bg-white text-ink-800 hover:border-brand-400 px-3.5 py-2 text-left text-sm transition-all';
                        btn.setAttribute('aria-checked', 'false');
                    }
                });

                if (selectedVariantLabel) {
                    selectedVariantLabel.textContent = v.name;
                }

                // Update Prices in Price Block
                if (priceMain && v.formattedPrice) {
                    priceMain.textContent = v.formattedPrice + P.priceSuffix;
                }
                if (mobileStickyPrice && v.formattedPrice) {
                    mobileStickyPrice.textContent = v.formattedPrice;
                }

                // Strikethrough price
                if (v.formattedCompare) {
                    priceCompare.textContent = v.formattedCompare;
                    priceCompare.classList.remove('hidden');
                } else {
                    priceCompare.classList.add('hidden');
                }

                // Discount Badge
                if (v.discount) {
                    priceDiscountPct.textContent = '-' + v.discount + '%';
                    priceDiscountBadge.classList.remove('hidden');
                    if (galleryDiscountBadge) {
                        galleryDiscountText.textContent = '-' + v.discount + '%';
                        galleryDiscountBadge.classList.remove('hidden');
                    }
                } else {
                    priceDiscountBadge.classList.add('hidden');
                    if (galleryDiscountBadge) galleryDiscountBadge.classList.add('hidden');
                }

                // Switch gallery media if variant has specific image
                if (v.image) {
                    const matchingIdx = P.media.findIndex(m => m.rawUrl === v.image || m.rawPosterUrl === v.image);
                    if (matchingIdx !== -1) {
                        // Gambar yang sama sudah tampil: jangan dirender ulang (menghindari kedipan dan unduhan ulang)
                        if (matchingIdx !== currentMediaIndex || showingVariantImage) switchMedia(matchingIdx);
                    } else {
                        mainMediaContainer.innerHTML = mainImageHtml(v.displayImage || v.image, v.displaySrcset, v.name);
                        showingVariantImage = true;
                    }
                }

                // Hide validation alert if active
                if (variantValidationAlert) {
                    variantValidationAlert.classList.add('hidden');
                    if (variantSectionBox) variantSectionBox.classList.remove('border-rose-400', 'ring-2', 'ring-rose-200');
                }

                // Update URL query ?varian=slug without full page reload
                try {
                    const currentUrl = new URL(window.location.href);
                    currentUrl.searchParams.set('varian', v.slug);
                    window.history.replaceState({}, '', currentUrl.toString());
                } catch (err) {}
            }

            // Click delegation for variant buttons
            document.addEventListener('click', function (e) {
                const btn = e.target.closest('.variant-btn');
                if (btn) {
                    const id = parseInt(btn.dataset.variantId, 10);
                    const v = P.variants.find(item => item.id === id);
                    if (v) selectVariant(v);
                }
            });

            // --- 3. STEPPER KUANTITAS ---
            function updateQty(newQty) {
                currentQty = Math.min(9999, Math.max(1, newQty));
                if (qtyInput) qtyInput.value = currentQty;
                if (qtyMinus) qtyMinus.disabled = currentQty <= 1;
                if (qtyPlus) qtyPlus.disabled = currentQty >= 9999;
            }

            if (qtyMinus) qtyMinus.addEventListener('click', () => updateQty(currentQty - 1));
            if (qtyPlus) qtyPlus.addEventListener('click', () => updateQty(currentQty + 1));
            if (qtyInput) {
                qtyInput.addEventListener('change', () => updateQty(parseInt(qtyInput.value, 10) || 1));
            }

            // --- 4. VALIDASI & ACTION BUTTONS ---
            function validateSelection() {
                if (P.hasVariants && !selectedVariant) {
                    if (variantValidationAlert) variantValidationAlert.classList.remove('hidden');
                    if (variantSectionBox) {
                        variantSectionBox.classList.add('border-rose-400', 'ring-2', 'ring-rose-200');
                        variantSectionBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    return false;
                }
                return true;
            }

            function getWhatsappUrl() {
                // Kanal WhatsApp aktif: lewat /go/ (mencatat klik, server menyusun pesan dengan varian & jumlah yang sama)
                if (P.waGoUrl) {
                    const url = new URL(P.waGoUrl, window.location.origin);
                    if (selectedVariant) url.searchParams.set('variant', selectedVariant.id);
                    url.searchParams.set('qty', currentQty);

                    return url.toString();
                }

                const number = P.waNumber ? P.waNumber.replace(/\D+/g, '') : '';
                let msg = `Halo, saya tertarik dengan ${P.name}.`;
                const extra = [];
                if (selectedVariant) extra.push(`Varian: ${selectedVariant.name}`);
                if (currentQty > 1 || P.unit) extra.push(`Jumlah: ${currentQty} ${P.unit}`);
                if (extra.length > 0) msg += ` (${extra.join(', ')})`;
                msg += `\n${P.url}`;

                return `https://wa.me/${number}?text=${encodeURIComponent(msg)}`;
            }

            function setChannelModal(open) {
                channelModal.classList.toggle('hidden', !open);
                document.body.style.overflow = open ? 'hidden' : '';
                if (open) closeChannelModal?.focus();
            }

            function handleBuyAction() {
                if (!validateSelection()) return;

                // Update channel modal redirect links with variant and qty params
                document.querySelectorAll('.channel-redirect-btn').forEach(link => {
                    const baseHref = link.dataset.baseHref;
                    const url = new URL(baseHref, window.location.origin);
                    if (selectedVariant) url.searchParams.set('variant', selectedVariant.id);
                    url.searchParams.set('qty', currentQty);
                    link.href = url.toString();
                });

                if (P.marketplaceChannels.length === 1) {
                    const firstLink = document.querySelector('.channel-redirect-btn');
                    if (firstLink) openExternal(firstLink.href);
                } else if (P.marketplaceChannels.length > 1) {
                    setChannelModal(true);
                } else {
                    openExternal(getWhatsappUrl());
                }
            }

            // Button Action Triggers
            const btnActionWa = document.getElementById('btn-action-whatsapp');
            if (btnActionWa) {
                btnActionWa.addEventListener('click', () => {
                    if (!validateSelection()) return;
                    openExternal(getWhatsappUrl());
                });
            }

            const btnActionBuyNow = document.getElementById('btn-action-buy-now');
            if (btnActionBuyNow) btnActionBuyNow.addEventListener('click', handleBuyAction);

            const btnActionBuyConsult = document.getElementById('btn-action-buy-consult');
            if (btnActionBuyConsult) {
                btnActionBuyConsult.addEventListener('click', () => {
                    if (!validateSelection()) return;
                    openExternal(getWhatsappUrl());
                });
            }

            // Mobile Sticky Triggers
            const mobileStickyWa = document.getElementById('mobile-sticky-wa');
            if (mobileStickyWa) {
                mobileStickyWa.addEventListener('click', () => {
                    if (!validateSelection()) return;
                    openExternal(getWhatsappUrl());
                });
            }

            const mobileStickyBuy = document.getElementById('mobile-sticky-buy');
            if (mobileStickyBuy) mobileStickyBuy.addEventListener('click', handleBuyAction);

            // Channel Modal Close
            if (closeChannelModal) {
                closeChannelModal.addEventListener('click', () => setChannelModal(false));
            }
            channelModal.addEventListener('click', (e) => {
                if (e.target === channelModal) setChannelModal(false);
            });
            window.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && !channelModal.classList.contains('hidden')) setChannelModal(false);
            });

            const modalWaBtn = document.getElementById('modal-wa-btn');
            if (modalWaBtn) {
                modalWaBtn.addEventListener('click', () => {
                    if (!validateSelection()) return;
                    setChannelModal(false);
                    openExternal(getWhatsappUrl());
                });
            }

            // --- 5. DESCRIPTION EXPAND / COLLAPSE (LIHAT SELENGKAPNYA) ---
            const descWrapper = document.getElementById('desc-wrapper');
            const descToggleBtn = document.getElementById('desc-toggle-btn');
            const descToggleText = document.getElementById('desc-toggle-text');
            const descToggleIcon = document.getElementById('desc-toggle-icon');
            const descFade = document.getElementById('desc-fade');

            if (descWrapper && descToggleBtn) {
                if (descWrapper.scrollHeight <= 320) {
                    descToggleBtn.parentElement.classList.add('hidden');
                    descFade.classList.add('hidden');
                    descWrapper.classList.remove('max-h-[300px]');
                } else {
                    let isExpanded = false;
                    descToggleBtn.addEventListener('click', () => {
                        isExpanded = !isExpanded;
                        if (isExpanded) {
                            descWrapper.classList.remove('max-h-[300px]');
                            descFade.classList.add('hidden');
                            descToggleText.textContent = 'Sembunyikan';
                            descToggleIcon.classList.add('rotate-180');
                        } else {
                            descWrapper.classList.add('max-h-[300px]');
                            descFade.classList.remove('hidden');
                            descToggleText.textContent = 'Lihat Selengkapnya';
                            descToggleIcon.classList.remove('rotate-180');
                            descWrapper.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        }
                    });
                }
            }

            // --- 6. RELATED PRODUCTS CAROUSEL ---
            const relCarousel = document.getElementById('related-carousel');
            const relPrev = document.getElementById('rel-prev');
            const relNext = document.getElementById('rel-next');
            if (relPrev && relCarousel) {
                relPrev.addEventListener('click', () => relCarousel.scrollBy({ left: -260, behavior: 'smooth' }));
            }
            if (relNext && relCarousel) {
                relNext.addEventListener('click', () => relCarousel.scrollBy({ left: 260, behavior: 'smooth' }));
            }

            // Harga & pilihan awal sudah dirender server. Hanya varian dari tautan ?varian= yang perlu
            // diterapkan ulang (untuk memindahkan galeri ke gambar varian itu).
            if (selectedVariant && P.preselected) {
                selectVariant(selectedVariant);
            }
        });
    </script>
</x-layouts.app>
