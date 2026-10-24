<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex flex-wrap gap-3">
            <x-filament::button type="submit" icon="heroicon-o-check" wire:loading.attr="disabled">Simpan</x-filament::button>
            <x-filament::button color="gray" icon="heroicon-o-eye" wire:click="preview" wire:loading.attr="disabled">Preview (belum disimpan)</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
