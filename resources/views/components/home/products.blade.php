{{-- Seksi Produk. Boleh lebih dari satu di beranda; sumber produk diatur di HomepageService. --}}
@php
    $products = $data['products'];
    $ctaText = $section->setting('cta_text');
    $ctaUrl = $section->setting('cta_url');
@endphp
<section class="container-x mt-20">
    <div class="mb-8 flex items-end justify-between gap-4">
        @if($section->title)<h2 class="section-title">{{ $section->title }}</h2>@endif
        @if($ctaText && $ctaUrl)
            <a href="{{ $ctaUrl }}" class="shrink-0 text-sm font-semibold text-brand-700 hover:underline">{{ $ctaText }}</a>
        @endif
    </div>
    @if($products->isEmpty())
        <p class="card p-10 text-center text-ink-400">Produk akan segera hadir.</p>
    @else
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            @foreach($products as $p)<x-product-card :product="$p" />@endforeach
        </div>
    @endif
</section>
