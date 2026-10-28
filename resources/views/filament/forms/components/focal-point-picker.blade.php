{{-- Pemilih titik fokus: klik gambar untuk memindahkan penanda. Gaya: .sm-focal-* di filament/admin-styles.blade.php --}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            state: $wire.$entangle(@js($getStatePath())),
            pick(event) {
                const box = event.currentTarget.getBoundingClientRect()
                const clamp = (value) => Math.min(100, Math.max(0, Math.round(value)))

                this.state = {
                    x: clamp(((event.clientX - box.left) / box.width) * 100),
                    y: clamp(((event.clientY - box.top) / box.height) * 100),
                }
            },
        }"
        {{ $getExtraAttributeBag() }}
    >
        <div class="sm-focal" x-on:click="pick($event)" role="button" aria-label="Klik untuk memilih titik fokus">
            <img src="{{ $getImageUrl() }}" alt="" draggable="false">
            <span class="sm-focal-marker" x-bind:style="`left: ${state?.x ?? 50}%; top: ${state?.y ?? 50}%`"></span>
        </div>
        <p class="sm-media-empty" x-text="`Titik fokus: ${state?.x ?? 50}% dari kiri, ${state?.y ?? 50}% dari atas`"></p>
    </div>
</x-dynamic-component>
