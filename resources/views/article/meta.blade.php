{{-- Metadata redaksi: penulis, tanggal terbit/perbarui (WIB), waktu baca; tiap bagian dapat disembunyikan. --}}
@php
    $metaParts = array_values(array_filter([
        $show['author'] ? 'author' : null,
        $show['published'] ? 'published' : null,
        $show['updated'] && $article->formattedUpdatedAt() ? 'updated' : null,
        $show['reading_time'] ? 'reading' : null,
    ]));
@endphp
@if($metaParts)
    <div class="mt-4 flex flex-wrap items-center gap-x-3 gap-y-1.5 text-xs sm:text-sm text-ink-500 border-b border-ink-100 pb-4">
        @foreach($metaParts as $i => $part)
            @if($i > 0)<span class="text-ink-300">·</span>@endif
            @if($part === 'author')
                <span class="font-bold text-ink-900">
                    @if($article->author)
                        <a href="{{ route('author.show', $article->author->slug) }}" class="transition hover:text-brand-600 hover:underline">{{ $article->author->name }}</a>
                    @else
                        Redaksi Supermaterial
                    @endif
                </span>
            @elseif($part === 'published')
                <time datetime="{{ $article->published_at?->toIso8601String() }}">{{ $article->formattedPublishedAt() }}</time>
            @elseif($part === 'updated')
                <span class="text-ink-400">Diperbarui: {{ $article->formattedUpdatedAt() }}</span>
            @else
                <span class="inline-flex items-center gap-1 text-ink-500 font-medium">
                    <svg class="h-3.5 w-3.5 text-ink-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    {{ $article->readingMinutes() }} menit baca
                </span>
            @endif
        @endforeach
    </div>
@endif
