{{-- Pratinjau potongan: geser bingkai ke bagian yang ingin dipakai, perbesar untuk memotong lebih rapat. Gaya: .sm-crop-* di resources/css/filament/admin/theme.css --}}
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            state: $wire.$entangle(@js($getStatePath())),
            imageRatio: @js($getImageRatio()),
            targetRatio: @js($getTargetRatio()),
            sourceWidth: @js($getSourceWidth()),
            maxOutputWidth: @js($getMaxOutputWidth()),
            maxZoom: @js(\App\Filament\Support\CropFramePicker::MAX_ZOOM),
            dragging: false,
            init() {
                this.state = { x: this.state?.x ?? 50, y: this.state?.y ?? 50, zoom: this.state?.zoom ?? 1 }
            },
            zoom() {
                return Math.min(this.maxZoom, Math.max(1, Number(this.state?.zoom ?? 1)))
            },
            frame() {
                const wide = this.imageRatio > this.targetRatio
                const width = (wide ? this.targetRatio / this.imageRatio * 100 : 100) / this.zoom()
                const height = (wide ? 100 : this.imageRatio / this.targetRatio * 100) / this.zoom()
                const clamp = (value, size) => Math.min(100 - size, Math.max(0, value - size / 2))

                return { width, height, left: clamp(this.state?.x ?? 50, width), top: clamp(this.state?.y ?? 50, height) }
            },
            frameStyle() {
                const f = this.frame()

                return `left: ${f.left}%; top: ${f.top}%; width: ${f.width}%; height: ${f.height}%`
            },
            outputLabel() {
                if (! this.sourceWidth) return ''
                const width = Math.round(Math.min(this.sourceWidth * this.frame().width / 100, this.maxOutputWidth ?? Infinity))

                return `Hasil: ${width}×${Math.round(width / this.targetRatio)} px`
            },
            move(event) {
                const box = this.$refs.image.getBoundingClientRect()
                const clamp = (value) => Math.min(100, Math.max(0, Math.round(value)))

                this.state = {
                    ...this.state,
                    x: clamp(((event.clientX - box.left) / box.width) * 100),
                    y: clamp(((event.clientY - box.top) / box.height) * 100),
                }
            },
        }"
        {{ $getExtraAttributeBag() }}
    >
        <div
            class="sm-crop"
            x-ref="image"
            x-on:pointerdown.prevent="dragging = true; $el.setPointerCapture($event.pointerId); move($event)"
            x-on:pointermove="dragging && move($event)"
            x-on:pointerup="dragging = false"
            x-on:pointercancel="dragging = false"
            role="application"
            aria-label="Geser bingkai ke bagian gambar yang ingin dipakai"
        >
            <img src="{{ $getImageUrl() }}" alt="" draggable="false">
            <span class="sm-crop-frame" x-bind:style="frameStyle()"></span>
        </div>

        <div class="sm-crop-controls">
            <label class="sm-crop-zoom">
                <span>Perbesar</span>
                <input type="range" min="1" x-bind:max="maxZoom" step="0.05"
                       x-bind:value="zoom()" x-on:input="state = { ...state, zoom: Number($event.target.value) }">
            </label>
            <span class="sm-media-empty" x-text="outputLabel()"></span>
        </div>
    </div>
</x-dynamic-component>
