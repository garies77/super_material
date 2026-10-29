{{-- Pengingat dashboard Editor/Admin (App\Filament\Widgets\AttentionBoard). Memakai gaya .sm-mission-* dari theme.css. --}}
<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-bell-alert" heading="Perlu perhatian" description="Ditangani sekaligus dari daftar tersaring.">
        <div class="sm-mission-grid">
            @foreach ($items as $item)
                <div class="sm-mission">
                    <span class="sm-mission-icon is-{{ $item['tone'] }}">
                        <x-filament::icon :icon="$item['icon']" />
                    </span>
                    <div class="sm-mission-body">
                        <strong>{{ $item['title'] }}</strong>
                        <span class="sm-seo-sub">{{ $item['hint'] }}</span>
                    </div>
                    <span class="sm-mission-count">{{ $item['count'] }}</span>
                    <x-filament::button tag="a" :href="$item['url']" size="sm" color="gray" icon="heroicon-m-arrow-right" icon-position="after">
                        {{ $item['action'] }}
                    </x-filament::button>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
