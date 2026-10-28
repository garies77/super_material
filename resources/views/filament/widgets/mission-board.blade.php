{{-- Papan misi dashboard (App\Filament\Widgets\MissionBoard). Gaya: .sm-mission-* di filament/admin-styles.blade.php --}}
<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-trophy" heading="Papan misi" description="Pekerjaan berikutnya untuk Anda. Klik Kerjakan untuk membuka item berikutnya.">
        @if ($missions === [])
            <div class="sm-mission-empty">
                <span class="sm-mission-icon">🎉</span>
                <div>
                    <strong>Semua misi beres!</strong>
                    <p class="sm-seo-sub">Tidak ada pekerjaan tertunda. Saatnya menambah konten baru.</p>
                </div>
                @if ($createProductUrl)
                    <x-filament::button tag="a" :href="$createProductUrl" icon="heroicon-o-plus">Tambah produk</x-filament::button>
                @endif
                @if ($createArticleUrl)
                    <x-filament::button tag="a" :href="$createArticleUrl" color="gray" icon="heroicon-o-pencil-square">Tulis artikel</x-filament::button>
                @endif
            </div>
        @else
            <div class="sm-mission-grid">
                @foreach ($missions as $mission)
                    <div class="sm-mission">
                        <span class="sm-mission-icon">{{ $mission['icon'] }}</span>
                        <div class="sm-mission-body">
                            <strong>{{ $mission['title'] }}</strong>
                            <span class="sm-seo-sub">{{ $mission['hint'] }}</span>
                        </div>
                        <span class="sm-mission-count">{{ $mission['count'] }}</span>
                        <x-filament::button tag="a" :href="$mission['url']" size="sm">Kerjakan →</x-filament::button>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
