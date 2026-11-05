{{-- Detail berkas Pustaka Media: pratinjau, info, URL, dan konten yang memakainya --}}
<div class="sm-media-detail">
    <img src="{{ $media->url() }}" alt="{{ $media->alt_text ?: $media->name }}" class="sm-media-detail-image">

    <dl class="sm-media-detail-info">
        <dt>Folder</dt>
        <dd>{{ $media->folderLabel() }}</dd>
        <dt>Ukuran</dt>
        <dd>{{ collect([$media->ratioLabel(), $media->dimensionsLabel(), $media->sizeLabel(), $media->mime_type])->filter()->implode(' · ') }}</dd>
        <dt>Teks alternatif</dt>
        <dd>{{ $media->alt_text ?: '—' }}</dd>
        <dt>Diunggah</dt>
        <dd>{{ $media->created_at?->translatedFormat('d M Y H:i') }}@if ($media->uploader) oleh {{ $media->uploader->name }}@endif</dd>
        <dt>Path</dt>
        <dd><code>{{ $media->path }}</code></dd>
        <dt>URL</dt>
        {{-- Clipboard API hanya ada di koneksi aman; selain itu pakai textarea + execCommand. "Tersalin" hanya bila berhasil. --}}
        <dd x-data="{
            copied: false,
            async copy(text) {
                let ok = false;
                if (navigator.clipboard && window.isSecureContext) {
                    try { await navigator.clipboard.writeText(text); ok = true; } catch {}
                }
                if (! ok) {
                    const area = document.createElement('textarea');
                    area.value = text;
                    area.style.position = 'fixed';
                    area.style.opacity = '0';
                    document.body.appendChild(area);
                    area.select();
                    try { ok = document.execCommand('copy'); } catch {}
                    area.remove();
                }
                if (ok) {
                    this.copied = true;
                    setTimeout(() => this.copied = false, 1500);
                }
            },
        }">
            <code>{{ $media->url() }}</code>
            <x-filament::link
                tag="button"
                size="sm"
                x-on:click="copy(@js($media->url()))"
            >
                <span x-text="copied ? 'Tersalin' : 'Salin URL'"></span>
            </x-filament::link>
        </dd>
    </dl>

    <div>
        <h3 class="sm-media-detail-heading">Dipakai di ({{ count($usages) }})</h3>

        @forelse ($usages as $usage)
            <p class="sm-media-detail-usage">
                <x-filament::badge color="gray" size="sm">{{ $usage['type'] }}</x-filament::badge>
                @if ($usage['url'])
                    <x-filament::link :href="$usage['url']" target="_blank">{{ $usage['title'] }}</x-filament::link>
                @else
                    <span>{{ $usage['title'] }}</span>
                @endif
            </p>
        @empty
            <p class="sm-media-empty">Belum dipakai di konten mana pun.</p>
        @endforelse
    </div>
</div>
