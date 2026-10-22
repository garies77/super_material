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
<nav aria-label="Breadcrumb" class="mb-6 text-sm text-ink-400">
    <ol class="flex flex-wrap items-center gap-1.5">
        @foreach($trail as $i => $t)
            <li class="flex items-center gap-1.5">
                @if($i > 0)<span aria-hidden="true">/</span>@endif
                @if(! $loop->last && ! empty($t[1]))
                    <a href="{{ $t[1] }}" class="transition hover:text-brand-700">{{ $t[0] }}</a>
                @else
                    <span class="font-medium text-ink-600" aria-current="page">{{ $t[0] }}</span>
                @endif
            </li>
        @endforeach
    </ol>
</nav>
