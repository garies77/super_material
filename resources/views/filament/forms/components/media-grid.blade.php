{{-- Grid pilih gambar Pustaka Media. Gaya: .sm-media-* di resources/css/filament/admin/theme.css --}}
@php
    // Item diambil satu lebih banyak dari ukuran halaman: kelebihannya menandakan masih ada gambar lain
    $pageSize = $getPageSize();
    $items = $getItems();
    $hasMore = $items->count() > $pageSize;
    $items = $items->take($pageSize);
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            state: $wire.$entangle(@js($getStatePath())),
            multiple: @js($isMultiple()),
            selected() {
                return Array.isArray(this.state) ? this.state : []
            },
            toggle(path) {
                let paths = [...this.selected()]
                const index = paths.indexOf(path)

                if (this.multiple) {
                    index >= 0 ? paths.splice(index, 1) : paths.push(path)
                } else {
                    paths = index >= 0 ? [] : [path]
                }

                this.state = paths
            },
            order(path) {
                return this.selected().indexOf(path) + 1
            },
        }"
        {{ $getExtraAttributeBag() }}
    >
        @if ($items->isEmpty())
            <p class="sm-media-empty">Tidak ada gambar yang cocok. Ubah kata kunci atau folder, atau unggah gambar baru.</p>
        @else
            <div class="sm-media-grid">
                @foreach ($items as $media)
                    <button
                        type="button"
                        wire:key="media-grid-{{ $media->id }}"
                        x-on:click="toggle(@js($media->path))"
                        x-bind:class="{ 'is-selected': order(@js($media->path)) > 0 }"
                        x-bind:aria-pressed="order(@js($media->path)) > 0"
                        class="sm-media-item"
                        title="{{ $media->name }}"
                    >
                        <img src="{{ $media->thumbnailUrl() }}" alt="{{ $media->alt_text ?: $media->name }}" loading="lazy" decoding="async" width="160" height="160">
                        <span class="sm-media-badge" x-show="order(@js($media->path)) > 0" x-text="multiple ? order(@js($media->path)) : '✓'"></span>
                        @if (($fits = $field->fitsSection($media)) !== null)
                            <span @class(['sm-media-fit', 'is-fit' => $fits])>{{ $fits ? 'Pas' : ($field->willCrop($media) ? 'Dipotong' : 'Rasio lain') }}</span>
                        @endif
                        <span class="sm-media-caption">
                            <span class="sm-media-name">{{ $media->name }}</span>
                            <span class="sm-media-meta">{{ collect([$media->ratioLabel(), $media->dimensionsLabel() ?? $media->folderLabel()])->filter()->implode(' · ') }}</span>
                            @if ($lowTarget = $field->lowResolutionTarget($media))
                                <span class="sm-media-warning" title="Section ini memakai lebar {{ $lowTarget }} px; gambar ini akan tampil buram.">⚠ Resolusi rendah</span>
                            @endif
                        </span>
                    </button>
                @endforeach
            </div>

            @if ($hasMore)
                <div class="sm-media-more">
                    <x-filament::button
                        color="gray"
                        size="sm"
                        x-on:click="$wire.set(@js($getLimitStatePath()), {{ $pageSize + \App\Filament\Support\MediaPicker::LIMIT }})"
                        wire:loading.attr="disabled"
                    >
                        Muat lebih banyak
                    </x-filament::button>
                    <span class="sm-media-empty">Menampilkan {{ $pageSize }} gambar terbaru</span>
                </div>
            @endif
        @endif
    </div>
</x-dynamic-component>
