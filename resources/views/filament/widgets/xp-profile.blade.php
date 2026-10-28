{{-- Kartu XP & lencana (App\Filament\Widgets\XpProfile). Gaya: .sm-xp-* di resources/css/filament/admin/theme.css --}}
<x-filament-widgets::widget>
    <x-filament::section>
        <div class="sm-xp">
            <div class="sm-xp-level">
                <div class="sm-xp-level-head">
                    <x-filament::icon icon="heroicon-o-trophy" class="sm-xp-level-icon" />
                    <div>
                        <p class="sm-xp-label">Level Anda</p>
                        <p class="sm-xp-level-name">{{ $level['name'] }}</p>
                    </div>
                    <p class="sm-xp-total"><strong>{{ number_format($level['xp'], 0, ',', '.') }}</strong> XP</p>
                </div>

                <div class="sm-xp-bar" role="progressbar" aria-valuenow="{{ $level['progress'] }}" aria-valuemin="0" aria-valuemax="100">
                    <span style="width: {{ $level['progress'] }}%"></span>
                </div>
                <p class="sm-seo-sub">
                    @if ($level['next'])
                        {{ number_format($level['next_at'] - $level['xp'], 0, ',', '.') }} XP lagi menuju <strong>{{ $level['next'] }}</strong>
                    @else
                        Level tertinggi tercapai.
                    @endif
                </p>

                <p class="sm-xp-label sm-xp-rules">
                    XP diberikan saat kiriman disetujui: +{{ \App\Support\Gamification::POINTS_APPROVED }} disetujui,
                    +{{ \App\Support\Gamification::POINTS_COMPLETE }} skor ≥ {{ \App\Support\Gamification::COMPLETE_SCORE }}%,
                    +{{ \App\Support\Gamification::POINTS_PERFECT }} skor 100%,
                    +{{ \App\Support\Gamification::POINTS_FIRST_TIME_RIGHT }} tanpa perbaikan.
                </p>
            </div>

            <div>
                <p class="sm-xp-heading">Lencana</p>
                <div class="sm-xp-badges">
                    @foreach ($badges as $badge)
                        <div @class(['sm-xp-badge', 'is-earned' => $badge['earned']]) title="{{ $badge['description'] }}">
                            <x-filament::icon :icon="$badge['icon']" class="sm-xp-badge-icon" />
                            <span class="sm-xp-badge-name">{{ $badge['name'] }}</span>
                            <span class="sm-xp-label">{{ $badge['earned'] ? 'Diraih' : $badge['progress'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div>
                <p class="sm-xp-heading">Riwayat XP terbaru</p>
                @forelse ($history as $event)
                    <div class="sm-xp-event">
                        <span class="sm-xp-points">+{{ $event->points }}</span>
                        <span class="sm-xp-reason">{{ $event->reason }}</span>
                        <span class="sm-xp-label">{{ $event->created_at->diffForHumans() }}</span>
                    </div>
                @empty
                    <p class="sm-seo-sub">Belum ada XP. Ajukan draf yang lengkap untuk mendapat XP pertama Anda.</p>
                @endforelse
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
