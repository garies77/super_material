{{--
    Panel misi kelengkapan (App\Filament\Support\SeoScorePanel). Gaya: .sm-seo-* di filament/admin-styles.blade.php.
    Misi yang belum selesai tampil di atas (bisa diklik untuk menggulir ke field-nya); yang selesai dilipat.
    wire:key berubah setiap skor berubah agar x-init berjalan lagi; confetti (window.smConfetti, filament/celebrate)
    hanya diputar saat skor NAIK menjadi 100% selama mengedit, bukan saat halaman dibuka sudah 100%.
--}}
@php
    $score = $result['score'];
    $checks = collect($result['checks'])->map(fn (bool $passed, string $label) => [
        'passed' => $passed,
        'text' => $missions[$label][0] ?? $label,
        'field' => $missions[$label][1] ?? null,
    ]);
    [$completed, $open] = $checks->partition(fn (array $check) => $check['passed']);
    $level = $score >= 80 ? 'good' : ($score >= 50 ? 'fair' : 'poor');
    $circumference = 2 * pi() * 26;
@endphp

<div
    class="sm-seo"
    wire:key="sm-seo-{{ $score }}"
    x-data="{ showCompleted: false }"
    x-init="
        const key = window.location.pathname
        const last = (window.smSeoLast ??= {})[key]
        if (last !== undefined && last < 100 && {{ $score }} === 100) window.smConfetti?.()
        window.smSeoLast[key] = {{ $score }}
    "
>
    <div class="sm-seo-head">
        <svg @class(['sm-seo-ring', "is-{$level}"]) viewBox="0 0 64 64" role="img" aria-label="Skor kelengkapan {{ $score }}%">
            <circle class="sm-seo-ring-track" cx="32" cy="32" r="26" />
            <circle
                class="sm-seo-ring-value"
                cx="32"
                cy="32"
                r="26"
                stroke-dasharray="{{ $circumference }}"
                stroke-dashoffset="{{ $circumference * (1 - $score / 100) }}"
            />
            <text x="32" y="32" dominant-baseline="central" text-anchor="middle">{{ $score }}%</text>
        </svg>
        <div>
            <p class="sm-seo-title">
                {{ $open->isEmpty() ? 'Semua pemeriksaan terpenuhi' : $completed->count().' dari '.$checks->count().' terpenuhi' }}
            </p>
            <p class="sm-seo-sub">
                {{ $open->isEmpty() ? 'Konten siap tampil optimal di Google.' : 'Selesaikan '.$open->count().' item berikut untuk melengkapi konten.' }}
            </p>
        </div>
    </div>

    @if ($open->isNotEmpty())
        <ul class="sm-seo-list">
            @foreach ($open as $check)
                <li>
                    <button
                        type="button"
                        class="sm-seo-item"
                        @if ($check['field'])
                            x-on:click="
                                const target = document.getElementById(@js('form.'.$check['field'])) ?? document.querySelector(@js('[id^="form.'.$check['field'].'"]'))
                                target?.scrollIntoView({ behavior: 'smooth', block: 'center' })
                                target?.focus?.({ preventScroll: true })
                            "
                        @endif
                    >
                        <x-filament::icon icon="heroicon-o-exclamation-circle" class="sm-seo-icon is-open" />
                        <span>{{ $check['text'] }}</span>
                        @if ($check['field'])
                            <x-filament::icon icon="heroicon-m-chevron-right" class="sm-seo-chevron" />
                        @endif
                    </button>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($completed->isNotEmpty())
        <button type="button" class="sm-seo-toggle" x-on:click="showCompleted = ! showCompleted" x-bind:aria-expanded="showCompleted">
            <x-filament::icon icon="heroicon-m-check-circle" class="sm-seo-icon is-done" />
            <span>Selesai ({{ $completed->count() }})</span>
            <x-filament::icon icon="heroicon-m-chevron-down" class="sm-seo-chevron" x-bind:class="{ 'is-flipped': showCompleted }" />
        </button>

        <ul class="sm-seo-list is-completed" x-show="showCompleted" x-collapse x-cloak>
            @foreach ($completed as $check)
                <li class="sm-seo-item">
                    <x-filament::icon icon="heroicon-m-check" class="sm-seo-icon is-done" />
                    <span>{{ $check['text'] }}</span>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($result['pending'] ?? false)
        <p class="sm-seo-sub sm-seo-note">Foto yang baru diunggah ikut dinilai setelah disimpan.</p>
    @endif
</div>
