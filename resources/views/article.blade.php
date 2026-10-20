@php
    $mainCategory = $article->categories->first();
    $crumbs = [
        ['Artikel', route('article.index')],
    ];
    if ($mainCategory) {
        $crumbs[] = [$mainCategory->name, route('article.category', $mainCategory->slug)];
    }
    $crumbs[] = [$article->title, null];

    $ld = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'NewsArticle',
        'headline' => $article->title,
        'description' => $article->seoDescription(),
        'image' => $article->imageUrl() ?: $article->seoImage(),
        'datePublished' => $article->published_at?->toAtomString(),
        'dateModified' => $article->updated_at->toAtomString(),
        'author' => [
            '@type' => 'Person',
            'name' => $article->author ? $article->author->name : 'Redaksi Supermaterial',
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => \App\Models\Setting::get('org_name', 'Supermaterial'),
            'logo' => [
                '@type' => 'ImageObject',
                'url' => asset('favicon.ico'),
            ],
        ],
        'mainEntityOfPage' => $article->url(),
    ]);

    $faqLd = $article->faqs->isNotEmpty() ? [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => $article->faqs->map(fn ($f) => [
            '@type' => 'Question',
            'name' => $f->question,
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f->answer]
        ])->all(),
    ] : null;

    $shareUrl = rawurlencode($article->url());
    $shareText = rawurlencode($article->title . ' - Supermaterial');
    $waShareUrl = "https://api.whatsapp.com/send?text={$shareText}%20{$shareUrl}";
    $fbShareUrl = "https://www.facebook.com/sharer/sharer.php?u={$shareUrl}";
    $twShareUrl = "https://twitter.com/intent/tweet?text={$shareText}&url={$shareUrl}";
@endphp

<x-layouts.app :title="$article->seoTitle()" :description="$article->seoDescription()" :canonical="$article->canonical_url ?: $article->url()"
               :image="$article->imageUrl() ?: $article->seoImage()" type="article" :noindex="$article->noindex">
    @push('jsonld')
        <x-jsonld :data="$ld" />
        @if($faqLd)<x-jsonld :data="$faqLd" />@endif
    @endpush

    <article class="container-x py-6 lg:py-10">
        {{-- Breadcrumb Dua Tingkat Khas CNN Indonesia --}}
        <div class="mb-4">
            <x-breadcrumb :items="$crumbs" />
        </div>

        {{-- Grid Utama: Kolom Bacaan (Maks 720px) + Sidebar Terpopuler --}}
        <div class="grid gap-10 lg:grid-cols-[minmax(0,1fr)_340px] items-start">
            
            {{-- ==================== KOLOM BACAAN UTAMA ==================== --}}
            <div class="min-w-0 max-w-[740px]">
                
                {{-- 1. Kategori Tag & Judul Artikel (H1) --}}
                @if($mainCategory)
                    <a href="{{ route('article.category', $mainCategory->slug) }}"
                       class="inline-block text-xs font-black uppercase tracking-wider text-brand-600 hover:text-brand-700 mb-2">
                        {{ $mainCategory->name }}
                    </a>
                @endif

                <h1 class="font-display text-2xl sm:text-3xl lg:text-[40px] font-black leading-[1.2] tracking-tight text-ink-950">
                    {{ $article->title }}
                </h1>

                {{-- 2. Metadata Redaksi: Penulis & Tanggal Jam Terbit WIB --}}
                <div class="mt-4 flex flex-wrap items-center gap-x-3 gap-y-1.5 text-xs sm:text-sm text-ink-500 border-b border-ink-100 pb-4">
                    <span class="font-bold text-ink-900">
                        {{ $article->author ? $article->author->name : 'Redaksi Supermaterial' }}
                    </span>
                    <span class="text-ink-300">·</span>
                    <time datetime="{{ $article->published_at?->toIso8601String() }}">
                        {{ $article->formattedPublishedAt() }}
                    </time>
                    @if($updated = $article->formattedUpdatedAt())
                        <span class="text-ink-300">·</span>
                        <span class="text-ink-400">Diperbarui: {{ $updated }}</span>
                    @endif
                    <span class="text-ink-300">·</span>
                    <span class="inline-flex items-center gap-1 text-ink-500 font-medium">
                        <svg class="h-3.5 w-3.5 text-ink-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        {{ $article->readingMinutes() }} menit baca
                    </span>
                </div>

                {{-- 3. Tombol Bagikan Baris 1 (Salin Tautan, FB, X, WA) --}}
                <div class="mt-4 mb-6 flex items-center justify-between gap-2 py-1">
                    <div class="flex items-center gap-2 text-xs font-bold text-ink-500 uppercase tracking-wider">
                        <span>Bagikan:</span>
                        <div class="flex items-center gap-2" id="share-bar-top">
                            {{-- Salin Tautan --}}
                            <div class="relative inline-block" x-data="{ copied: false }">
                                <button type="button"
                                        @click="navigator.clipboard.writeText('{{ $article->url() }}'); copied = true; setTimeout(() => copied = false, 2500)"
                                        class="flex h-9 items-center gap-1.5 rounded-full border border-ink-200 bg-white px-3 text-xs font-semibold text-ink-700 shadow-xs transition hover:border-brand-500 hover:text-brand-600 hover:bg-brand-50/50"
                                        title="Salin Tautan">
                                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg>
                                    <span>Salin</span>
                                </button>
                                <span x-show="copied" x-cloak style="display: none;"
                                      x-transition
                                      class="absolute -top-8 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-md bg-ink-950 px-2 py-1 text-[11px] font-medium text-white shadow-md">
                                    URL berhasil disalin!
                                </span>
                            </div>

                            {{-- WhatsApp --}}
                            <a href="{{ $waShareUrl }}" target="_blank" rel="noopener noreferrer"
                               class="flex h-9 w-9 items-center justify-center rounded-full bg-[#25D366]/10 text-[#25D366] transition hover:bg-[#25D366] hover:text-white"
                               title="Bagikan ke WhatsApp">
                                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor"><path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0 0 12.04 2zm5.79 14.07c-.24.67-1.39 1.29-1.92 1.37-.5.08-1.14.11-3.69-.94-2.88-1.19-4.73-4.14-4.87-4.33-.14-.19-1.16-1.54-1.16-2.94 0-1.4.73-2.09 1-2.37.26-.28.58-.35.78-.35.19 0 .39 0 .56.01.18.01.42-.07.66.5.24.58.83 2.03.9 2.18.08.15.13.33.03.53-.1.19-.15.31-.3.48-.15.17-.31.38-.45.51-.15.14-.3.3-.13.6.17.29.76 1.25 1.63 2.02 1.12.99 2.06 1.3 2.36 1.44.29.15.47.13.64-.07.18-.21.75-.87.95-1.17.2-.3.39-.25.66-.15.26.1 1.69.8 1.98.94.29.15.48.22.56.34.07.13.07.74-.17 1.41z"/></svg>
                            </a>

                            {{-- Facebook --}}
                            <a href="{{ $fbShareUrl }}" target="_blank" rel="noopener noreferrer"
                               class="flex h-9 w-9 items-center justify-center rounded-full bg-[#1877F2]/10 text-[#1877F2] transition hover:bg-[#1877F2] hover:text-white"
                               title="Bagikan ke Facebook">
                                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                            </a>

                            {{-- X (Twitter) --}}
                            <a href="{{ $twShareUrl }}" target="_blank" rel="noopener noreferrer"
                               class="flex h-9 w-9 items-center justify-center rounded-full bg-ink-950/10 text-ink-950 transition hover:bg-ink-950 hover:text-white"
                               title="Bagikan ke X">
                                <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                            </a>
                        </div>
                    </div>
                </div>

                {{-- 4. Gambar Utama (16:9) dengan Keterangan & Kredit Foto --}}
                @if($article->imageUrl())
                    <figure class="my-6">
                        <div class="overflow-hidden rounded-2xl bg-ink-100 shadow-sm">
                            <img src="{{ $article->imageUrl() }}"
                                 alt="{{ $article->image_caption ?: $article->title }}"
                                 width="1200" height="675"
                                 fetchpriority="high"
                                 class="aspect-[16/9] w-full object-cover">
                        </div>
                        <figcaption class="mt-2 text-xs text-ink-500 leading-relaxed">
                            @if($article->image_caption)
                                <span class="italic">{{ $article->image_caption }}</span>
                            @endif
                            <span class="text-ink-400 font-medium">
                                (Foto: {{ $article->image_credit ?: 'Dok. Supermaterial' }})
                            </span>
                        </figcaption>
                    </figure>
                @endif

                {{-- 5. Ringkasan Artikel (Lead Paragraph) --}}
                @if($article->summary)
                    <div class="my-5 rounded-2xl border-l-4 border-brand-500 bg-brand-50/60 p-4 sm:p-5 text-base sm:text-lg font-medium leading-relaxed text-ink-900">
                        {{ $article->summary }}
                    </div>
                @endif

                {{-- 6. Daftar Isi Otomatis (Dapat Dilipat / Accordion) --}}
                @if(count($toc) >= 3)
                    <div class="my-6 rounded-2xl border border-ink-200 bg-white p-5 shadow-xs" x-data="{ open: true }">
                        <div class="flex items-center justify-between cursor-pointer select-none" @click="open = !open">
                            <div class="flex items-center gap-2.5">
                                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-brand-100 text-brand-700">
                                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                                </span>
                                <h3 class="font-display text-base font-bold text-ink-900">Daftar isi</h3>
                            </div>
                            <button type="button" class="text-xs font-semibold text-brand-700 hover:underline flex items-center gap-1">
                                <span x-text="open ? 'Sembunyikan' : 'Tampilkan'"></span>
                                <svg viewBox="0 0 24 24" class="h-4 w-4 transition-transform duration-200" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                            </button>
                        </div>
                        <ul x-show="open" x-cloak class="mt-4 space-y-2.5 border-t border-ink-100 pt-3 text-sm">
                            @foreach($toc as $item)
                                <li class="{{ $item['level'] === 3 ? 'pl-5 text-xs text-ink-600' : 'font-medium text-ink-800' }}">
                                    <a href="#{{ $item['id'] }}" class="inline-flex items-center gap-1.5 transition hover:text-brand-600">
                                        <span class="text-brand-500">›</span>
                                        <span>{{ $item['text'] }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- 7. Isi Artikel (Format Berita CNN Indonesia dengan Dateline & Tipografi Nyaman) --}}
                <div class="content mt-6 text-[17px] leading-[1.8] text-ink-800">
                    {!! $html !!}
                </div>

                {{-- 8. Inisial Redaksi di Akhir Artikel --}}
                @if($initials = $article->initialsBlock())
                    <p class="mt-6 text-sm font-bold italic text-ink-500">
                        {{ $initials }}
                    </p>
                @endif

                {{-- 9. Tombol Bagikan Baris 2 (Di Bawah Isi) --}}
                <div class="mt-8 border-t border-b border-ink-100 py-4 flex flex-wrap items-center justify-between gap-3">
                    <span class="text-xs font-bold text-ink-500 uppercase tracking-wider">Bagikan artikel ini:</span>
                    <div class="flex items-center gap-2">
                        <a href="{{ $waShareUrl }}" target="_blank" rel="noopener noreferrer"
                           class="inline-flex items-center gap-1.5 rounded-full bg-[#25D366] px-4 py-2 text-xs font-bold text-white shadow-xs transition hover:opacity-90">
                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor"><path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.816 9.816 0 0 0 12.04 2zm5.79 14.07c-.24.67-1.39 1.29-1.92 1.37-.5.08-1.14.11-3.69-.94-2.88-1.19-4.73-4.14-4.87-4.33-.14-.19-1.16-1.54-1.16-2.94 0-1.4.73-2.09 1-2.37.26-.28.58-.35.78-.35.19 0 .39 0 .56.01.18.01.42-.07.66.5.24.58.83 2.03.9 2.18.08.15.13.33.03.53-.1.19-.15.31-.3.48-.15.17-.31.38-.45.51-.15.14-.3.3-.13.6.17.29.76 1.25 1.63 2.02 1.12.99 2.06 1.3 2.36 1.44.29.15.47.13.64-.07.18-.21.75-.87.95-1.17.2-.3.39-.25.66-.15.26.1 1.69.8 1.98.94.29.15.48.22.56.34.07.13.07.74-.17 1.41z"/></svg>
                            <span>WhatsApp</span>
                        </a>
                        <a href="{{ $fbShareUrl }}" target="_blank" rel="noopener noreferrer"
                           class="inline-flex items-center gap-1.5 rounded-full bg-[#1877F2] px-4 py-2 text-xs font-bold text-white shadow-xs transition hover:opacity-90">
                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                            <span>Facebook</span>
                        </a>
                        <a href="{{ $twShareUrl }}" target="_blank" rel="noopener noreferrer"
                           class="inline-flex items-center gap-1.5 rounded-full bg-ink-950 px-4 py-2 text-xs font-bold text-white shadow-xs transition hover:bg-ink-800">
                            <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                            <span>X</span>
                        </a>
                    </div>
                </div>

                {{-- 10. Topik Terkait (Tag Chip) --}}
                @if($article->tags->isNotEmpty())
                    <div class="mt-6">
                        <p class="text-xs font-black uppercase tracking-wider text-ink-400 mb-3">Topik Terkait</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($article->tags as $tag)
                                <a href="{{ route('article.tag', $tag->slug) }}"
                                   class="inline-flex items-center gap-1.5 rounded-full border border-ink-200 bg-white px-3.5 py-1.5 text-xs font-semibold text-ink-700 shadow-xs transition hover:border-brand-500 hover:bg-brand-50 hover:text-brand-700">
                                    <span class="text-brand-500 font-bold">#</span>
                                    <span>{{ $tag->name }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- 11. FAQ Artikel (Bila Ada) --}}
                @if($article->faqs->isNotEmpty())
                    <section class="mt-10 rounded-2xl border border-ink-200 bg-white p-6 shadow-xs">
                        <h2 class="font-display text-xl font-bold text-ink-950 mb-4">Pertanyaan Seputar Artikel</h2>
                        <div class="space-y-3">
                            @foreach($article->faqs as $faq)
                                <details class="group rounded-xl border border-ink-100 bg-ink-50/60 p-4 transition open:border-brand-300 open:bg-brand-50/30">
                                    <summary class="flex cursor-pointer list-none items-center justify-between font-bold text-ink-900">
                                        <span>{{ $faq->question }}</span>
                                        <svg class="h-4 w-4 shrink-0 transition group-open:rotate-180 text-ink-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                                    </summary>
                                    <p class="mt-2 text-sm leading-relaxed text-ink-600">{{ $faq->answer }}</p>
                                </details>
                            @endforeach
                        </div>
                    </section>
                @endif

                {{-- 12. Terpopuler di Mobile (Muncul di Bawah Isi pada Layar Kecil) --}}
                @if($popular->isNotEmpty())
                    <div class="mt-10 lg:hidden">
                        <div class="border-t-2 border-brand-500 pt-4">
                            <h3 class="font-display text-lg font-black uppercase tracking-tight text-ink-950 mb-4">
                                Terpopuler
                            </h3>
                            <div class="divide-y divide-ink-100">
                                @foreach($popular as $index => $item)
                                    <a href="{{ $item->url() }}" class="flex items-start gap-4 py-3 group">
                                        <span class="font-display text-2xl font-black text-brand-600 w-6 shrink-0 leading-none pt-1">
                                            {{ $index + 1 }}
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            @if($cat = $item->categories->first())
                                                <span class="text-[11px] font-bold uppercase tracking-wider text-brand-600">
                                                    {{ $cat->name }}
                                                </span>
                                            @endif
                                            <h4 class="font-display text-sm font-bold text-ink-900 leading-snug group-hover:text-brand-600 transition">
                                                {{ $item->title }}
                                            </h4>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

            </div>

            {{-- ==================== SIDEBAR KANAN (DESKTOP) ==================== --}}
            <aside class="hidden lg:block space-y-8 sticky top-24">
                
                {{-- Widget 1: Terpopuler (CNN Indonesia Style Numbered 1 to 5) --}}
                @if($popular->isNotEmpty())
                    <div class="rounded-2xl border border-ink-100 bg-white p-5 shadow-sm">
                        <div class="border-b-2 border-brand-500 pb-3 mb-4 flex items-center justify-between">
                            <h3 class="font-display text-base font-black uppercase tracking-tight text-ink-950">
                                Terpopuler
                            </h3>
                            <span class="text-[11px] font-bold text-brand-600 bg-brand-50 px-2 py-0.5 rounded-full">
                                Trending
                            </span>
                        </div>
                        <div class="divide-y divide-ink-100">
                            @foreach($popular as $index => $item)
                                <a href="{{ $item->url() }}" class="flex items-start gap-3.5 py-3 group">
                                    <span class="font-display text-3xl font-black text-brand-500 w-7 shrink-0 leading-none pt-0.5 group-hover:text-brand-600 transition">
                                        {{ $index + 1 }}
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        @if($cat = $item->categories->first())
                                            <span class="text-[10px] font-bold uppercase tracking-wider text-brand-700">
                                                {{ $cat->name }}
                                            </span>
                                        @endif
                                        <h4 class="font-display text-sm font-bold text-ink-900 leading-snug group-hover:text-brand-600 transition line-clamp-2">
                                            {{ $item->title }}
                                        </h4>
                                        <p class="text-[11px] text-ink-400 mt-1">
                                            {{ $item->readingMinutes() }} menit baca
                                        </p>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Widget 2: Banner Promo Rekanan Jaya Intero / Konsultasi Proyek --}}
                <div class="rounded-2xl bg-gradient-to-br from-ink-900 to-ink-950 p-6 text-white shadow-lg relative overflow-hidden">
                    <div class="relative z-10">
                        <span class="inline-block rounded-md bg-brand-500 px-2.5 py-1 text-[11px] font-black uppercase tracking-wider text-ink-950 mb-3">
                            Konsultasi Material
                        </span>
                        <h4 class="font-display text-lg font-bold leading-tight">
                            Butuh Pasokan Material Proyek dalam Jumlah Besar?
                        </h4>
                        <p class="mt-2 text-xs text-ink-300 leading-relaxed">
                            Hubungi tim spesialis Supermaterial untuk penawaran harga grosir, cek ketersediaan stok, dan pengiriman tepat waktu.
                        </p>
                        <a href="https://api.whatsapp.com/send?phone=6281234567890&text={{ rawurlencode('Halo Supermaterial, saya membaca artikel ' . $article->title . ' dan ingin konsultasi material proyek.') }}"
                           target="_blank" rel="noopener noreferrer"
                           class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 py-2.5 text-xs font-bold text-white shadow-md transition hover:bg-emerald-500">
                            <x-channel-logo channel="whatsapp" class="h-4 w-4" />
                            <span>Hubungi Tim Proyek</span>
                        </a>
                    </div>
                </div>

                {{-- Widget 3: Kategori Artikel --}}
                @if($article->categories->isNotEmpty())
                    <div class="rounded-2xl border border-ink-100 bg-white p-5 shadow-sm">
                        <h3 class="font-display text-sm font-black uppercase tracking-tight text-ink-900 mb-3">
                            Kategori Terkait
                        </h3>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($article->categories as $c)
                                <a href="{{ route('article.category', $c->slug) }}"
                                   class="inline-block rounded-lg bg-ink-50 px-2.5 py-1 text-xs font-medium text-ink-700 hover:bg-brand-50 hover:text-brand-700 transition">
                                    {{ $c->name }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

            </aside>
        </div>

        {{-- ==================== BAGIAN BAWAH: PRODUK TERKAIT ==================== --}}
        @if($products->isNotEmpty())
            <section class="mt-14 border-t border-ink-200 pt-10">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <span class="text-xs font-black uppercase tracking-wider text-brand-600">Katalog Rekomendasi</span>
                        <h2 class="font-display text-2xl font-black text-ink-950">Produk yang Dibahas dalam Artikel</h2>
                    </div>
                    <a href="{{ route('product.index') }}" class="hidden sm:inline-flex items-center gap-1 text-xs font-bold text-brand-700 hover:underline">
                        <span>Lihat Semua Produk</span>
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                    </a>
                </div>

                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach($products as $product)
                        <x-product-card :product="$product" />
                    @endforeach
                </div>
            </section>
        @endif

        {{-- ==================== BAGIAN BAWAH: ARTIKEL TERKAIT & REKOMENDASI ==================== --}}
        @if($related->isNotEmpty() || $recommendations->isNotEmpty())
            <section class="mt-14 border-t border-ink-200 pt-10">
                <div class="mb-6">
                    <span class="text-xs font-black uppercase tracking-wider text-brand-600">Baca Selanjutnya</span>
                    <h2 class="font-display text-2xl font-black text-ink-950">Artikel Terkait & Rekomendasi Untukmu</h2>
                </div>

                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach($related->concat($recommendations)->unique('id')->take(4) as $item)
                        <x-article-card :article="$item" />
                    @endforeach
                </div>
            </section>
        @endif

    </article>
</x-layouts.app>
