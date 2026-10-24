<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex flex-wrap gap-3">
            <x-filament::button type="submit" icon="heroicon-o-check" wire:loading.attr="disabled">Simpan Pengaturan</x-filament::button>
            <x-filament::button tag="a" color="gray" icon="heroicon-o-arrow-left" :href="\App\Filament\Resources\Articles\ArticleResource::getUrl()">Kembali ke daftar artikel</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
