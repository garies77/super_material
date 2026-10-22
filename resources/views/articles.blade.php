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
        <div class="mt-4 flex flex-col md:flex-row md:items-end justify-between gap-4 border-b border-ink-100 pb-6">
            <div>
                <span class="text-xs font-black uppercase tracking-wider text-brand-600">Pusat Edukasi &amp; Berita</span>
                <h1 class="font-display text-3xl sm:text-4xl font-black text-ink-950 mt-1">
                    {{ $title ?? 'Artikel & Panduan Material' }}
                </h1>
                <p class="mt-2 max-w-2xl text-sm sm:text-base text-ink-600">
                    Tips memilih spesifikasi, membandingkan mutu material SNI, dan panduan teknis konstruksi bangunan.
                </p>
            </div>

            {{-- Kategori Tabs/Pills --}}
            @if($categories->isNotEmpty())
                <div class="flex items-center gap-1.5 overflow-x-auto pb-1 max-w-full">
                    <a href="{{ route('article.index') }}"
                       class="shrink-0 rounded-full px-3.5 py-1.5 text-xs font-bold transition {{ !$activeCategory && !$activeTag ? 'bg-ink-950 text-white shadow-xs' : 'bg-ink-100 text-ink-700 hover:bg-ink-200' }}">
                        Semua
                    </a>
                    @foreach($categories as $cat)
                        <a href="{{ route('article.category', $cat->slug) }}"
                           class="shrink-0 rounded-full px-3.5 py-1.5 text-xs font-bold transition {{ $activeCategory && $activeCategory->id === $cat->id ? 'bg-brand-600 text-white shadow-xs' : 'bg-ink-100 text-ink-700 hover:bg-ink-200' }}">
                            {{ $cat->name }}
                        </a>
                    @endforeach
                </div>
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
                <div class="rounded-2xl bg-gradient-to-br from-ink-900 to-ink-950 p-6 text-white shadow-lg relative overflow-hidden">
                    <div class="relative z-10">
                        <span class="inline-block rounded-md bg-brand-500 px-2.5 py-1 text-[11px] font-black uppercase tracking-wider text-ink-950 mb-3">
                            Konsultasi Gratis
                        </span>
                        <h4 class="font-display text-lg font-bold leading-tight text-white">
                            Tanya Kebutuhan Bahan Bangunan Anda
                        </h4>
                        <p class="mt-2 text-xs text-ink-300 leading-relaxed">
                            Spesialis teknis Supermaterial siap membantu menghitung kebutuhan semen, baja ringan, lantai, dan cat sesuai rancangan bangunan Anda.
                        </p>
                        @php
                            $waRaw = preg_replace('/\D+/', '', (string) \App\Models\Setting::get('whatsapp_number', '081234567890'));
                            $waNum = str_starts_with($waRaw, '0') ? '62' . substr($waRaw, 1) : $waRaw;
                        @endphp
                        <a href="https://api.whatsapp.com/send?phone={{ $waNum }}&text={{ rawurlencode('Halo Supermaterial, saya ingin konsultasi kebutuhan material proyek.') }}"
                           target="_blank" rel="noopener noreferrer"
                           class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 py-2.5 text-xs font-bold text-white shadow-md transition hover:bg-emerald-500">
                            <x-channel-logo channel="whatsapp" class="h-4 w-4" />
                            <span>Chat WhatsApp Rekanan</span>
                        </a>
                    </div>
                </div>
            </aside>

        </div>
    </div>
</x-layouts.app>
