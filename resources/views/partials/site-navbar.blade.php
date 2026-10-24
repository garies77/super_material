{{-- Menu navigasi dari Navbar Builder. Variabel: $items (SiteLayout::navbar()). --}}
@php
    $linkClass = fn (bool $active) => 'rounded-lg px-2.5 py-2 text-sm font-semibold transition sm:px-3.5 '.($active ? 'bg-brand-50 text-brand-700' : 'text-ink-600 hover:bg-ink-50 hover:text-ink-900');
    $isActive = fn (array $item) => \App\Support\SiteLayout::isCurrent($item['href'] ?? null)
        || collect($item['children'] ?? [])->contains(fn ($c) => \App\Support\SiteLayout::isCurrent($c['href'] ?? null));
    $target = fn (array $item) => ! empty($item['new_tab']) ? 'target="_blank" rel="noopener"' : '';
    // Sedikit menu tanpa submenu tetap tampil sebaris di mobile seperti sebelumnya
    $compact = count($items) <= 2 && collect($items)->every(fn ($i) => empty($i['children']));
@endphp

<div class="{{ $compact ? 'flex' : 'hidden md:flex' }} items-center gap-0.5 sm:gap-1">
    @foreach($items as $item)
        @if(! empty($item['children']))
            <div class="group relative">
                <button type="button" aria-haspopup="true" class="{{ $linkClass($isActive($item)) }} inline-flex items-center gap-1">
                    {{ $item['label'] }}
                    <svg viewBox="0 0 20 20" class="h-4 w-4" fill="currentColor" aria-hidden="true"><path d="M5.3 7.3a1 1 0 0 1 1.4 0L10 10.6l3.3-3.3a1 1 0 1 1 1.4 1.4l-4 4a1 1 0 0 1-1.4 0l-4-4a1 1 0 0 1 0-1.4Z"/></svg>
                </button>
                <div class="invisible absolute left-0 top-full z-50 min-w-48 pt-2 opacity-0 transition group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100">
                    <div class="rounded-xl border border-ink-100 bg-white p-1.5 shadow-lg">
                        @if($item['href'])
                            <a href="{{ $item['href'] }}" {!! $target($item) !!} class="block rounded-lg px-3 py-2 text-sm font-semibold text-ink-800 hover:bg-ink-50">{{ $item['label'] }}</a>
                        @endif
                        @foreach($item['children'] as $child)
                            <a href="{{ $child['href'] }}" {!! $target($child) !!} class="block rounded-lg px-3 py-2 text-sm text-ink-700 hover:bg-ink-50 hover:text-ink-900">{{ $child['label'] }}</a>
                        @endforeach
                    </div>
                </div>
            </div>
        @else
            <a href="{{ $item['href'] }}" {!! $target($item) !!} @if($isActive($item)) aria-current="page" @endif class="{{ $linkClass($isActive($item)) }}">{{ $item['label'] }}</a>
        @endif
    @endforeach
</div>

@unless($compact)
    {{-- Mobile: menu lipat tanpa JavaScript --}}
    <details class="relative md:hidden">
        <summary class="grid h-11 cursor-pointer list-none place-items-center rounded-xl border border-ink-200 bg-white px-3 text-sm font-semibold text-ink-800 [&::-webkit-details-marker]:hidden">Menu</summary>
        <div class="absolute right-0 top-full z-50 mt-2 w-64 rounded-xl border border-ink-100 bg-white p-2 shadow-lg">
            @foreach($items as $item)
                @if($item['href'])
                    <a href="{{ $item['href'] }}" {!! $target($item) !!} class="block rounded-lg px-3 py-2 text-sm font-semibold {{ $isActive($item) ? 'bg-brand-50 text-brand-700' : 'text-ink-800 hover:bg-ink-50' }}">{{ $item['label'] }}</a>
                @else
                    <p class="px-3 pb-1 pt-2 text-xs font-bold uppercase tracking-wider text-ink-400">{{ $item['label'] }}</p>
                @endif
                @foreach($item['children'] ?? [] as $child)
                    <a href="{{ $child['href'] }}" {!! $target($child) !!} class="block rounded-lg py-2 pl-6 pr-3 text-sm text-ink-700 hover:bg-ink-50">{{ $child['label'] }}</a>
                @endforeach
            @endforeach
        </div>
    </details>
@endunless
