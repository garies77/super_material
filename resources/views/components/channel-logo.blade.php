@props(['channel', 'class' => 'h-5 w-5'])
@php
    $slug = is_string($channel) ? \Illuminate\Support\Str::slug($channel) : ($channel->slug ?? \Illuminate\Support\Str::slug($channel->name ?? ''));
@endphp

@if($slug === 'shopee')
    {{-- Official Shopee Logo --}}
    <svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" class="{{ $class }}" aria-label="Shopee">
        <rect width="32" height="32" rx="8" fill="#EE4D2D"/>
        <!-- Shopee Handle -->
        <path d="M16 5.5c-2.48 0-4.5 2.02-4.5 4.5v1.2h1.8V10c0-1.49 1.21-2.7 2.7-2.7s2.7 1.21 2.7 2.7v1.2h1.8V10c0-2.48-2.02-4.5-4.5-4.5z" fill="#FFFFFF"/>
        <!-- Shopee Bag Body -->
        <path d="M8 10.2h16l1.4 15.3c.07.77-.54 1.5-1.32 1.5H7.92c-.78 0-1.39-.73-1.32-1.5L8 10.2z" fill="#EE4D2D" stroke="#FFFFFF" stroke-width="1.2"/>
        <!-- Shopee 'S' Character -->
        <path d="M16.6 14.8c-1.8 0-2.6.7-2.6 1.5 0 1.8 4.1 1.4 4.1 3.9 0 1.6-1.3 2.6-3.1 2.6-1.7 0-2.9-.8-3.2-2.1l1.5-.5c.2.8.8 1.2 1.7 1.2 1.1 0 1.6-.6 1.6-1.2 0-1.9-4.1-1.4-4.1-3.9 0-1.6 1.3-2.6 3.1-2.6 1.5 0 2.5.6 2.9 1.8l-1.5.6c-.2-.7-.7-1.3-1.6-1.3z" fill="#FFFFFF"/>
    </svg>
@elseif($slug === 'tokopedia')
    {{-- Official Tokopedia Owl Logo --}}
    <svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" class="{{ $class }}" aria-label="Tokopedia">
        <rect width="32" height="32" rx="8" fill="#03AC0E"/>
        <!-- Owl Tuft Forehead -->
        <path d="M8 8.5l3.2 3.6c1.5-.7 3.1-1.1 4.8-1.1s3.3.4 4.8 1.1L24 8.5C21.6 6.3 18.9 5 16 5s-5.6 1.3-8 3.5z" fill="#028A0B"/>
        <!-- Left Eye Goggle -->
        <circle cx="11.5" cy="16" r="4.5" fill="#FFFFFF"/>
        <circle cx="11.5" cy="16" r="2.3" fill="#03AC0E"/>
        <circle cx="12.3" cy="15.2" r="0.8" fill="#FFFFFF"/>
        <!-- Right Eye Goggle -->
        <circle cx="20.5" cy="16" r="4.5" fill="#FFFFFF"/>
        <circle cx="20.5" cy="16" r="2.3" fill="#03AC0E"/>
        <circle cx="21.3" cy="15.2" r="0.8" fill="#FFFFFF"/>
        <!-- Orange Beak -->
        <polygon points="16,18.5 14.2,22 17.8,22" fill="#FCA311"/>
    </svg>
@elseif($slug === 'tiktok-shop' || $slug === 'tiktok')
    {{-- Official TikTok Shop Logo --}}
    <svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" class="{{ $class }}" aria-label="TikTok Shop">
        <rect width="32" height="32" rx="8" fill="#111111"/>
        <!-- Cyan Shadow Note -->
        <path d="M22.5 12.2c-1.6-.2-2.9-1.2-3.4-2.7h-3.1v10.9c0 1.8-1.4 3.2-3.2 3.2s-3.2-1.4-3.2-3.2 1.4-3.2 3.2-3.2c.4 0 .8.1 1.1.2v-3.2c-.4 0-.7-.1-1.1-.1-3.5 0-6.4 2.9-6.4 6.4s2.9 6.4 6.4 6.4 6.4-2.9 6.4-6.4v-6.9c1.4 1 3.1 1.6 5 1.6v-3c-.6 0-1.2 0-1.7 0z" fill="#00F2FE" transform="translate(-0.8, -0.8)"/>
        <!-- Red Shadow Note -->
        <path d="M22.5 12.2c-1.6-.2-2.9-1.2-3.4-2.7h-3.1v10.9c0 1.8-1.4 3.2-3.2 3.2s-3.2-1.4-3.2-3.2 1.4-3.2 3.2-3.2c.4 0 .8.1 1.1.2v-3.2c-.4 0-.7-.1-1.1-.1-3.5 0-6.4 2.9-6.4 6.4s2.9 6.4 6.4 6.4 6.4-2.9 6.4-6.4v-6.9c1.4 1 3.1 1.6 5 1.6v-3c-.6 0-1.2 0-1.7 0z" fill="#FE2C55" transform="translate(0.8, 0.8)"/>
        <!-- White Core Note -->
        <path d="M22.5 12.2c-1.6-.2-2.9-1.2-3.4-2.7h-3.1v10.9c0 1.8-1.4 3.2-3.2 3.2s-3.2-1.4-3.2-3.2 1.4-3.2 3.2-3.2c.4 0 .8.1 1.1.2v-3.2c-.4 0-.7-.1-1.1-.1-3.5 0-6.4 2.9-6.4 6.4s2.9 6.4 6.4 6.4 6.4-2.9 6.4-6.4v-6.9c1.4 1 3.1 1.6 5 1.6v-3c-.6 0-1.2 0-1.7 0z" fill="#FFFFFF"/>
    </svg>
@elseif($slug === 'lazada')
    {{-- Official Lazada Logo --}}
    <svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" class="{{ $class }}" aria-label="Lazada">
        <rect width="32" height="32" rx="8" fill="#0F146D"/>
        <!-- Lazada 3D Origami Heart -->
        <path d="M16 6.8c-2.4-2.4-6.2-2.4-8.6 0-2.4 2.4-2.4 6.3 0 8.7L16 24.2l8.6-8.7c2.4-2.4 2.4-6.3 0-8.7-2.4-2.4-6.2-2.4-8.6 0z" fill="#F57224"/>
        <path d="M16 10.8c-1.4-1.4-3.7-1.4-5.1 0-1.4 1.4-1.4 3.7 0 5.1l5.1 5.2 5.1-5.2c1.4-1.4 1.4-3.7 0-5.1-1.4-1.4-3.7-1.4-5.1 0z" fill="#0F146D"/>
        <path d="M16 23.5l-7.2-7.3c-1.9-1.9-1.9-5 0-6.9 1.1-1.1 2.6-1.7 4.1-1.5 1 .2 2 .7 2.9 1.6l-.7 3-3.2-3c-.9-.9-2.4-.9-3.3 0-.9.9-.9 2.4 0 3.3L16 20.2l7.4-7.5c.9-.9.9-2.4 0-3.3-.9-.9-2.4-.9-3.3 0l-.7 2.9-.7-3c.9-.9 1.9-1.4 2.9-1.6 1.5-.2 3 .4 4.1 1.5 1.9 1.9 1.9 5 0 6.9L16 23.5z" fill="#FFFFFF"/>
    </svg>
@elseif($slug === 'whatsapp')
    {{-- Official WhatsApp Logo --}}
    <svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" class="{{ $class }}" aria-label="WhatsApp">
        <rect width="32" height="32" rx="8" fill="#25D366"/>
        <!-- WhatsApp Speech Bubble & Phone Handset -->
        <path d="M16 5.5c-5.8 0-10.5 4.7-10.5 10.5 0 1.9.5 3.6 1.4 5.2L5.5 26.5l4.9-1.3c1.5.8 3.3 1.3 5.1 1.3 5.8 0 10.5-4.7 10.5-10.5S21.8 5.5 16 5.5z" fill="#25D366" stroke="#FFFFFF" stroke-width="1.2"/>
        <path d="M21.7 18.5c-.2-.1-1.3-.7-1.5-.7-.2-.1-.4-.1-.5.1-.2.2-.6.7-.7.9-.1.2-.3.2-.5.1-.2-.1-.9-.3-1.8-1.1-.7-.6-1.1-1.3-1.3-1.6-.1-.2 0-.4.1-.5.1-.1.2-.3.3-.4.1-.1.2-.2.2-.4.1-.1 0-.3 0-.4-.1-.1-.5-1.2-.7-1.7-.2-.4-.4-.4-.5-.4h-.4c-.2 0-.4.1-.6.3-.2.2-.8.8-.8 1.9s.8 2.2.9 2.3c.1.2 1.6 2.4 3.8 3.4.5.2 1 .4 1.3.5.5.2 1 .2 1.4.1.4-.1 1.3-.5 1.5-1.1.2-.5.2-1 .1-1.1-.1-.1-.2-.1-.4-.2z" fill="#FFFFFF"/>
    </svg>
@else
    {{-- Generic Shopping Bag Icon --}}
    <svg viewBox="0 0 24 24" class="{{ $class }}" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/>
    </svg>
@endif
