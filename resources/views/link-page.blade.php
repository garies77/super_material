{{--
    Halaman link-in-bio /l/{slug}: berdiri sendiri (tanpa navbar/footer), mobile-first. Klik lewat /l/{slug}/{item} agar tercatat.
    Tampilan dari App\Support\LinkBioDesign (warna & pilihan sudah disaring). Dipakai juga oleh pratinjau admin:
    $preview = true mematikan tautan, $urls menimpa URL gambar dengan unggahan yang belum disimpan.
--}}
@php
    use App\Models\LinkPage;
    use App\Models\Setting;
    use App\Support\LinkBioDesign;

    $preview ??= false;
    $urls ??= [];
    $design = $page->resolvedDesign();
    $avatar = $urls['avatar'] ?? $page->avatarUrl();
    $banner = $urls['banner'] ?? $page->bannerUrl();
    $backgroundImage = $urls['background'] ?? LinkBioDesign::storageUrl($design['bg_image_path']);
    $layout = $design['layout'];

    $metaTitle = $page->metaTitle();
    $metaDescription = $page->metaDescription();
    $shareImage = $preview ? null : $page->shareImage();

    // Data terstruktur Google: halaman profil yang mewakili organisasi; sameAs = akun media sosial resmi
    $structuredData = $preview ? null : [
        '@context' => 'https://schema.org',
        '@type' => 'ProfilePage',
        '@id' => $page->url().'#profilepage',
        'url' => $page->url(),
        'name' => $metaTitle,
        'description' => $metaDescription,
        'inLanguage' => 'id-ID',
        'dateCreated' => $page->created_at?->toAtomString(),
        'dateModified' => $page->updated_at?->toAtomString(),
        'isPartOf' => ['@type' => 'WebSite', '@id' => url('/').'#website', 'name' => Setting::brandName(), 'url' => url('/')],
        'mainEntity' => array_filter([
            '@type' => 'Organization',
            '@id' => $page->url().'#organization',
            'name' => $page->title,
            'description' => $page->bio ?: null,
            'url' => url('/'),
            'image' => $page->avatarUrl() ?: $shareImage['url'] ?? null,
            'logo' => $page->avatarUrl(),
            'sameAs' => array_column($page->validSocials(), 'url') ?: null,
        ]),
    ];
    $structuredData = $structuredData ? array_filter($structuredData) : null;
    $initial = mb_strtoupper(mb_substr((string) $page->title, 0, 1)) ?: '•';

    // Bentuk burst: bintang 24 titik sebagai clip-path
    $burst = collect(range(0, 47))->map(function ($i) {
        $r = $i % 2 ? 43 : 50;
        $a = deg2rad($i * 7.5 - 90);

        return round(50 + $r * cos($a), 2).'% '.round(50 + $r * sin($a), 2).'%';
    })->implode(',');
    $svgMask = fn (string $body) => "url(\"data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'>{$body}</svg>\")";
    $flower = $svgMask("<g fill='black'><circle cx='50' cy='50' r='30'/>".collect(range(0, 5))->map(fn ($i) => "<circle cx='".round(50 + 26 * cos(deg2rad($i * 60)), 2)."' cy='".round(50 + 26 * sin(deg2rad($i * 60)), 2)."' r='23'/>")->implode('').'</g>');
    $loop = $svgMask("<path fill='black' d='M52 2c22 0 42 12 46 34 5 25-9 54-33 61-25 7-55-2-63-27C-5 46 6 18 24 8 33 3 42 2 52 2Z'/>");
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $metaTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    @if($preview || ! $page->isIndexable())
        <meta name="robots" content="noindex, nofollow">
    @else
        <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large">
    @endif
    @unless($preview)
        <link rel="canonical" href="{{ $page->url() }}">
        {{-- Open Graph (Facebook, WhatsApp, LinkedIn, Threads) & kartu X --}}
        <meta property="og:type" content="profile">
        <meta property="og:site_name" content="{{ Setting::brandName() }}">
        <meta property="og:locale" content="id_ID">
        <meta property="og:url" content="{{ $page->url() }}">
        <meta property="og:title" content="{{ $metaTitle }}">
        <meta property="og:description" content="{{ $metaDescription }}">
        @if($shareImage)
            <meta property="og:image" content="{{ $shareImage['url'] }}">
            <meta property="og:image:secure_url" content="{{ $shareImage['url'] }}">
            <meta property="og:image:alt" content="{{ $page->title }}">
            @if($page->og_image_path)
                <meta property="og:image:width" content="1200">
                <meta property="og:image:height" content="630">
            @endif
        @endif
        <meta name="twitter:card" content="{{ $shareImage['large'] ?? false ? 'summary_large_image' : 'summary' }}">
        <meta name="twitter:title" content="{{ $metaTitle }}">
        <meta name="twitter:description" content="{{ $metaDescription }}">
        @if($shareImage)
            <meta name="twitter:image" content="{{ $shareImage['url'] }}">
            <meta name="twitter:image:alt" content="{{ $page->title }}">
        @endif
        <x-jsonld :data="$structuredData" />
    @endunless
    <meta name="theme-color" content="{{ $design['bg_color'] }}">
    <link rel="icon" href="{{ \App\Support\Favicon::url() }}" type="{{ \App\Support\Favicon::type() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="{{ LinkBioDesign::fontUrl($design['font']) }}" rel="stylesheet">
    @vite(['resources/css/app.css'])
    <style>
        {!! LinkBioDesign::cssVariables($design, $backgroundImage) !!}
        body.lb { background: var(--lb-background); background-color: var(--lb-bg); background-attachment: fixed; color: var(--lb-text); font-family: var(--lb-font); }
        .lb h1, .lb h2 { font-family: var(--lb-font); color: var(--lb-text); }
        .lb-muted { color: color-mix(in srgb, var(--lb-text) 78%, transparent); }
        .lb-btn { background: var(--lb-btn); color: var(--lb-btn-text); border: 2px solid transparent; border-radius: var(--lb-radius); transition: transform .15s ease, filter .15s ease, box-shadow .15s ease; }
        .lb-btn:hover { transform: translateY(-1px); filter: brightness(1.04); }
        .lb-btn:active { transform: translateY(0) scale(.99); }
        .lb-btn:focus-visible, .lb-social:focus-visible { outline: 2px solid var(--lb-text); outline-offset: 3px; }
        .lb-style-soft .lb-btn { box-shadow: 0 4px 14px rgba(0,0,0,.12); }
        .lb-style-hard .lb-btn { border-color: var(--lb-btn-text); box-shadow: 4px 4px 0 var(--lb-btn-text); }
        .lb-style-hard .lb-btn:hover { box-shadow: 2px 2px 0 var(--lb-btn-text); transform: translate(2px, 2px); }
        .lb-style-outline .lb-btn { background: transparent; border-color: var(--lb-btn); }
        .lb .lb-btn.lb-highlight { background: var(--lb-accent); color: var(--lb-accent-text); border-color: var(--lb-accent); }
        .lb-social { color: var(--lb-text); border-radius: 999px; }
        .lb-social:hover { background: color-mix(in srgb, var(--lb-text) 12%, transparent); }
        .lb-avatar-fallback { background: var(--lb-accent); color: var(--lb-accent-text); }
        .lb-hero-fade { background: linear-gradient(to bottom, transparent 35%, var(--lb-bg) 100%); }
        .lb-ring { box-shadow: 0 0 0 4px var(--lb-bg); }
        .lb-shape-circle { border-radius: 50%; }
        .lb-shape-rounded { border-radius: 24%; }
        .lb-shape-oval { border-radius: 50%; aspect-ratio: 4 / 5; height: auto !important; }
        .lb-shape-burst { clip-path: polygon({{ $burst }}); }
        .lb-shape-flower { -webkit-mask: {!! $flower !!} center / contain no-repeat; mask: {!! $flower !!} center / contain no-repeat; }
        .lb-shape-loop { -webkit-mask: {!! $loop !!} center / contain no-repeat; mask: {!! $loop !!} center / contain no-repeat; }
        @if($preview) a { pointer-events: none; } html { scrollbar-width: none; } ::-webkit-scrollbar { display: none; } @endif
    </style>
</head>
<body class="lb lb-style-{{ $design['button_style'] }} min-h-screen antialiased">
    @if(! $page->is_published && ! $preview)
        <div class="bg-amber-100 px-4 py-2 text-center text-sm font-medium text-amber-900">Pratinjau draf — halaman ini belum terbit dan statistik tidak dicatat.</div>
    @endif

    <main class="mx-auto flex w-full max-w-[480px] flex-col items-center px-5 pb-12 {{ in_array($layout, ['hero', 'banner'], true) ? 'pt-0' : 'pt-10' }}">
        {{-- Header sesuai tata letak --}}
        @switch($layout)
            @case('hero')
                <div class="relative -mx-5 w-[calc(100%+2.5rem)]">
                    @if($avatar)
                        <img src="{{ $avatar }}" alt="{{ $page->title }}" class="aspect-square w-full object-cover">
                    @else
                        <div class="lb-avatar-fallback flex aspect-square w-full items-center justify-center text-7xl font-extrabold" aria-hidden="true">{{ $initial }}</div>
                    @endif
                    <div class="lb-hero-fade absolute inset-0"></div>
                </div>
                @break

            @case('banner')
                <div class="-mx-5 w-[calc(100%+2.5rem)]">
                    @if($banner)
                        <img src="{{ $banner }}" alt="" class="h-40 w-full object-cover">
                    @else
                        <div class="h-40 w-full" style="background: color-mix(in srgb, var(--lb-accent) 35%, var(--lb-bg))"></div>
                    @endif
                </div>
                @if($avatar)
                    <img src="{{ $avatar }}" alt="{{ $page->title }}" width="104" height="104" class="lb-ring relative -mt-[52px] h-[104px] w-[104px] rounded-full object-cover">
                @else
                    <div class="lb-avatar-fallback lb-ring relative -mt-[52px] flex h-[104px] w-[104px] items-center justify-center rounded-full text-4xl font-extrabold" aria-hidden="true">{{ $initial }}</div>
                @endif
                @break

            @case('cutout')
                @if($avatar)
                    <img src="{{ $avatar }}" alt="{{ $page->title }}" class="h-56 w-auto max-w-full object-contain drop-shadow-xl">
                @else
                    <div class="lb-avatar-fallback flex h-40 w-40 items-center justify-center rounded-full text-6xl font-extrabold" aria-hidden="true">{{ $initial }}</div>
                @endif
                @break

            @case('shape')
                @if($avatar)
                    <img src="{{ $avatar }}" alt="{{ $page->title }}" class="lb-shape-{{ $design['shape'] }} h-52 w-52 object-cover">
                @else
                    <div class="lb-avatar-fallback lb-shape-{{ $design['shape'] }} flex h-52 w-52 items-center justify-center text-6xl font-extrabold" aria-hidden="true">{{ $initial }}</div>
                @endif
                @break

            @default
                @if($avatar)
                    <img src="{{ $avatar }}" alt="{{ $page->title }}" width="96" height="96" class="h-24 w-24 rounded-full object-cover shadow-md">
                @else
                    <div class="lb-avatar-fallback flex h-24 w-24 items-center justify-center rounded-full text-4xl font-extrabold shadow-md" aria-hidden="true">{{ $initial }}</div>
                @endif
        @endswitch

        <h1 class="relative {{ $layout === 'hero' ? '-mt-14' : 'mt-4' }} text-center font-extrabold leading-tight {{ $design['title_size'] === 'large' ? 'text-3xl' : 'text-2xl' }}">{{ $page->title }}</h1>
        @if($page->bio)
            <p class="lb-muted relative mt-2 whitespace-pre-line text-center text-[15px] leading-relaxed">{{ $page->bio }}</p>
        @endif

        @if($socials = $page->validSocials())
            <ul class="relative mt-4 flex flex-wrap justify-center gap-1" aria-label="Media sosial">
                @foreach($socials as $social)
                    <li>
                        <a href="{{ $social['url'] }}" target="_blank" rel="noopener me" class="lb-social flex h-11 w-11 items-center justify-center"
                           aria-label="{{ LinkPage::socialOptions()[$social['platform']] ?? $social['platform'] }}">
                            @include('partials.social-icon', ['platform' => $social['platform'], 'class' => 'h-5 w-5'])
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif

        <div class="relative mt-8 flex w-full flex-col gap-3">
            @forelse($items as $item)
                @if($item->type === 'heading')
                    <h2 class="lb-muted mt-3 text-center text-xs font-semibold uppercase tracking-wider">{{ $item->label }}</h2>
                @else
                    @php($thumb = $item->thumbnailUrl())
                    <a href="{{ $preview ? '#' : route('link.click', [$page->slug, $item->id]) }}"
                       @if($item->opensInNewTab() && ! $preview) target="_blank" rel="noopener" @endif
                       class="lb-btn {{ $item->is_highlighted ? 'lb-highlight' : '' }} relative flex min-h-14 w-full items-center px-4 py-3 text-[15px] font-semibold">
                        @if($thumb)
                            <img src="{{ $thumb }}" alt="" width="40" height="40" loading="lazy" class="absolute left-2 h-10 w-10 rounded-[calc(var(--lb-radius)*.6)] object-cover">
                        @elseif($item->type === 'whatsapp')
                            <span class="absolute left-3 {{ $item->is_highlighted ? '' : 'text-[#25D366]' }}">@include('partials.social-icon', ['platform' => 'whatsapp', 'class' => 'h-6 w-6'])</span>
                        @endif
                        <span class="w-full px-10 text-center">{{ $item->label }}</span>
                    </a>
                @endif
            @empty
                <p class="lb-muted text-center text-sm">Belum ada tautan.</p>
            @endforelse
        </div>

        <a href="{{ $preview ? '#' : url('/') }}" class="lb-muted relative mt-12 text-xs font-medium underline-offset-4 hover:underline">{{ Setting::brandName() }}</a>
    </main>
</body>
</html>
