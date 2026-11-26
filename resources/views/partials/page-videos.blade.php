{{--
    Blok "Video" page builder. Variabel: $videos (VideoEmbed::fromBlock), $layout ('1'–'4' atau 'scroll').
    Tata letak 1 kolom (dan blok berisi satu video) tampil seperti blok embed lama: video ditumpuk di tengah.
    Grid paling banyak 2 kolom di HP; kalau ada video mendatar (YouTube biasa), HP memakai 1 kolom agar tidak terlalu kecil.
--}}
@php
    $allPortrait = collect($videos)->every(fn ($item) => $item['video']['portrait']);
    $isStacked = $layout === '1' || count($videos) === 1 || ! in_array($layout, ['2', '3', '4', 'scroll'], true);
    $gridColumns = $allPortrait
        ? ['2' => 'grid-cols-2', '3' => 'grid-cols-2 sm:grid-cols-3', '4' => 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-4']
        : ['2' => 'grid-cols-1 sm:grid-cols-2', '3' => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3', '4' => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4'];
    $allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share';
@endphp

@if($videos !== [])
    @if($isStacked)
        <div class="page-videos space-y-6">
            @foreach($videos as $item)
                @php $video = $item['video']; @endphp
                {{-- Instagram menambah header dan keterangan di dalam iframe, jadi butuh tinggi tetap, bukan rasio --}}
                <div @class([
                    'overflow-hidden rounded-xl bg-ink-100',
                    'aspect-video' => ! $video['portrait'],
                    'mx-auto w-full max-w-[400px] h-[680px]' => $video['provider'] === 'instagram',
                    'mx-auto w-full max-w-[340px] aspect-[9/16]' => $video['portrait'] && $video['provider'] !== 'instagram',
                ])>
                    <iframe src="{{ $video['src'] }}" title="{{ $item['title'] }}" loading="lazy" class="h-full w-full border-0"
                            allow="{{ $allow }}" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
                </div>
            @endforeach
        </div>
    @else
        <div @class([
            'page-videos',
            'grid items-start gap-3 sm:gap-4 '.($gridColumns[$layout] ?? '') => $layout !== 'scroll',
            'flex snap-x snap-mandatory gap-3 overflow-x-auto pb-3 sm:gap-4' => $layout === 'scroll',
        ])>
            @foreach($videos as $item)
                @php $video = $item['video']; @endphp
                {{-- Di grid, Instagram diberi rasio lebih tinggi dari 9:16 karena iframe-nya memuat header dan keterangan --}}
                <div @class([
                    'overflow-hidden rounded-xl bg-ink-100',
                    'aspect-video' => ! $video['portrait'],
                    'aspect-[9/16]' => $video['portrait'] && $video['provider'] !== 'instagram',
                    'aspect-[9/20]' => $video['provider'] === 'instagram',
                    'w-[70%] shrink-0 snap-start sm:w-64' => $layout === 'scroll' && $video['portrait'],
                    'w-[85%] shrink-0 snap-start sm:w-96' => $layout === 'scroll' && ! $video['portrait'],
                ])>
                    <iframe src="{{ $video['src'] }}" title="{{ $item['title'] }}" loading="lazy" class="h-full w-full border-0"
                            allow="{{ $allow }}" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
                </div>
            @endforeach
        </div>
    @endif
@endif
