@props(['product'])
<a href="{{ $product->url() }}" class="card card-hover group flex flex-col overflow-hidden">
    <div class="relative aspect-square overflow-hidden bg-ink-100">
        @if($product->imageUrl())
            <img src="{{ \App\Support\ImageDerivatives::resized($product->imageUrl(), 480) }}" alt="{{ $product->name }}" width="480" height="480" loading="lazy"
                 class="h-full w-full object-contain p-2 transition duration-500 group-hover:scale-105">
        @else
            <div class="placeholder-art grid h-full w-full place-items-center">
                <span class="font-display text-5xl font-bold text-brand-500/60">{{ mb_strtoupper(mb_substr($product->name, 0, 1)) }}</span>
            </div>
        @endif
        @if($product->badge)
            <span class="absolute left-2.5 top-2.5 rounded bg-brand-600 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-white shadow-sm">{{ $product->badge }}</span>
        @elseif($product->brand)
            <span class="absolute left-2.5 top-2.5 rounded-full bg-white/90 px-2 py-0.5 text-[11px] font-semibold text-ink-800 backdrop-blur shadow-sm">{{ $product->brand->name }}</span>
        @endif

        @if($discount = $product->maxDiscountPercentage())
            <span class="absolute right-2.5 top-2.5 rounded bg-rose-500 px-1.5 py-0.5 text-[11px] font-bold text-white shadow-sm">-{{ $discount }}%</span>
        @endif
    </div>
    <div class="flex flex-1 flex-col p-3.5">
        @if($product->category)<span class="text-xs font-medium text-brand-700 line-clamp-1">{{ $product->category->name }}</span>@endif
        <h3 class="mt-1 line-clamp-2 font-display text-sm font-semibold leading-snug text-ink-900 group-hover:text-brand-700">{{ $product->name }}</h3>
        
        <div class="mt-auto pt-2 flex items-baseline justify-between gap-1">
            <p class="text-sm font-bold text-brand-600">{{ $product->priceLabel() ?? 'Lihat harga di toko' }}</p>
            @if($product->hasRating())
                <span class="text-[11px] text-ink-500 flex items-center gap-0.5 shrink-0">
                    <svg viewBox="0 0 20 20" class="w-3 h-3 fill-amber-400"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    {{ $product->formattedRatingValue() }}
                </span>
            @endif
        </div>
    </div>
</a>
