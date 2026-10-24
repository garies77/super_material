{{-- Pratinjau halaman statis (layout frontend asli di dalam bingkai perangkat), khusus Admin/Editor. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Preview: {{ $page->title }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-ink-100">
    <header class="sticky top-0 z-10 flex flex-wrap items-center gap-3 border-b border-ink-200 bg-white px-4 py-3 shadow-sm">
        <a href="{{ \App\Filament\Resources\Pages\PageResource::getUrl('edit', ['record' => $page]) }}" class="btn border border-ink-200 bg-white !py-2 text-sm text-ink-800 hover:bg-ink-50">← Kembali ke editor</a>
        <div class="min-w-0">
            <p class="truncate text-sm font-bold text-ink-900">Preview: {{ $page->title }}</p>
            <p class="text-xs {{ $page->is_published && ! $page->hasUnpublishedChanges() ? 'text-emerald-700' : 'text-amber-700' }}">
                {{ ! $page->is_published ? 'Draf, belum tampil di situs' : ($page->hasUnpublishedChanges() ? 'Menampilkan draf yang belum dipublikasikan' : 'Sama dengan halaman publik') }}
            </p>
        </div>
        <div class="ml-auto flex rounded-xl border border-ink-200 p-1" role="group" aria-label="Ukuran layar">
            @foreach(['desktop' => ['Desktop', '100%'], 'tablet' => ['Tablet', '768px'], 'mobile' => ['Mobile', '375px']] as $key => [$label, $width])
                <button type="button" data-device="{{ $width }}" aria-pressed="{{ $key === 'desktop' ? 'true' : 'false' }}"
                        class="rounded-lg px-3 py-1.5 text-sm font-semibold text-ink-600 aria-pressed:bg-ink-900 aria-pressed:text-white">{{ $label }}</button>
            @endforeach
        </div>
    </header>

    <main class="flex justify-center p-4">
        <iframe id="preview-frame" src="{{ route('page.preview-frame', $page) }}" title="Preview halaman"
                class="h-[calc(100vh-6rem)] w-full rounded-xl border border-ink-200 bg-white shadow transition-[width] duration-300"></iframe>
    </main>

    <script nonce="{{ Vite::cspNonce() }}">
        document.querySelectorAll('[data-device]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.getElementById('preview-frame').style.width = btn.dataset.device;
                document.querySelectorAll('[data-device]').forEach(function (b) { b.setAttribute('aria-pressed', String(b === btn)); });
            });
        });
    </script>
</body>
</html>
