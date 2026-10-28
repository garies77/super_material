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
        <dd x-data="{ copied: false }">
            <code>{{ $media->url() }}</code>
            <x-filament::link
                tag="button"
                size="sm"
                x-on:click="navigator.clipboard.writeText(@js($media->url())); copied = true; setTimeout(() => copied = false, 1500)"
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
