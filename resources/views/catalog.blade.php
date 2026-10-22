@php
    $isCategory = (bool) $category;
    $base = $isCategory ? $category->url() : route('product.index');
    $page = $products->currentPage();

    $crumbs = [['Produk', $isCategory ? route('product.index') : null]];
    if ($isCategory) {
        foreach ($category->breadcrumbs() as $c) { $crumbs[] = [$c->name, $c->url()]; }
    }

    // Halaman terfilter: noindex + canonical ke halaman dasar. Paginasi tanpa filter: self-canonical.
    $canonical = ($isCategory && $category->canonical_url) ? $category->canonical_url : $base;
    if (! $filtered && $page > 1) { $canonical = $base.'?page='.$page; }

    $heading = $isCategory ? $category->name : 'Katalog Produk';
    $title = ($isCategory ? $category->seoTitle() : 'Katalog Produk Bahan Bangunan | '.config('app.name')).($page > 1 ? ' — Halaman '.$page : '');
    $description = ($isCategory ? $category->seoDescription() : 'Jelajahi katalog bahan bangunan berdasarkan kategori, merek, dan rentang harga. Bandingkan lalu beli lewat marketplace atau WhatsApp.').($page > 1 ? ' (Halaman '.$page.')' : '');

    // Pembuat URL yang mempertahankan filter aktif
    $current = ['brand' => $brandIds, 'min' => $min, 'max' => $max, 'sort' => $sort === 'relevansi' ? null : $sort];
    $urlWith = function (array $override) use ($base, $current) {
        $params = array_filter(array_merge($current, $override), fn ($v) => $v !== null && $v !== [] && $v !== '');
        return $base.($params ? '?'.http_build_query($params) : '');
    };
    $rp = fn ($n) => 'Rp'.number_format($n, 0, ',', '.');
    $brandNames = $brands->pluck('name', 'id');
    $activeCount = count($brandIds) + ($min || $max ? 1 : 0) + ($sort !== 'relevansi' ? 1 : 0);

    $categoryImage = $category?->primaryImageUrl();
    $firstProductImage = $products->first()?->primaryImageUrl();
    $catalogImage = $categoryImage ?: ($firstProductImage ?: \App\Models\Setting::defaultOgImage());

    $collection = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => $heading,
        'url' => $base,
        'description' => $description,
        'image' => $catalogImage,
    ]);
@endphp
<x-layouts.app :title="$title" :description="$description" :canonical="$canonical"
               :image="$catalogImage"
               :image-alt="$heading"
               :image-width="$isCategory ? 800 : 1200"
               :image-height="$isCategory ? 800 : 630"
               :noindex="($category?->noindex ?? false) || $filtered"
               :nofollow="$category?->nofollow ?? false">
    @push('jsonld')<x-jsonld :data="$collection" />@endpush

    <div class="container-x pt-8">
        <x-breadcrumb :items="$crumbs" />

        <header class="max-w-3xl">
            <h1 class="font-display text-3xl font-extrabold sm:text-4xl">{{ $heading }}</h1>
            @if($isCategory && $category->intro)
                <p class="mt-3 text-lg leading-8 text-ink-600">{{ $category->intro }}</p>
            @elseif(! $isCategory)
                <p class="mt-3 text-lg leading-8 text-ink-600">Temukan bahan bangunan berdasarkan kategori, merek, dan rentang harga.</p>
            @endif
        </header>

        @if($children->isNotEmpty())
            <div class="mt-5 flex flex-wrap gap-2">
                @foreach($children as $child)
                    <a href="{{ $child->url() }}" class="rounded-full border border-ink-200 bg-white px-4 py-1.5 text-sm font-medium transition hover:border-brand-400 hover:text-brand-700">{{ $child->name }}</a>
                @endforeach
            </div>
        @endif

        <div class="mt-8 grid gap-8 lg:grid-cols-[17rem_1fr]">
            {{-- Sidebar katalog: sidebar di desktop, drawer di mobile --}}
            <div data-filter-panel class="fixed inset-0 z-50 hidden lg:sticky lg:top-24 lg:z-auto lg:block lg:self-start">
                <div data-filter-close class="absolute inset-0 bg-ink-950/50 backdrop-blur-sm lg:hidden"></div>
                <aside id="filter-panel" aria-label="Filter katalog"
                       class="absolute inset-y-0 left-0 flex w-[88%] max-w-sm flex-col bg-white shadow-2xl lg:static lg:max-h-[calc(100vh-7rem)] lg:w-auto lg:max-w-none lg:rounded-2xl lg:border lg:border-ink-100 lg:shadow-sm">
                    <div class="flex items-center justify-between border-b border-ink-100 px-5 py-4 lg:hidden">
                        <p class="font-display text-lg font-bold">Filter</p>
                        <button type="button" data-filter-close class="grid h-10 w-10 place-items-center rounded-lg hover:bg-ink-50" aria-label="Tutup filter">
                            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                        </button>
                    </div>

                    <div class="flex-1 space-y-6 overflow-y-auto p-5">
                        <section>
                            <h2 class="mb-2 text-xs font-bold uppercase tracking-wider text-ink-400">Kategori</h2>
                            <a href="{{ route('product.index') }}" @unless($isCategory) aria-current="page" @endunless
                               class="mb-0.5 block rounded-lg px-2.5 py-1.5 text-sm transition {{ $isCategory ? 'text-ink-600 hover:bg-ink-50 hover:text-ink-900' : 'bg-brand-50 font-semibold text-brand-700' }}">Semua produk</a>
                            @include('partials.category-tree', ['parentId' => 0, 'depth' => 0])
                        </section>

                        <form id="catalog-filter" method="get" action="{{ $base }}" class="space-y-6">
                            <section>
                                <label for="sort" class="mb-2 block text-xs font-bold uppercase tracking-wider text-ink-400">Urutkan</label>
                                <select id="sort" name="sort" data-autosubmit class="w-full rounded-lg border border-ink-200 bg-white px-3 py-2.5 text-sm focus:border-brand-400 focus:outline-none">
                                    @foreach($sorts as $value => $label)
                                        <option value="{{ $value === 'relevansi' ? '' : $value }}" @selected($sort === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </section>

                            @if($brands->isNotEmpty())
                                <fieldset>
                                    <legend class="mb-2 text-xs font-bold uppercase tracking-wider text-ink-400">Merek</legend>
                                    <div class="space-y-1">
                                        @foreach($brands as $b)
                                            <label class="flex cursor-pointer items-center gap-2.5 rounded-lg px-2 py-1.5 text-sm hover:bg-ink-50">
                                                <input type="checkbox" name="brand[]" value="{{ $b->id }}" data-autosubmit @checked(in_array($b->id, $brandIds))
                                                       class="h-4 w-4 rounded border-ink-200 accent-brand-500">
                                                {{ $b->name }}
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>
                            @endif

                            <fieldset>
                                <legend class="mb-2 text-xs font-bold uppercase tracking-wider text-ink-400">Rentang harga</legend>
                                <div class="grid grid-cols-2 gap-2">
                                    <label class="block">
                                        <span class="mb-1 block text-xs text-ink-600">Minimum</span>
                                        <input type="number" name="min" min="0" step="1000" inputmode="numeric" value="{{ $min }}" placeholder="Rp0" class="w-full rounded-lg border border-ink-200 px-3 py-2 text-sm focus:border-brand-400 focus:outline-none">
                                    </label>
                                    <label class="block">
                                        <span class="mb-1 block text-xs text-ink-600">Maksimum</span>
                                        <input type="number" name="max" min="0" step="1000" inputmode="numeric" value="{{ $max }}" placeholder="Tanpa batas" class="w-full rounded-lg border border-ink-200 px-3 py-2 text-sm focus:border-brand-400 focus:outline-none">
                                    </label>
                                </div>
                            </fieldset>
                        </form>
                    </div>

                    <div class="flex gap-2 border-t border-ink-100 p-4">
                        <button type="submit" form="catalog-filter" class="btn btn-dark flex-1 !py-2.5">Terapkan filter</button>
                        <a href="{{ $base }}" class="btn btn-ghost !py-2.5 {{ $filtered ? '' : 'pointer-events-none opacity-50' }}" @unless($filtered) aria-disabled="true" tabindex="-1" @endunless>Reset filter</a>
                    </div>
                </aside>
            </div>

            {{-- Daftar produk --}}
            <div>
                <div class="mb-4 flex items-center justify-between gap-3">
                    <p class="text-sm text-ink-600" aria-live="polite"><span class="font-bold text-ink-900">{{ number_format($products->total(), 0, ',', '.') }}</span> produk ditemukan</p>
                    <button type="button" data-filter-open aria-controls="filter-panel" aria-expanded="false" class="btn btn-ghost !py-2 lg:hidden">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg>
                        Filter
                        @if($activeCount)<span class="grid h-5 min-w-5 place-items-center rounded-full bg-brand-500 px-1.5 text-[11px] font-bold text-ink-950">{{ $activeCount }}</span>@endif
                    </button>
                </div>

                @if($filtered)
                    <div class="mb-5 flex flex-wrap items-center gap-2" aria-label="Filter aktif">
                        @foreach($brandIds as $id)
                            @if($brandNames->has($id))
                                <a href="{{ $urlWith(['brand' => array_values(array_diff($brandIds, [$id]))]) }}" class="group inline-flex items-center gap-1.5 rounded-full bg-ink-900 py-1.5 pl-3 pr-2 text-xs font-medium text-white hover:bg-ink-800" aria-label="Hapus filter merek {{ $brandNames[$id] }}">
                                    {{ $brandNames[$id] }} <span aria-hidden="true" class="grid h-4 w-4 place-items-center rounded-full bg-white/20 group-hover:bg-white/30">×</span>
                                </a>
                            @endif
                        @endforeach
                        @if($min || $max)
                            <a href="{{ $urlWith(['min' => null, 'max' => null]) }}" class="group inline-flex items-center gap-1.5 rounded-full bg-ink-900 py-1.5 pl-3 pr-2 text-xs font-medium text-white hover:bg-ink-800" aria-label="Hapus filter harga">
                                {{ $min ? $rp($min) : 'Rp0' }} – {{ $max ? $rp($max) : '∞' }} <span aria-hidden="true" class="grid h-4 w-4 place-items-center rounded-full bg-white/20 group-hover:bg-white/30">×</span>
                            </a>
                        @endif
                        @if($sort !== 'relevansi')
                            <a href="{{ $urlWith(['sort' => null]) }}" class="group inline-flex items-center gap-1.5 rounded-full bg-ink-900 py-1.5 pl-3 pr-2 text-xs font-medium text-white hover:bg-ink-800" aria-label="Hapus pengurutan">
                                {{ $sorts[$sort] }} <span aria-hidden="true" class="grid h-4 w-4 place-items-center rounded-full bg-white/20 group-hover:bg-white/30">×</span>
                            </a>
                        @endif
                        <a href="{{ $base }}" class="ml-1 text-xs font-semibold text-brand-700 hover:underline">Hapus semua</a>
                    </div>
                @endif

                @if($products->isEmpty())
                    <div class="card px-6 py-14 text-center">
                        <p class="font-display text-lg font-bold text-ink-900">Tidak ada produk yang cocok</p>
                        <p class="mt-1 text-sm text-ink-600">Coba ubah atau hapus beberapa filter.</p>
                        @if($filtered)<a href="{{ $base }}" class="btn btn-primary mt-5">Hapus semua filter</a>@endif
                    </div>
                @else
                    <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-3">
                        @foreach($products as $p)<x-product-card :product="$p" />@endforeach
                    </div>
                    <div class="mt-10">{{ $products->links() }}</div>
                @endif
            </div>
        </div>

        @if($isCategory && $category->bottom_content && $page === 1 && ! $filtered)
            <section class="card mt-14 p-6 sm:p-10"><div class="content max-w-none">{!! $category->bottom_content !!}</div></section>
        @endif

        @if($articles->isNotEmpty())
            <section class="mt-14">
                <h2 class="section-title mb-6">Panduan terkait</h2>
                <div class="grid gap-5 md:grid-cols-3">@foreach($articles as $a)<x-article-card :article="$a" />@endforeach</div>
            </section>
        @endif
    </div>
</x-layouts.app>
