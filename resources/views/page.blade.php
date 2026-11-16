@php
    $pageImage = $page->seoImage() ?: \App\Models\Setting::defaultOgImage();
    // Pratinjau mengirim $blocks berisi draf; halaman publik memakai versi terbit
    $blocks ??= $page->blocks;

    // Halaman yang memuat grid produk marketplace memakai tata letak landing page:
    // hero (blok sebelum grid pertama), grid selebar kontainer, lalu kolom baca + kartu bantuan menempel.
    $blockList = array_values((array) ($blocks ?? []));
    $productIndexes = array_keys(array_filter($blockList, fn ($b) => ($b['type'] ?? null) === 'products'));
    $isLanding = $productIndexes !== [];
    if ($isLanding) {
        $first = min($productIndexes);
        $last = max($productIndexes);
        $leadBlocks = array_slice($blockList, 0, $first);
        $gridBlocks = array_slice($blockList, $first, $last - $first + 1);
        $tailBlocks = array_slice($blockList, $last + 1);
        $channelName = \App\Support\PageProductGrid::channel($blockList[$first]['data'] ?? [])?->name;
        $whatsappNumber = preg_replace('/\D+/', '', (string) \App\Models\Setting::get('whatsapp_number'));
        $whatsappNumber = str_starts_with($whatsappNumber, '0') ? '62'.substr($whatsappNumber, 1) : $whatsappNumber;
        $whatsappUrl = $whatsappNumber !== ''
            ? 'https://wa.me/'.$whatsappNumber.'?text='.rawurlencode('Halo '.\App\Models\Setting::brandName().', saya mau tanya soal produk'.($channelName ? ' di '.$channelName : '').'.')
            : null;
    }
@endphp
<x-layouts.app :title="($page->meta_title ?: $page->title).' | '.config('app.name')"
               :description="$page->meta_description ?: $page->seoFallbackDescription()"
               :canonical="$page->canonical_url ?: url($page->slug)"
               :image="$pageImage"
               :image-alt="$page->title"
               :noindex="($preview ?? false) || ($page->noindex ?? false)"
               :nofollow="$page->nofollow ?? false">
    @if($isLanding)
        <div class="container-x pb-16 pt-6 sm:pt-8">
            <x-breadcrumb :items="[[$page->title, null]]" />

            <header class="relative mt-2 overflow-hidden rounded-3xl border border-ink-100 bg-white px-5 py-7 shadow-sm sm:px-10 sm:py-10">
                <div aria-hidden="true" class="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-brand-100/70 blur-3xl"></div>
                <div class="relative max-w-3xl">
                    @if($channelName)
                        <p class="inline-flex items-center gap-2 rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-brand-700">Belanja di {{ $channelName }}</p>
                    @endif
                    <h1 class="mt-3 font-display text-3xl font-extrabold leading-tight text-ink-950 sm:text-4xl lg:text-[2.75rem]">{{ $page->title }}</h1>
                    @if($leadBlocks)
                        <div class="page-lead mt-4">
                            @include('partials.page-blocks', ['blocks' => $leadBlocks, 'wide' => true])
                        </div>
                    @endif
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="#produk" class="btn btn-primary">Lihat produk</a>
                        @if($whatsappUrl)
                            <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="btn btn-ghost">Tanya via WhatsApp</a>
                        @endif
                    </div>
                </div>
            </header>

            <div class="mt-10 sm:mt-12">
                @include('partials.page-blocks', ['blocks' => $gridBlocks, 'wide' => true])
            </div>

            @if($tailBlocks)
                <div class="mt-14 grid gap-10 lg:grid-cols-[minmax(0,1fr)_20rem] lg:gap-14">
                    <div class="min-w-0">
                        @include('partials.page-blocks', ['blocks' => $tailBlocks, 'wide' => true])
                    </div>
                    <aside class="lg:sticky lg:top-24 lg:self-start">
                        <div class="rounded-2xl border border-ink-100 bg-white p-6 shadow-sm">
                            <p class="font-display text-lg font-bold text-ink-900">Butuh bantuan memilih?</p>
                            <p class="mt-2 text-sm leading-relaxed text-ink-600">Ceritakan kebutuhan proyek Anda. Kami bantu pilihkan produk yang cocok sebelum Anda checkout{{ $channelName ? ' di '.$channelName : '' }}.</p>
                            <div class="mt-4 flex flex-col gap-2">
                                @if($whatsappUrl)
                                    <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener" class="btn btn-primary w-full justify-center">Tanya via WhatsApp</a>
                                @endif
                                <a href="{{ route('product.index') }}" class="btn btn-ghost w-full justify-center">Lihat semua produk</a>
                            </div>
                            <p class="mt-4 border-t border-ink-100 pt-4 text-xs leading-relaxed text-ink-500">Harga di situs ini adalah harga acuan. Harga final, ongkir, dan promo mengikuti {{ $channelName ?? 'marketplace' }} saat checkout.</p>
                        </div>
                    </aside>
                </div>
            @endif

            @include('partials.custom-code', ['model' => $page])
        </div>
    @else
        <div class="container-x pt-8">
            <x-breadcrumb :items="[[$page->title, null]]" />
            <article class="mx-auto max-w-3xl">
                <h1 class="font-display text-3xl font-extrabold sm:text-4xl">{{ $page->title }}</h1>
                <div class="mt-6">
                    @if(! empty($blocks))
                        @include('partials.page-blocks', ['blocks' => $blocks])
                    @else
                        <div class="content">{!! $page->content !!}</div>
                    @endif
                </div>
                @include('partials.custom-code', ['model' => $page])
            </article>
        </div>
    @endif
</x-layouts.app>
