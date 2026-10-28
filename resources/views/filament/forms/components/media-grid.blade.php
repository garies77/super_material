{{-- Grid pilih gambar Pustaka Media. Gaya: .sm-media-* di filament/admin-styles.blade.php --}}
@php
    $items = $getItems();
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
                        </span>
                    </button>
                @endforeach
            </div>

            @if ($items->count() >= \App\Filament\Support\MediaPicker::LIMIT)
                <p class="sm-media-empty">Menampilkan {{ \App\Filament\Support\MediaPicker::LIMIT }} gambar terbaru. Gunakan pencarian untuk menemukan gambar lain.</p>
            @endif
        @endif
    </div>
</x-dynamic-component>
