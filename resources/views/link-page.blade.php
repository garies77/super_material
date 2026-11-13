{{-- Halaman link-in-bio /l/{slug}: berdiri sendiri (tanpa navbar/footer), mobile-first. Klik lewat /l/{slug}/{item} agar tercatat. --}}
@php
    use App\Models\Setting;

    $theme = match ($page->theme) {
        'dark' => [
            'body' => 'bg-ink-950 text-ink-100',
            'title' => 'text-white',
            'muted' => 'text-ink-400',
            'button' => 'border-white/10 bg-white/5 text-white hover:bg-white/10',
            'highlight' => 'border-transparent bg-brand-500 text-[var(--color-on-brand)] hover:bg-brand-600',
            'social' => 'text-ink-100 hover:bg-white/10',
            'heading' => 'text-ink-400',
            'ring' => 'focus-visible:ring-brand-400 focus-visible:ring-offset-ink-950',
            'avatar' => 'bg-brand-500 text-[var(--color-on-brand)]',
        ],
        'brand' => [
            'body' => 'bg-brand-500 text-[var(--color-on-brand)]',
            'title' => 'text-[var(--color-on-brand)]',
            'muted' => 'text-[var(--color-on-brand)] opacity-80',
            'button' => 'border-transparent bg-white text-ink-900 hover:bg-ink-50',
            'highlight' => 'border-transparent bg-ink-900 text-white hover:bg-ink-800',
            'social' => 'text-[var(--color-on-brand)] hover:bg-black/10',
            'heading' => 'text-[var(--color-on-brand)] opacity-80',
            'ring' => 'focus-visible:ring-ink-900 focus-visible:ring-offset-brand-500',
            'avatar' => 'bg-white text-ink-900',
        ],
        default => [
            'body' => 'bg-ink-50 text-ink-800',
            'title' => 'text-ink-950',
            'muted' => 'text-ink-600',
            'button' => 'border-ink-200 bg-white text-ink-900 hover:border-ink-400',
            'highlight' => 'border-transparent bg-brand-500 text-[var(--color-on-brand)] hover:bg-brand-600',
            'social' => 'text-ink-800 hover:bg-ink-100',
            'heading' => 'text-ink-600',
            'ring' => 'focus-visible:ring-brand-500 focus-visible:ring-offset-ink-50',
            'avatar' => 'bg-brand-500 text-[var(--color-on-brand)]',
        ],
    };

    $metaTitle = $page->meta_title ?: $page->title;
    $metaDescription = $page->meta_description ?: \Illuminate\Support\Str::limit((string) $page->bio, 155);
    $avatar = $page->avatarUrl();
    $initial = mb_strtoupper(mb_substr($page->title, 0, 1));
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $metaTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <link rel="canonical" href="{{ $page->url() }}">
    @unless($page->is_published)
        <meta name="robots" content="noindex, nofollow">
    @endunless
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:url" content="{{ $page->url() }}">
    @if($avatar)
        <meta property="og:image" content="{{ $avatar }}">
    @endif
    <link rel="icon" href="{{ \App\Support\Favicon::url() }}" type="{{ \App\Support\Favicon::type() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    <style>{!! Setting::themeCss() !!}</style>
</head>
<body class="min-h-screen {{ $theme['body'] }}">
    @unless($page->is_published)
        <div class="bg-amber-100 px-4 py-2 text-center text-sm font-medium text-amber-900">Pratinjau draf — halaman ini belum terbit dan statistik tidak dicatat.</div>
    @endunless

    <main class="mx-auto flex w-full max-w-[480px] flex-col items-center px-5 pb-12 pt-10">
        @if($avatar)
            <img src="{{ $avatar }}" alt="{{ $page->title }}" width="96" height="96" class="h-24 w-24 rounded-full object-cover shadow-md">
        @else
            <div class="flex h-24 w-24 items-center justify-center rounded-full font-display text-4xl font-extrabold shadow-md {{ $theme['avatar'] }}" aria-hidden="true">{{ $initial }}</div>
        @endif

        <h1 class="mt-4 text-center font-display text-2xl font-extrabold {{ $theme['title'] }}">{{ $page->title }}</h1>
        @if($page->bio)
            <p class="mt-2 whitespace-pre-line text-center text-[15px] leading-relaxed {{ $theme['muted'] }}">{{ $page->bio }}</p>
        @endif

        @if($socials = $page->validSocials())
            <ul class="mt-4 flex flex-wrap justify-center gap-1" aria-label="Media sosial">
                @foreach($socials as $social)
                    <li>
                        <a href="{{ $social['url'] }}" target="_blank" rel="noopener me"
                           class="flex h-11 w-11 items-center justify-center rounded-full transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 {{ $theme['social'] }} {{ $theme['ring'] }}"
                           aria-label="{{ \App\Models\LinkPage::socialOptions()[$social['platform']] ?? $social['platform'] }}">
                            @include('partials.social-icon', ['platform' => $social['platform'], 'class' => 'h-5 w-5'])
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="mt-8 flex w-full flex-col gap-3">
            @forelse($items as $item)
                @if($item->type === 'heading')
                    <h2 class="mt-3 text-center text-xs font-semibold uppercase tracking-wider {{ $theme['heading'] }}">{{ $item->label }}</h2>
                @else
                    @php($thumb = $item->thumbnailUrl())
                    <a href="{{ route('link.click', [$page->slug, $item->id]) }}"
                       @if($item->opensInNewTab()) target="_blank" rel="noopener" @endif
                       class="relative flex min-h-14 w-full items-center rounded-2xl border px-4 py-3 text-[15px] font-semibold shadow-sm transition active:scale-[.99] focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 {{ $item->is_highlighted ? $theme['highlight'] : $theme['button'] }} {{ $theme['ring'] }}">
                        @if($thumb)
                            <img src="{{ $thumb }}" alt="" width="40" height="40" loading="lazy" class="absolute left-2 h-10 w-10 rounded-xl object-cover">
                        @elseif($item->type === 'whatsapp')
                            <span class="absolute left-3 {{ $item->is_highlighted ? '' : 'text-[#25D366]' }}">@include('partials.social-icon', ['platform' => 'whatsapp', 'class' => 'h-6 w-6'])</span>
                        @endif
                        <span class="w-full px-10 text-center">{{ $item->label }}</span>
                    </a>
                @endif
            @empty
                <p class="text-center text-sm {{ $theme['muted'] }}">Belum ada tautan.</p>
            @endforelse
        </div>

        <a href="{{ url('/') }}" class="mt-12 text-xs font-medium underline-offset-4 hover:underline {{ $theme['muted'] }}">{{ Setting::brandName() }}</a>
    </main>
</body>
</html>
