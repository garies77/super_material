{{--
    Mode pilih Pustaka Media: klik kartu menandai/melepas gambar sepenuhnya di browser (tanpa request ke server).
    Shift + klik memilih semua kartu di antara klik terakhir dan kartu ini. Pilihan disimpan di $wire.selectedMediaIds
    (entangle tunda) dan baru terkirim saat "Hapus terpilih" dipanggil; "Semua yang belum dipakai" diisi server.
    Kartu dikenali dari atribut data-media-id pada kolom thumbnail. Gaya: .sm-media-* & .sm-select-bar di theme.css.
--}}
<div
    x-data="{
        selected: $wire.$entangle('selectedMediaIds'),
        lastClickedId: null,
        isSelecting() {
            return $wire.isSelectingMedia
        },
        recordId(record) {
            const id = record?.querySelector('[data-media-id]')?.dataset.mediaId
            return id ? Number(id) : null
        },
        visibleIds() {
            return [...document.querySelectorAll('.fi-ta-record')].map((record) => this.recordId(record)).filter((id) => id !== null)
        },
        toggle(id) {
            this.selected = this.selected.includes(id)
                ? this.selected.filter((selectedId) => selectedId !== id)
                : [...this.selected, id]
        },
        selectRange(fromId, toId) {
            const ids = this.visibleIds()
            const [start, end] = [ids.indexOf(fromId), ids.indexOf(toId)].sort((a, b) => a - b)
            if (start < 0) return this.toggle(toId)
            this.selected = [...new Set([...this.selected, ...ids.slice(start, end + 1)])]
        },
        selectPage() {
            this.selected = [...new Set([...this.selected, ...this.visibleIds()])]
            this.paint()
        },
        clear() {
            this.selected = []
            this.lastClickedId = null
            this.paint()
        },
        paint() {
            document.querySelectorAll('.fi-ta-record').forEach((record) => {
                const id = this.recordId(record)
                record.classList.toggle('sm-media-card-selected', this.isSelecting() && id !== null && this.selected.includes(id))
            })
        },
        init() {
            this.onClick = (event) => {
                if (! this.isSelecting()) return
                const id = this.recordId(event.target.closest('.fi-ta-record'))
                if (id === null) return
                event.preventDefault()
                event.shiftKey && this.lastClickedId !== null ? this.selectRange(this.lastClickedId, id) : this.toggle(id)
                this.lastClickedId = id
                this.paint()
            }
            document.addEventListener('click', this.onClick)
            // Grid di-render ulang (filter, halaman berikutnya, pilihan dari server): warnai ulang kartu terpilih
            this.unhook = Livewire.hook('commit', ({ succeed }) => succeed(() => requestAnimationFrame(() => this.paint())))
            this.paint()
        },
        destroy() {
            document.removeEventListener('click', this.onClick)
            this.unhook?.()
        },
    }"
>
    <div class="sm-select-bar" x-show="isSelecting()" x-cloak>
        <span><strong x-text="selected.length"></strong> dipilih</span>
        <x-filament::button color="gray" size="sm" x-on:click="selectPage()">
            Semua di halaman ini
        </x-filament::button>
        <x-filament::button color="gray" size="sm" wire:click="selectAllUnusedMedia" wire:loading.attr="disabled" wire:target="selectAllUnusedMedia">
            Semua yang belum dipakai
        </x-filament::button>
        <x-filament::button color="gray" size="sm" x-on:click="clear()" x-bind:disabled="selected.length === 0">
            Kosongkan
        </x-filament::button>
        <x-filament::button color="danger" size="sm" icon="heroicon-o-trash" x-on:click="$wire.mountAction('deleteSelected')" x-bind:disabled="selected.length === 0">
            Hapus terpilih
        </x-filament::button>
        <span class="sm-select-hint">Shift + klik untuk memilih rentang</span>
    </div>
</div>
