{{--
    Banner promosi antar-seksi: gambar kustom (desktop + mobile) atau kampanye aktif dari menu Kampanye.
    Kampanye memakai slot yang sama dengan banner atas halaman sehingga tampilan & klik tercatat di laporan.
--}}
@php
    $url = $section->setting('url');
    $external = $url && preg_match('#^https?://#', $url) && ! str_starts_with($url, url('/'));
@endphp
@if($section->setting('source') === 'campaign')
    @if($data['ad'])
        <div class="container-x mt-20">
            @include('partials.promo-slot', ['ad' => $data['ad'], 'slot' => 'page_top', 'article' => null, 'preview' => false, 'class' => 'my-0'])
        </div>
    @endif
@elseif($data['image'])
    <section class="container-x mt-20" aria-label="{{ $section->title ?: 'Promo' }}">
        @if($url)<a href="{{ $url }}" @if($external) target="_blank" rel="nofollow noopener" @endif class="group block overflow-hidden rounded-3xl">@else<div class="overflow-hidden rounded-3xl">@endif
            <picture>
                @if($data['image_mobile'])
                    <source media="(max-width: 639px)" srcset="{{ $data['image_mobile'] }}">
                @endif
                <img src="{{ $data['image'] }}" alt="{{ $section->setting('alt') ?: $section->title ?: 'Promo' }}" loading="lazy"
                     width="1200" height="300" class="h-auto w-full object-cover transition duration-500 group-hover:scale-[1.01]">
            </picture>
        @if($url)</a>@else</div>@endif
    </section>
@endif
