@props(['article'])
<a href="{{ $article->url() }}" class="card card-hover group flex flex-col overflow-hidden">
    <div class="aspect-[16/9] overflow-hidden bg-ink-100">
        @if($article->image)
            <img src="{{ \App\Support\ImageDerivatives::resized($article->imageUrl(), 640) }}" alt="{{ $article->title }}" width="640" height="360" loading="lazy" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
        @else
            <div class="placeholder-art h-full w-full"></div>
        @endif
    </div>
    <div class="flex flex-1 flex-col p-5">
        <time datetime="{{ $article->published_at?->toDateString() }}" class="text-xs font-medium text-brand-700">{{ $article->published_at?->translatedFormat('d F Y') }} · {{ $article->readingMinutes() }} menit baca</time>
        <h3 class="mt-2 font-display text-lg font-semibold leading-snug group-hover:text-brand-700">{{ $article->title }}</h3>
        <p class="mt-2 line-clamp-3 text-sm leading-6 text-ink-600">{{ $article->summary ?: \Illuminate\Support\Str::limit(strip_tags($article->content), 140) }}</p>
    </div>
</a>
