@php
    $crumbs = [['Artikel', $activeCategory || $activeTag ? route('article.index') : null]];
    if ($activeCategory) {
        $crumbs[] = [$activeCategory->name, null];
    } elseif ($activeTag) {
        $crumbs[] = ['#' . $activeTag->name, null];
    }
    
    $currentUrl = request()->url();
    $page = $articles->currentPage();
    $canonical = $page > 1 ? $currentUrl . '?page=' . $page : $currentUrl;
    $pageSuffix = $page > 1 ? ' — Halaman ' . $page : '';

    // Deskripsi unik per daftar dan per halaman paginasi (PRD 9.11.5, SEO-10)
    $listDescription = match (true) {
        (bool) $activeCategory => 'Artikel dan panduan seputar ' . $activeCategory->name . ': tips memilih, membandingkan spesifikasi, dan estimasi kebutuhan material.',
        (bool) $activeTag => 'Kumpulan artikel bertopik ' . $activeTag->name . ' tentang bahan bangunan dan konstruksi.',
        default => 'Panduan memilih, membandingkan spesifikasi teknis, dan estimasi kebutuhan bahan bangunan langsung dari pakar konstruksi.',
    } . ($page > 1 ? ' Halaman ' . $page . '.' : '');
    $articlesImage = $featured?->primaryImageUrl() ?: ($articles->first()?->primaryImageUrl() ?: \App\Models\Setting::defaultOgImage());
@endphp

<x-layouts.app :title="($title ?? 'Artikel & Panduan Bahan Bangunan') . $pageSuffix . ' | ' . config('app.name')"
               :description="$listDescription"
               :canonical="$canonical"
               :image="$articlesImage"
               :image-alt="$title ?? 'Artikel & Panduan Bahan Bangunan'"
               :image-width="1200"
               :image-height="675">
    <div class="container-x py-6 lg:py-10">
        <x-breadcrumb :items="$crumbs" />

        {{-- Header Halaman Artikel --}}
        <div class="mt-4 border-b border-ink-100 pb-6">
            <div>
                <span class="text-xs font-black uppercase tracking-wider text-brand-600">Pusat Edukasi &amp; Berita</span>
                <h1 class="font-display text-3xl sm:text-4xl font-black text-ink-950 mt-1">
                    {{ $title ?? 'Artikel & Panduan Material' }}
                </h1>
                <p class="mt-2 max-w-2xl text-sm sm:text-base text-ink-600">
                    Tips memilih spesifikasi, membandingkan mutu material SNI, dan panduan teknis konstruksi bangunan.
                </p>
            </div>

            {{--
                Filter kategori: satu baris selebar halaman yang bisa digeser (sentuh, trackpad, roda mouse, atau tombol panah).
                Tanpa scrollbar; bayangan tepi & panah hanya muncul bila masih ada kategori tersembunyi di sisi itu.
                Perilaku di resources/js/app.js ([data-chip-scroller]); kategori aktif otomatis digeser ke tengah.
            --}}
            @if($categories->isNotEmpty())
                @php
                    $chip = 'inline-flex shrink-0 snap-start items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 focus-visible:ring-offset-ink-50';
                    $chipIdle = 'border-ink-200 bg-white text-ink-700 hover:border-brand-300 hover:text-brand-700';
                    $chipActive = 'border-brand-600 bg-brand-600 text-white shadow-sm';
                    $countIdle = 'bg-ink-100 text-ink-500';
                    $countActive = 'bg-white/20 text-white';
                    $allActive = ! $activeCategory && ! $activeTag;
                @endphp
                <nav aria-label="Kategori artikel" class="group relative mt-6" data-chip-scroller>
                    <div class="pointer-events-none absolute inset-y-0 left-0 z-10 w-16 bg-gradient-to-r from-ink-50 to-transparent opacity-0 transition-opacity duration-200 group-data-[can-left]:opacity-100"></div>
                    <div class="pointer-events-none absolute inset-y-0 right-0 z-10 w-16 bg-gradient-to-l from-ink-50 to-transparent opacity-0 transition-opacity duration-200 group-data-[can-right]:opacity-100"></div>

                    <button type="button" data-scroll="left" aria-label="Kategori sebelumnya" tabindex="-1"
                            class="pointer-events-none absolute left-0 top-1/2 z-20 hidden h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full border border-ink-200 bg-white text-ink-700 opacity-0 shadow-md transition hover:border-brand-300 hover:text-brand-700 group-data-[can-left]:pointer-events-auto group-data-[can-left]:opacity-100 md:flex">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
                    </button>
                    <button type="button" data-scroll="right" aria-label="Kategori berikutnya" tabindex="-1"
                            class="pointer-events-none absolute right-0 top-1/2 z-20 hidden h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full border border-ink-200 bg-white text-ink-700 opacity-0 shadow-md transition hover:border-brand-300 hover:text-brand-700 group-data-[can-right]:pointer-events-auto group-data-[can-right]:opacity-100 md:flex">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                    </button>

                    <div data-track class="relative flex snap-x scroll-px-1 gap-2 overflow-x-auto scroll-smooth px-1 py-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                        <a href="{{ route('article.index') }}" @if($allActive) aria-current="page" @endif
                           class="{{ $chip }} {{ $allActive ? $chipActive : $chipIdle }}">
                            Semua
                        </a>
                        @foreach($categories as $cat)
                            @php $isActive = $activeCategory && $activeCategory->id === $cat->id; @endphp
                            <a href="{{ route('article.category', $cat->slug) }}" @if($isActive) aria-current="page" @endif
                               class="{{ $chip }} {{ $isActive ? $chipActive : $chipIdle }}">
                                {{ $cat->name }}
                                <span class="rounded-full px-1.5 py-0.5 text-[11px] font-bold leading-none {{ $isActive ? $countActive : $countIdle }}">{{ $cat->articles_count }}</span>
                            </a>
                        @endforeach
                    </div>
                </nav>
            @endif
        </div>

        {{-- Main Layout: Kolom Berita & Sidebar Terpopuler --}}
        <div class="mt-8 grid gap-10 lg:grid-cols-[minmax(0,1fr)_340px] items-start">
            
            {{-- ==================== KOLOM KONTEN ==================== --}}
            <div class="min-w-0">
                
                {{-- 1. Artikel Unggulan (Headline) - Tampil di Halaman 1 --}}
                @if($featured && $articles->currentPage() === 1)
                    <div class="mb-10 rounded-3xl border border-ink-100 bg-white p-4 sm:p-6 shadow-sm hover:shadow-md transition">
                        <div class="grid gap-6 md:grid-cols-2 items-center">
                            <a href="{{ $featured->url() }}" class="overflow-hidden rounded-2xl bg-ink-100 block aspect-[16/10] group">
                                @if($featured->imageUrl())
                                    <img src="{{ $featured->imageUrl() }}"
                                         alt="{{ $featured->title }}"
                                         width="700" height="437"
                                         fetchpriority="high"
                                         class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                                @else
                                    <div class="placeholder-art h-full w-full"></div>
                                @endif
                            </a>
                            <div class="flex flex-col justify-between">
                                <div>
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="rounded-md bg-brand-500 px-2 py-0.5 text-[10px] font-black uppercase tracking-wider text-ink-950">
                                            Headline
                                        </span>
                                        @if($cat = $featured->categories->first())
                                            <span class="text-xs font-bold text-brand-700">
                                                {{ $cat->name }}
                                            </span>
                                        @endif
                                    </div>
                                    <a href="{{ $featured->url() }}" class="group">
                                        <h2 class="font-display text-xl sm:text-2xl font-black text-ink-950 leading-tight group-hover:text-brand-600 transition">
                                            {{ $featured->title }}
                                        </h2>
                                    </a>
                                    <p class="mt-3 text-sm text-ink-600 leading-relaxed line-clamp-3">
                                        {{ $featured->summary ?: \Illuminate\Support\Str::limit(strip_tags($featured->content), 180) }}
                                    </p>
                                </div>
                                <div class="mt-5 flex items-center justify-between text-xs text-ink-400 border-t border-ink-100 pt-3">
                                    <span>
                                        {{ $featured->author ? $featured->author->name : 'Supermaterial' }} · 
                                        {{ $featured->published_at?->diffInHours() < 24 ? $featured->published_at?->diffForHumans() : $featured->published_at?->translatedFormat('d M Y') }}
                                    </span>
                                    <a href="{{ $featured->url() }}" class="font-bold text-brand-600 hover:underline">
                                        Baca Selengkapnya ›
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- 2. Daftar Artikel Terbaru (Baris Grid Berita CNN) --}}
                @if($articles->isEmpty() && !$featured)
                    <div class="rounded-2xl border border-ink-100 bg-white p-12 text-center text-ink-500">
                        <svg class="mx-auto h-12 w-12 text-ink-300 mb-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <p class="font-bold text-base text-ink-800">Belum ada artikel pada kategori ini.</p>
                        <p class="text-xs text-ink-400 mt-1">Silakan cek kembali nanti atau lihat kategori lainnya.</p>
                    </div>
                @else
                    <div class="space-y-4">
                        @foreach($articles as $a)
                            <article class="flex flex-col sm:flex-row items-start gap-4 rounded-2xl border border-ink-100 bg-white p-4 shadow-2xs hover:shadow-md transition group">
                                <a href="{{ $a->url() }}" class="w-full sm:w-48 sm:h-32 shrink-0 overflow-hidden rounded-xl bg-ink-100 block aspect-[16/10] sm:aspect-auto">
                                    @if($a->imageUrl())
                                        <img src="{{ $a->imageUrl() }}"
                                             alt="{{ $a->title }}"
                                             loading="lazy"
                                             width="300" height="200"
                                             class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
                                    @else
                                        <div class="placeholder-art h-full w-full"></div>
                                    @endif
                                </a>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1.5">
                                        @if($cat = $a->categories->first())
                                            <a href="{{ route('article.category', $cat->slug) }}"
                                               class="text-[11px] font-black uppercase tracking-wider text-brand-600 hover:text-brand-700">
                                                {{ $cat->name }}
                                            </a>
                                            <span class="text-ink-300">·</span>
                                        @endif
                                        <span class="text-xs text-ink-400">
                                            {{ $a->published_at?->diffInHours() < 24 ? $a->published_at?->diffForHumans() : $a->published_at?->translatedFormat('d M Y') }}
                                        </span>
                                    </div>

                                    <a href="{{ $a->url() }}">
                                        <h3 class="font-display text-base sm:text-lg font-bold text-ink-950 leading-snug group-hover:text-brand-600 transition line-clamp-2">
                                            {{ $a->title }}
                                        </h3>
                                    </a>

                                    <p class="mt-1.5 text-xs sm:text-sm text-ink-600 line-clamp-2 leading-relaxed">
                                        {{ $a->summary ?: \Illuminate\Support\Str::limit(strip_tags($a->content), 120) }}
                                    </p>
                                </div>
                            </article>
                        @endforeach
                    </div>

                    {{-- Paginasi Bersih & Crawlable --}}
                    <div class="mt-8">
                        {{ $articles->links() }}
                    </div>
                @endif

            </div>

            {{-- ==================== SIDEBAR TERPOPULER ==================== --}}
            <aside class="space-y-6">
                @if($popular->isNotEmpty())
                    <div class="rounded-2xl border border-ink-100 bg-white p-5 shadow-sm">
                        <div class="border-b-2 border-brand-500 pb-3 mb-4 flex items-center justify-between">
                            <h3 class="font-display text-base font-black uppercase tracking-tight text-ink-950">
                                Terpopuler
                            </h3>
                            <span class="text-[11px] font-bold text-brand-600 bg-brand-50 px-2 py-0.5 rounded-full">
                                Pilihan Pembaca
                            </span>
                        </div>
                        <div class="divide-y divide-ink-100">
                            @foreach($popular as $index => $item)
                                <a href="{{ $item->url() }}" class="flex items-start gap-3 py-3 group">
                                    <span class="font-display text-2xl font-black text-brand-500 w-6 shrink-0 leading-none pt-0.5 group-hover:text-brand-600 transition">
                                        {{ $index + 1 }}
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        @if($cat = $item->categories->first())
                                            <span class="text-[10px] font-bold uppercase tracking-wider text-brand-700">
                                                {{ $cat->name }}
                                            </span>
                                        @endif
                                        <h4 class="font-display text-xs sm:text-sm font-bold text-ink-900 leading-snug group-hover:text-brand-600 transition line-clamp-2">
                                            {{ $item->title }}
                                        </h4>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Banner Konsultasi Proyek --}}
                @include('partials.consult-card', [
                    'badge' => 'Konsultasi Gratis',
                    'title' => 'Tanya Kebutuhan Bahan Bangunan Anda',
                    'description' => 'Spesialis teknis Supermaterial siap membantu menghitung kebutuhan semen, baja ringan, lantai, dan cat sesuai rancangan bangunan Anda.',
                    'button' => 'Chat WhatsApp Rekanan',
                    'message' => 'Halo Supermaterial, saya ingin konsultasi kebutuhan material proyek.',
                ])
            </aside>

        </div>
    </div>
</x-layouts.app>
