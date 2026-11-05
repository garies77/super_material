@props(['video', 'primary' => false])
@php
    /** @var \App\Support\Seo\VideoData $video */
    $clips = \App\Support\Seo\VideoSchema::clips($video);
    $duration = \App\Support\Seo\VideoDuration::format($video->durationSeconds);
@endphp
{{-- Player tampil apa adanya agar Google melihat video yang sama dengan VideoObject di JSON-LD --}}
<figure {{ $attributes->class(['my-6']) }} data-video-player>
    <div class="aspect-video overflow-hidden rounded-2xl bg-ink-950 shadow-sm">
        @if($video->youtubeId)
            <iframe src="{{ $video->embedUrl() }}" title="{{ $video->title }}"
                    @unless($primary) loading="lazy" @endunless
                    data-video-frame data-embed="{{ $video->embedUrl() }}"
                    class="h-full w-full border-0"
                    allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen
                    referrerpolicy="strict-origin-when-cross-origin"></iframe>
        @elseif($video->fileUrl)
            <video src="{{ $video->fileUrl }}" @if($video->thumbnailUrl) poster="{{ $video->thumbnailUrl }}" @endif
                   controls preload="metadata" playsinline data-video-frame class="h-full w-full"></video>
        @endif
    </div>
    <figcaption class="mt-3">
        <p class="font-display text-base font-bold leading-snug text-ink-950">
            {{ $video->title }}
            @if($duration)<span class="ml-1 text-xs font-semibold text-ink-500">({{ $duration }})</span>@endif
        </p>
        @if($video->description)
            <p class="mt-1 line-clamp-3 whitespace-pre-line text-sm leading-relaxed text-ink-600">{{ $video->description }}</p>
        @endif
        @if($clips)
            <ol class="mt-3 flex flex-wrap gap-2" aria-label="Momen penting video">
                @foreach($clips as $clip)
                    <li>
                        <a href="{{ $clip['url'] }}" data-video-seek="{{ $clip['startOffset'] }}"
                           class="inline-flex items-center gap-1.5 rounded-full border border-ink-200 bg-white px-3 py-1 text-xs font-semibold text-ink-700 transition hover:border-brand-300 hover:text-brand-700">
                            <span class="tabular-nums text-brand-600">{{ \App\Support\Seo\VideoDuration::format($clip['startOffset']) }}</span>
                            {{ $clip['name'] }}
                        </a>
                    </li>
                @endforeach
            </ol>
        @endif
    </figcaption>
</figure>
