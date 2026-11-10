@props(['items'])
{{-- $items: [[nama, url|null], ...]; "Beranda" ditambahkan otomatis --}}
@php
    $trail = array_merge([['Beranda', route('home')]], $items);
    $count = count($trail);
    $ld = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => collect($trail)->values()->map(function ($t, $i) use ($count) {
            $isLast = ($i === $count - 1);
            return array_filter([
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $t[0],
                'item' => ! $isLast ? ($t[1] ?? url()->current()) : ($t[1] ?: null),
            ]);
        })->all(),
    ];
@endphp
@push('jsonld')<x-jsonld :data="$ld" />@endpush
<nav aria-label="Breadcrumb" class="mb-4 text-sm text-ink-400 sm:mb-6">
    <ol class="no-scrollbar flex items-center gap-1.5 overflow-x-auto whitespace-nowrap sm:flex-wrap sm:whitespace-normal">
        @foreach($trail as $i => $t)
            {{-- Item terakhir boleh menyusut agar judul panjang berakhir dengan "…" di layar sempit --}}
            <li @class(['flex items-center gap-1.5', 'min-w-0 shrink' => $loop->last, 'shrink-0 sm:min-w-0 sm:shrink' => ! $loop->last])>
                @if($i > 0)<span aria-hidden="true">/</span>@endif
                @if(! $loop->last && ! empty($t[1]))
                    <a href="{{ $t[1] }}" class="transition hover:text-brand-700">{{ $t[0] }}</a>
                @else
                    <span class="max-w-[70vw] truncate font-medium text-ink-600 sm:max-w-md" aria-current="page">{{ $t[0] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
