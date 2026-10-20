{{-- Pohon kategori bertingkat (maks. 3 level). Variabel: $tree, $parentId, $depth, $category, $activeIds --}}
<ul class="{{ $depth ? 'ml-3 mt-0.5 border-l border-ink-100 pl-2' : '' }} space-y-0.5">
    @foreach($tree[$parentId] ?? [] as $node)
        @php $isActive = $category && $category->id === $node->id; @endphp
        <li>
            <a href="{{ $node->url() }}" @if($isActive) aria-current="page" @endif
               class="block rounded-lg px-2.5 py-1.5 text-sm transition {{ $isActive ? 'bg-brand-50 font-semibold text-brand-700' : (in_array($node->id, $activeIds) ? 'font-semibold text-ink-900' : 'text-ink-600 hover:bg-ink-50 hover:text-ink-900') }}">
                {{ $node->name }}
            </a>
            @if(isset($tree[$node->id]) && $depth < 2)
                @include('partials.category-tree', ['parentId' => $node->id, 'depth' => $depth + 1])
            @endif
        </li>
    @endforeach
</ul>
