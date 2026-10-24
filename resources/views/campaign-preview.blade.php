{{-- Pratinjau banner kampanye dalam bingkai desktop dan mobile (PRD 8.9), khusus Admin/Editor. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Pratinjau kampanye: {{ $campaign->name }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-ink-100 p-6">
    <div class="mb-5 flex flex-wrap items-center gap-3">
        <h1 class="text-xl font-bold">Pratinjau: {{ $campaign->name }}</h1>
        <span class="chip">{{ $campaign->displayStatus() }}</span>
        @unless($campaign->isLive())
            <span class="text-sm font-semibold text-red-600">Belum tayang di situs publik.</span>
        @endunless
        @if($campaign->isPopup() && $campaign->device !== 'all')
            <span class="text-sm text-ink-600">Di situs hanya tampil di {{ $campaign->device === 'mobile' ? 'mobile' : 'desktop' }}.</span>
        @endif
    </div>
    <p class="mb-5 text-sm text-ink-600">
        @if($campaign->isPopup())
            Di pratinjau modal langsung terbuka; di situs muncul {{ $campaign->trigger === 'scroll' ? 'setelah pengunjung menggulir '.$campaign->scroll_percent.'% halaman' : 'setelah jeda '.$campaign->delay_seconds.' detik' }}.
        @else
            Contoh di Homepage: banner tampil di atas konten pada {{ collect($campaign->pageList())->map(fn ($p) => \App\Models\Ad::PAGES[$p] ?? $p)->implode(', ') }}.
        @endif
        Pratinjau tidak dicatat di laporan.
    </p>
    <div class="flex flex-wrap items-start gap-6">
        <figure>
            <figcaption class="mb-2 text-sm font-semibold">Desktop (1280 × 800, diperkecil)</figcaption>
            <div class="overflow-hidden rounded-xl border border-ink-200 bg-white shadow" style="width:960px;height:600px">
                <iframe src="{{ route('ad.preview-frame', $campaign) }}" title="Pratinjau desktop" style="width:1280px;height:800px;transform:scale(.75);transform-origin:0 0;border:0"></iframe>
            </div>
        </figure>
        <figure>
            <figcaption class="mb-2 text-sm font-semibold">Mobile (375 × 740)</figcaption>
            <iframe src="{{ route('ad.preview-frame', $campaign) }}" title="Pratinjau mobile" class="rounded-[1.5rem] border-8 border-ink-900 bg-white shadow" style="width:391px;height:756px"></iframe>
        </figure>
    </div>
</body>
</html>
