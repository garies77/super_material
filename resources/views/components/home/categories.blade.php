{{-- Seksi Kategori. Kartu menumpuk ke hero ($overlap) hanya bila tepat setelah seksi hero. --}}
@php
    $categories = $data['categories'];
    $cols = (int) $section->setting('columns', 4) === 3 ? 'lg:grid-cols-3' : 'lg:grid-cols-4';
@endphp
@if($categories->isNotEmpty())
    <section @class(['container-x relative', '-mt-10' => $overlap, 'mt-20' => ! $overlap]) aria-label="{{ $section->title ?: 'Kategori' }}">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 {{ $cols }}">
            @foreach($categories as $c)
                <a href="{{ $c->url() }}" class="card card-hover group flex items-center gap-4 p-4">
                    @if($c->image)
                        <img src="{{ preg_match('#^https?://#', $c->image) ? $c->image : asset('storage/'.ltrim($c->image, '/')) }}" alt="" loading="lazy"
                             width="48" height="48" class="h-12 w-12 shrink-0 rounded-xl bg-brand-50 object-cover ring-1 ring-ink-100 transition group-hover:ring-brand-500">
                    @else
                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-brand-50 font-display text-xl font-bold text-brand-600 transition group-hover:bg-brand-500 group-hover:text-white">{{ mb_strtoupper(mb_substr($c->name, 0, 1)) }}</span>
                    @endif
                    <span class="min-w-0">
                        <span class="block truncate font-display font-semibold text-ink-900">{{ $c->name }}</span>
                        <span class="text-xs text-ink-400">{{ $c->products_count }} produk</span>
                    </span>
                </a>
            @endforeach
        </div>
    </section>
@endif
