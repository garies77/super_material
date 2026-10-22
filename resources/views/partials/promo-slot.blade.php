{{--
    Slot iklan internal (PRD 8.8). Variabel: $ad, $slot, $article, $preview, $class (opsional).
    Tinggi materi tetap agar tidak terjadi pergeseran tata letak (CLS); data-nosnippet agar
    isi iklan tidak dipakai sebagai cuplikan (PRD 9.11.4).
--}}
@php
    $preview = $preview ?? false;
    $destination = $ad->destinationUrl();
    $external = $ad->isExternal();
    $href = $destination
        ? ($external && ! $preview ? route('ad.click', ['ad' => $ad->id, 's' => $slot, 'a' => $article->id]) : $destination)
        : null;
    $linkAttrs = $external
        ? 'target="_blank" rel="sponsored nofollow"'
        : ($preview ? '' : 'data-promo-internal');
    $card = $ad->format === 'product' ? $ad->productCard() : null;
    $boxHeight = $slot === 'sidebar' ? 'h-[300px]' : 'h-[250px]';
@endphp
<aside data-nosnippet
       class="sm-promo not-italic {{ $class ?? 'my-8' }}"
       aria-label="Konten promosi"
       data-promo-slot="{{ $slot }}"
       @unless($preview)
           data-promo-id="{{ $ad->id }}"
           data-promo-event="{{ route('ad.event', $ad) }}"
           data-promo-article="{{ $article->id }}"
           data-promo-token="{{ csrf_token() }}"
       @endunless>
    <div class="mb-1.5 flex items-center justify-between">
        <span class="text-[10px] font-bold uppercase tracking-[0.2em] text-ink-400">{{ $ad->label }}</span>
        @if($preview)
            <span class="text-[10px] font-semibold text-brand-700">Pratinjau · {{ \App\Models\Ad::SLOTS[$slot] ?? $slot }}</span>
        @endif
    </div>

    <div class="{{ $boxHeight }} overflow-hidden rounded-xl border border-ink-100 bg-ink-50">
        @switch($ad->format)
            @case('image')
                @if($href && $ad->imageUrl())
                    <a href="{{ $href }}" {!! $linkAttrs !!} class="block h-full w-full">
                        <picture>
                            @if($ad->mobileImageUrl())
                                <source media="(max-width: 639px)" srcset="{{ $ad->mobileImageUrl() }}">
                            @endif
                            <img src="{{ $ad->imageUrl() }}" alt="{{ $ad->image_alt ?: $ad->title ?: $ad->name }}"
                                 width="728" height="250" @if($slot !== 'top') loading="lazy" @endif
                                 class="h-full w-full object-contain">
                        </picture>
                    </a>
                @endif
                @break

            @case('product')
                @if($card && $href)
                    <a href="{{ $href }}" {!! $linkAttrs !!} class="flex h-full w-full items-stretch bg-white {{ $slot === 'sidebar' ? 'flex-col' : '' }}">
                        <div class="{{ $slot === 'sidebar' ? 'h-36 w-full' : 'h-full w-2/5' }} shrink-0 bg-ink-100">
                            @if($card['image'])
                                <img src="{{ $card['image'] }}" alt="{{ $card['name'] }}" width="300" height="250" loading="lazy" class="h-full w-full object-cover">
                            @endif
                        </div>
                        <div class="flex min-w-0 flex-1 flex-col justify-center gap-1.5 p-4">
                            <p class="line-clamp-2 font-display text-base font-bold leading-snug text-ink-950">{{ $card['name'] }}</p>
                            @if($card['price'])
                                <p class="text-lg font-black text-brand-700">{{ $card['price'] }}</p>
                            @endif
                            @if($card['compare'])
                                <p class="text-xs text-ink-400 line-through">{{ $card['compare'] }}</p>
                            @endif
                            <span class="mt-1 inline-flex w-fit rounded-full bg-ink-900 px-4 py-1.5 text-xs font-bold text-white">{{ $ad->cta_label ?: 'Lihat produk' }}</span>
                        </div>
                    </a>
                @endif
                @break

            @case('text')
                <div class="flex h-full flex-col items-center justify-center gap-3 bg-white p-6 text-center">
                    @if($ad->title)
                        <p class="line-clamp-2 font-display text-xl font-black leading-tight text-ink-950">{{ $ad->title }}</p>
                    @endif
                    @if($ad->body)
                        <p class="line-clamp-3 text-sm leading-relaxed text-ink-600">{{ $ad->body }}</p>
                    @endif
                    @if($href)
                        <a href="{{ $href }}" {!! $linkAttrs !!} class="inline-flex rounded-full bg-brand-500 px-5 py-2 text-sm font-bold text-ink-950 transition hover:bg-brand-400">{{ $ad->cta_label ?: 'Selengkapnya' }}</a>
                    @endif
                </div>
                @break

            @case('html')
                <div class="h-full w-full">{!! $ad->safeHtml() !!}</div>
                @break
        @endswitch
    </div>

    @if($ad->show_scroll_hint && str_starts_with($slot, 'in_content'))
        <p class="mt-2 text-center text-[11px] font-medium uppercase tracking-wider text-ink-400">Scroll untuk melanjutkan</p>
    @endif
</aside>
