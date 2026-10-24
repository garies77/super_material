<x-layouts.app :title="($q ? 'Cari: '.$q : 'Pencarian').' | '.config('app.name')" :noindex="true" :canonical="route('search')">
    <div class="container-x py-6 lg:py-10">
        <x-breadcrumb :items="[['Pencarian', null]]" />

        <div class="border-b border-ink-100 pb-6">
            <h1 class="font-display text-2xl sm:text-3xl lg:text-4xl font-black text-ink-950">
                @if($q)
                    Hasil Pencarian untuk <span class="text-brand-600">“{{ $q }}”</span>
                @else
                    {{ ['produk' => 'Pencarian Produk', 'artikel' => 'Pencarian Artikel'][$type] ?? 'Pencarian Produk & Artikel' }}
                @endif
            </h1>
            <p class="mt-2 text-sm text-ink-600">
                Temukan katalog material konstruksi, spesifikasi mutu SNI, dan artikel panduan teknis bangunan.
            </p>

            <form action="{{ route('search') }}" method="get" role="search" class="mt-5 flex max-w-2xl gap-2">
                @if($type)<input type="hidden" name="type" value="{{ $type }}">@endif
                <div class="relative flex-1">
                    <label for="q-page" class="sr-only">Kata kunci pencarian</label>
                    <svg viewBox="0 0 24 24" class="absolute left-4 top-1/2 -translate-y-1/2 h-5 w-5 text-ink-400" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <input id="q-page" name="q" type="search" value="{{ $q }}"
                           placeholder="Cari semen, besi beton, cat tembok, mortar..."
                           class="w-full rounded-2xl border border-ink-200 bg-white py-3 pl-11 pr-4 text-sm font-medium text-ink-900 shadow-xs outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20">
                </div>
                <button class="btn btn-primary px-6 font-bold" type="submit">
                    <span>Cari</span>
                </button>
            </form>
        </div>

        @if(mb_strlen($q) < 2)
            {{-- State: Query Kurang Dari 2 Karakter --}}
            <div class="my-10 rounded-3xl border border-ink-100 bg-white p-8 text-center shadow-xs">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-50 text-brand-600 mb-4">
                    <svg viewBox="0 0 24 24" class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                </div>
                <h3 class="font-display text-lg font-bold text-ink-900">Mulai Pencarian Material</h3>
                <p class="mt-1 text-sm text-ink-500 max-w-md mx-auto">
                    Ketik minimal 2 karakter untuk mencari produk bangunan, merek, atau panduan edukasi.
                </p>

                <div class="mt-6">
                    <p class="text-xs font-bold uppercase tracking-wider text-ink-400 mb-3">Kata Kunci Populer</p>
                    <div class="flex flex-wrap justify-center gap-2">
                        @foreach(['Semen Tiga Roda', 'Besi Beton Polos', 'Cat Dulux', 'Baja Ringan', 'Mortar Instan', 'Keramik Mulia'] as $suggestion)
                            <a href="{{ route('search', ['q' => $suggestion]) }}"
                               class="inline-flex items-center gap-1.5 rounded-full border border-ink-200 bg-ink-50/60 px-3.5 py-1.5 text-xs font-semibold text-ink-700 hover:border-brand-500 hover:bg-brand-50 hover:text-brand-700 transition">
                                <span class="text-brand-500">🔍</span>
                                <span>{{ $suggestion }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @else
            {{-- Hasil Pencarian Ada atau Kosong --}}
            @if($products->isEmpty() && $articles->isEmpty())
                <div class="my-10 rounded-3xl border border-ink-100 bg-white p-10 text-center shadow-xs">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-amber-50 text-amber-600 mb-4">
                        <svg viewBox="0 0 24 24" class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    </div>
                    <h3 class="font-display text-xl font-bold text-ink-950">
                        Tidak Ditemukan Hasil untuk “{{ $q }}”
                    </h3>
                    <p class="mt-2 text-sm text-ink-500 max-w-lg mx-auto">
                        Kami tidak menemukan produk maupun artikel yang cocok dengan kata kunci tersebut. Coba periksa ejaan atau gunakan istilah yang lebih umum.
                    </p>

                    {{-- Rekomendasi Kata Kunci Populer --}}
                    <div class="mt-8 border-t border-ink-100 pt-6">
                        <p class="text-xs font-bold uppercase tracking-wider text-ink-400 mb-3">Coba Kata Kunci Populer Berikut:</p>
                        <div class="flex flex-wrap justify-center gap-2">
                            @foreach(['Semen Tiga Roda', 'Besi Beton', 'Cat Tembok', 'Mortar', 'Keramik Lantai', 'Baja Ringan'] as $suggestion)
                                <a href="{{ route('search', ['q' => $suggestion]) }}"
                                   class="inline-flex items-center gap-1.5 rounded-full border border-ink-200 bg-white px-3.5 py-1.5 text-xs font-semibold text-ink-700 hover:border-brand-500 hover:bg-brand-50 hover:text-brand-700 transition shadow-2xs">
                                    <span class="text-brand-500">›</span>
                                    <span>{{ $suggestion }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            @else
                {{-- Bagian 1: Hasil Produk --}}
                <div class="mt-8">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="font-display text-xl font-bold text-ink-950 flex items-center gap-2">
                            <span>Produk Terkait</span>
                            <span class="rounded-full bg-brand-100 px-2.5 py-0.5 text-xs font-bold text-brand-700">
                                {{ $products->count() }}
                            </span>
                        </h2>
                    </div>

                    @if($products->isEmpty())
                        <p class="rounded-2xl border border-ink-100 bg-white p-6 text-sm text-ink-400">
                            Tidak ada produk yang cocok dengan kata kunci ini.
                        </p>
                    @else
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                            @foreach($products as $product)
                                <x-product-card :product="$product" />
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Bagian 2: Hasil Artikel --}}
                <div class="mt-12 border-t border-ink-100 pt-8">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="font-display text-xl font-bold text-ink-950 flex items-center gap-2">
                            <span>Artikel &amp; Panduan Terkait</span>
                            <span class="rounded-full bg-brand-100 px-2.5 py-0.5 text-xs font-bold text-brand-700">
                                {{ $articles->count() }}
                            </span>
                        </h2>
                    </div>

                    @if($articles->isEmpty())
                        <p class="rounded-2xl border border-ink-100 bg-white p-6 text-sm text-ink-400">
                            Tidak ada artikel yang cocok dengan kata kunci ini.
                        </p>
                    @else
                        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach($articles as $article)
                                <x-article-card :article="$article" />
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        @endif
    </div>
</x-layouts.app>
