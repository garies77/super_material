{{-- Seksi Panduan & Artikel. Disembunyikan bila belum ada artikel yang cocok. --}}
@php
    $articles = $data['articles'];
    $ctaText = $section->setting('cta_text');
    $ctaUrl = $section->setting('cta_url');
@endphp
@if($articles->isNotEmpty())
    <section class="container-x mt-20">
        <div class="mb-8 flex items-end justify-between gap-4">
            @if($section->title)<h2 class="section-title">{{ $section->title }}</h2>@endif
            @if($ctaText && $ctaUrl)
                <a href="{{ $ctaUrl }}" class="shrink-0 text-sm font-semibold text-brand-700 hover:underline">{{ $ctaText }}</a>
            @endif
        </div>
        <div class="grid gap-5 md:grid-cols-3">
            @foreach($articles as $a)<x-article-card :article="$a" />@endforeach
        </div>
    </section>
@endif
