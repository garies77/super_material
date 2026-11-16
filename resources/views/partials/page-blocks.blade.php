{{--
    Render blok page builder untuk halaman publik dan pratinjau. Variabel: $blocks.
    HTML dari editor dan blok HTML khusus selalu disanitasi; gambar tidak pernah melebihi lebar kontainer.
--}}
@php
    $alignText = ['left' => 'text-left', 'center' => 'text-center', 'right' => 'text-right'];
    $alignFlex = ['left' => 'justify-start', 'center' => 'justify-center', 'right' => 'justify-end'];
    $clean = fn ($html) => \App\Support\Api\HtmlSanitizer::clean($html);
@endphp
@php $wide ??= false; $firstGrid = true; @endphp
<div class="page-blocks space-y-6 {{ $wide ? 'page-blocks--wide' : '' }}">
@foreach($blocks as $block)
    @php $d = $block['data'] ?? []; @endphp
    @php $type = $block['type'] ?? null; @endphp
        @if($type === 'heading')
            @php $tag = in_array($d['level'] ?? 'h2', ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true) ? $d['level'] : 'h2'; @endphp
            <{{ $tag }} class="{{ $alignText[$d['align'] ?? 'left'] ?? 'text-left' }}">{{ $d['text'] ?? '' }}</{{ $tag }}>

        @elseif($type === 'text')
            <div class="content">{!! $clean($d['content'] ?? '') !!}</div>

        @elseif($type === 'image' && filled($d['path'] ?? null))
            <figure class="page-image flex flex-col {{ ['left' => 'items-start', 'center' => 'items-center', 'right' => 'items-end'][$d['align'] ?? 'center'] ?? 'items-center' }}">
                <img src="{{ asset('storage/'.ltrim((string) $d['path'], '/')) }}" alt="{{ $d['alt'] ?? '' }}" loading="lazy"
                     class="h-auto max-w-full rounded-xl">
                @if(filled($d['caption'] ?? null))
                    <figcaption class="mt-2 text-center text-sm text-ink-500">{{ $d['caption'] }}</figcaption>
                @endif
            </figure>

        @elseif($type === 'image')
            {{-- Format lama (HTML dari editor teks), dipertahankan untuk blok yang belum dikonversi --}}
            @php
                // Lebar manual (opsional) menimpa hasil tarik; nilai sudah divalidasi angka sehingga aman di atribut style
                $width = (int) ($d['width'] ?? 0);
                $unit = ($d['width_unit'] ?? '%') === 'px' ? 'px' : '%';
                $ratio = preg_match('#^\d+/\d+$#', $d['ratio'] ?? '') ? $d['ratio'] : null;
                $vars = collect([
                    $width > 0 ? '--w:'.min($width, $unit === '%' ? 100 : 2000).$unit : null,
                    $ratio ? '--ratio:'.$ratio : null,
                ])->filter()->implode(';');
            @endphp
            <figure class="page-image content" @if($vars) style="{{ $vars }}" @endif>
                {!! $clean($d['image'] ?? '') !!}
                @if(filled($d['caption'] ?? null))
                    <figcaption class="-mt-3 text-center text-sm text-ink-500">{{ $d['caption'] }}</figcaption>
                @endif
            </figure>

        @elseif($type === 'gallery')
            @php $cols = ['2' => 'sm:grid-cols-2', '3' => 'sm:grid-cols-3', '4' => 'sm:grid-cols-2 lg:grid-cols-4'][(string) ($d['columns'] ?? 3)] ?? 'sm:grid-cols-3'; @endphp
            <div class="grid grid-cols-2 gap-3 {{ $cols }}">
                @foreach((array) ($d['images'] ?? []) as $path)
                    <img src="{{ asset('storage/'.ltrim($path, '/')) }}" alt="{{ $d['alt'] ?? '' }}" loading="lazy"
                         class="aspect-square w-full rounded-xl object-cover">
                @endforeach
            </div>

        @elseif($type === 'button')
            @php $href = \App\Support\LinkTarget::url($d); @endphp
            @if($href)
                <div class="flex {{ $alignFlex[$d['align'] ?? 'left'] ?? 'justify-start' }}">
                    <a href="{{ $href }}" @if(! empty($d['new_tab'])) target="_blank" rel="noopener" @endif
                       class="btn {{ ['primary' => 'btn-primary', 'dark' => 'bg-ink-900 text-white hover:bg-ink-800', 'outline' => 'border-2 border-ink-900 text-ink-900 hover:bg-ink-900 hover:text-white'][$d['style'] ?? 'primary'] ?? 'btn-primary' }}">{{ $d['label'] ?? '' }}</a>
                </div>
            @endif

        @elseif($type === 'products')
            {{-- Grid produk marketplace (App\Support\PageProductGrid). Lebih lebar dari kolom teks halaman. --}}
            @php
                $channel = \App\Support\PageProductGrid::channel($d);
                $gridProducts = $channel ? \App\Support\PageProductGrid::products($d, $channel) : collect();
                $gridCols = ['2' => 'sm:grid-cols-2', '3' => 'sm:grid-cols-2 lg:grid-cols-3', '4' => 'sm:grid-cols-3 lg:grid-cols-4'][(string) ($d['columns'] ?? 4)] ?? 'sm:grid-cols-3 lg:grid-cols-4';
                $buyLabel = filled($d['button_label'] ?? null) ? $d['button_label'] : 'Beli di '.($channel?->name ?? 'marketplace');
                $gridHeading = filled($d['heading'] ?? null) ? $d['heading'] : null;
                $campaign = request()->route('slug') ?: null;
            @endphp
            @if($gridProducts->isNotEmpty())
                {{-- Tata letak landing: grid selebar kontainer; halaman biasa: melebar keluar kolom teks yang sempit --}}
                <section @if($firstGrid) id="produk" @endif class="page-products not-prose scroll-mt-24 {{ $wide ? '' : 'relative left-1/2 w-[min(72rem,calc(100vw-2rem))] -translate-x-1/2' }}" aria-label="{{ $gridHeading ?? 'Daftar produk' }}">
                @php $firstGrid = false; @endphp
                    @if($gridHeading)
                        <h2 class="font-display font-extrabold text-ink-950 {{ $wide ? 'text-xl sm:text-2xl' : 'text-2xl sm:text-3xl' }}">{{ $gridHeading }}</h2>
                    @endif
                    @if(filled($d['intro'] ?? null))
                        <p class="mt-1.5 {{ $wide ? '' : 'max-w-3xl' }} text-sm leading-relaxed text-ink-600 sm:text-base">{{ $d['intro'] }}</p>
                    @endif
                    <ul class="{{ $wide ? 'mt-4' : 'mt-6' }} grid grid-cols-2 gap-3 sm:gap-4 {{ $gridCols }}">
                        @foreach($gridProducts as $product)
                            <li class="card flex flex-col overflow-hidden">
                                <a href="{{ $product->url() }}" class="group block">
                                    <div class="relative aspect-square overflow-hidden bg-ink-100">
                                        @if($product->imageUrl())
                                            <img src="{{ \App\Support\ImageDerivatives::resized($product->imageUrl(), 480) }}" alt="{{ $product->name }}" width="480" height="480" loading="lazy" decoding="async"
                                                 class="h-full w-full object-contain p-2 transition duration-500 group-hover:scale-105">
                                        @else
                                            <div class="placeholder-art grid h-full w-full place-items-center">
                                                <span class="font-display text-5xl font-bold text-brand-500/60">{{ mb_strtoupper(mb_substr($product->name, 0, 1)) }}</span>
                                            </div>
                                        @endif
                                        @if($discount = $product->maxDiscountPercentage())
                                            <span class="absolute right-2.5 top-2.5 rounded bg-rose-500 px-1.5 py-0.5 text-[11px] font-bold text-white shadow-sm">-{{ $discount }}%</span>
                                        @endif
                                    </div>
                                    <div class="px-3 pt-3 sm:px-3.5">
                                        @if($product->category)<span class="line-clamp-1 text-xs font-medium text-brand-700">{{ $product->category->name }}</span>@endif
                                        <h3 class="mt-1 line-clamp-2 font-display text-sm font-semibold leading-snug text-ink-900 group-hover:text-brand-700">{{ $product->name }}</h3>
                                    </div>
                                </a>
                                <div class="mt-auto px-3 pb-3 pt-2 sm:px-3.5 sm:pb-3.5">
                                    @if($d['show_price'] ?? true)
                                        <p class="text-[11px] font-medium uppercase tracking-wide text-ink-400">Harga acuan</p>
                                        <p class="text-sm font-bold text-brand-600">{{ $product->priceLabel() ?? 'Cek harga di '.$channel->name }}</p>
                                    @endif
                                    {{-- Sama dengan tombol beli di halaman produk: warna kanal, lewat /go, data-buy untuk event analitik --}}
                                    <a href="{{ \App\Support\PageProductGrid::buyUrl($product, $channel, $campaign) }}" target="_blank" rel="nofollow sponsored noopener"
                                       data-buy data-product="{{ $product->slug }}" data-channel="{{ $channel->slug }}"
                                       style="background-color: {{ $channel->color ?: '#121826' }}"
                                       class="mt-2.5 flex min-h-10 w-full items-center justify-center gap-2 rounded-xl px-3 text-sm font-semibold text-white shadow-sm transition hover:brightness-110 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">
                                        <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded bg-white/20"><x-channel-logo :channel="$channel->slug" class="h-4 w-4" /></span>
                                        <span class="truncate">{{ $buyLabel }}</span>
                                    </a>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                    {{-- Pengungkapan & kesegaran data (riset: jelaskan harga acuan dan siapa yang memproses transaksi) --}}
                    <p class="mt-3 text-xs leading-relaxed text-ink-500">
                        Harga acuan diperbarui {{ $gridProducts->max('updated_at')?->timezone('Asia/Jakarta')->translatedFormat('j F Y') }}; harga final, stok, dan promo mengikuti {{ $channel->name }}.
                        Tombol beli membuka {{ $channel->name }}, tempat transaksi, pembayaran, dan pengiriman diproses.
                    </p>
                </section>
                @push('jsonld')
                    <x-jsonld :data="\App\Support\PageProductGrid::itemList($gridProducts, $gridHeading ?? 'Produk di '.$channel->name)" />
                @endpush
            @elseif($channel)
                <section class="not-prose rounded-2xl border border-dashed border-ink-200 bg-white p-5 text-sm text-ink-600">
                    @if($gridHeading)<h2 class="font-display text-lg font-bold text-ink-900">{{ $gridHeading }}</h2>@endif
                    <p class="mt-1">Belum ada produk di bagian ini untuk {{ $channel->name }}. <a href="{{ route('product.index') }}" class="font-semibold text-brand-700 underline-offset-2 hover:underline">Lihat semua produk</a> atau tanyakan alternatifnya lewat WhatsApp.</p>
                </section>
            @endif

        @elseif($type === 'highlights')
            @php $points = collect((array) ($d['items'] ?? []))->map(fn ($i) => is_array($i) ? ($i['text'] ?? '') : $i)->filter(fn ($i) => filled($i)); @endphp
            @if($points->isNotEmpty())
                {{-- Ponsel: satu baris yang bisa digeser agar grid produk tetap terlihat di layar pertama --}}
                <ul class="not-prose -mx-4 flex gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none] sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0">
                    @foreach($points as $point)
                        <li class="inline-flex shrink-0 items-center gap-2 whitespace-nowrap rounded-full border border-ink-100 bg-white px-3.5 py-1.5 text-sm font-medium text-ink-800 shadow-sm">
                            <svg viewBox="0 0 20 20" class="h-4 w-4 shrink-0 text-brand-600" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                            {{ $point }}
                        </li>
                    @endforeach
                </ul>
            @endif

        @elseif($type === 'faq')
            @php $faqs = collect((array) ($d['items'] ?? []))->filter(fn ($i) => is_array($i) && filled($i['question'] ?? null) && filled($i['answer'] ?? null)); @endphp
            @if($faqs->isNotEmpty())
                <section class="not-prose">
                    @if(filled($d['heading'] ?? null))
                        <h2 class="font-display text-2xl font-extrabold text-ink-950">{{ $d['heading'] }}</h2>
                    @endif
                    <div class="mt-4 divide-y divide-ink-100 overflow-hidden rounded-2xl border border-ink-100 bg-white">
                        @foreach($faqs as $faq)
                            <details class="group px-4 py-4 sm:px-5" @if($loop->first) open @endif>
                                <summary class="flex cursor-pointer list-none items-start justify-between gap-4 [&::-webkit-details-marker]:hidden">
                                    <h3 class="text-base font-semibold leading-snug text-ink-900">{{ $faq['question'] }}</h3>
                                    <svg viewBox="0 0 20 20" class="mt-0.5 h-5 w-5 shrink-0 text-ink-400 transition group-open:rotate-180" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.2 7.2a1 1 0 0 1 1.4 0L10 10.6l3.4-3.4a1 1 0 1 1 1.4 1.4l-4.1 4.1a1 1 0 0 1-1.4 0L5.2 8.6a1 1 0 0 1 0-1.4Z" clip-rule="evenodd"/></svg>
                                </summary>
                                <p class="mt-2 whitespace-pre-line leading-relaxed text-ink-600">{{ $faq['answer'] }}</p>
                            </details>
                        @endforeach
                    </div>
                </section>
            @endif

        @elseif($type === 'list')
            @php $items = collect((array) ($d['items'] ?? []))->map(fn ($i) => is_array($i) ? ($i['text'] ?? '') : $i)->filter(fn ($i) => filled($i))->all(); @endphp
            <div class="content">
                @if(($d['style'] ?? 'bullet') === 'number')
                    <ol>@foreach($items as $item)<li>{{ $item }}</li>@endforeach</ol>
                @else
                    <ul>@foreach($items as $item)<li>{{ $item }}</li>@endforeach</ul>
                @endif
            </div>

        @elseif($type === 'divider')
            <hr class="border-ink-200 {{ ['sm' => 'my-2', 'md' => 'my-6', 'lg' => 'my-12'][$d['spacing'] ?? 'md'] ?? 'my-6' }}">

        @elseif($type === 'spacer')
            <div aria-hidden="true" style="height:{{ max(4, min(400, (int) ($d['height'] ?? 40))) }}px"></div>

        @elseif($type === 'embed')
            @if($youtubeId = \App\Support\YouTube::videoId($d['url'] ?? null))
                <div class="aspect-video overflow-hidden rounded-xl bg-ink-100">
                    <iframe src="{{ \App\Support\YouTube::embedUrl($youtubeId) }}" title="{{ $d['title'] ?? 'Video' }}" loading="lazy"
                            class="h-full w-full border-0" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture" allowfullscreen
                            referrerpolicy="strict-origin-when-cross-origin"></iframe>
                </div>
            @endif

        @elseif($type === 'form')
            <x-custom-form :id="$d['form_id'] ?? 0" :show-title="(bool) ($d['show_title'] ?? true)" class="not-prose rounded-2xl border border-ink-100 bg-white p-5 sm:p-8" />

        @elseif($type === 'html')
            <div class="custom-html">{!! \App\Support\Api\HtmlSanitizer::clean($d['html'] ?? '') !!}</div>
    @endif
@endforeach
</div>
