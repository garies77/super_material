@props(['product', 'channels', 'compact' => false])
@php
    $utm = request()->only(['utm_source', 'utm_medium', 'utm_campaign']);
@endphp
<div class="{{ $compact ? 'flex gap-2' : 'flex flex-col gap-2.5' }}">
    @foreach($channels as $channel)
        <a href="{{ route('go', ['product' => $product->slug, 'channel' => $channel->slug] + $utm) }}"
           rel="nofollow sponsored noopener" target="_blank"
           data-buy data-product="{{ $product->slug }}" data-channel="{{ $channel->slug }}"
           id="buy-{{ $compact ? 'sticky-' : '' }}{{ $channel->slug }}"
           style="background-color: {{ $channel->color }}"
           class="btn {{ $compact ? 'flex-1 px-3 py-3' : 'w-full justify-between py-3.5' }} text-white shadow-md hover:-translate-y-0.5 hover:brightness-110">
            <div class="flex items-center gap-2.5">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-white/20 p-0.5 shrink-0">
                    <x-channel-logo :channel="$channel->slug" class="h-5 w-5" />
                </span>
                <span>{{ $channel->isWhatsapp() ? 'Tanya via ' : 'Beli di ' }}{{ $channel->name }}</span>
            </div>
            @unless($compact)
                <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M7 17 17 7M8 7h9v9"/></svg>
            @endunless
        </a>
    @endforeach
</div>
