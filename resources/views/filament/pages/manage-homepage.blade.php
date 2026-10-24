<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Seret seksi untuk mengubah urutan di beranda. Klik nama seksi untuk mengubah isinya; matikan "Tampilkan" untuk menyembunyikan tanpa menghapus pengaturannya.
        </p>

        {{ $this->form }}

        <div class="flex flex-wrap gap-3">
            <x-filament::button type="submit" icon="heroicon-o-check" wire:loading.attr="disabled">Simpan Beranda</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
