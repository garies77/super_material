{{--
    Panel misi kelengkapan (App\Filament\Support\SeoScorePanel). Gaya: .sm-seo-* di filament/admin-styles.blade.php.
    wire:key berubah setiap skor berubah agar x-init berjalan lagi; confetti (window.smConfetti, filament/celebrate)
    hanya diputar saat skor NAIK menjadi 100% selama mengedit, bukan saat halaman dibuka sudah 100%.
--}}
@php
    $score = $result['score'];
    $checks = $result['checks'];
    $done = count(array_filter($checks));
    $total = count($checks);
    $left = $total - $done;
    $level = $score >= 80 ? 'good' : ($score >= 50 ? 'fair' : 'poor');
@endphp

<div
    class="sm-seo"
    wire:key="sm-seo-{{ $score }}"
    x-data
    x-init="
        const key = window.location.pathname
        const last = (window.smSeoLast ??= {})[key]
        if (last !== undefined && last < 100 && {{ $score }} === 100) window.smConfetti?.()
        window.smSeoLast[key] = {{ $score }}
    "
>
    <div class="sm-seo-head">
        <span @class(['sm-seo-score', "is-{$level}"])>{{ $score }}%</span>
        <span>
            @if ($left === 0)
                <strong>🎉 Semua misi selesai!</strong><br>
                <span class="sm-seo-sub">Siap bersaing di Google.</span>
            @else
                <strong>{{ $done }}/{{ $total }} misi selesai</strong><br>
                <span class="sm-seo-sub">Tinggal {{ $left }} lagi{{ $left <= 2 ? ', hampir sempurna!' : '.' }}</span>
            @endif
        </span>
    </div>

    <div class="sm-seo-progress" role="progressbar" aria-valuenow="{{ $score }}" aria-valuemin="0" aria-valuemax="100">
        <span @class(["is-{$level}"]) style="width: {{ $score }}%"></span>
    </div>

    <ul class="sm-seo-list">
        @foreach ($checks as $label => $passed)
            @php([$text, $field] = $missions[$label] ?? [$label, null])
            <li @class(['is-passed' => $passed])>
                @if ($passed || ! $field)
                    <span class="sm-seo-mark" aria-hidden="true">{{ $passed ? '✓' : '○' }}</span>
                    <span>{{ $text }}</span>
                @else
                    <button
                        type="button"
                        class="sm-seo-goto"
                        x-on:click="
                            const target = document.getElementById(@js('form.'.$field)) ?? document.querySelector(@js('[id^="form.'.$field.'"]'))
                            target?.scrollIntoView({ behavior: 'smooth', block: 'center' })
                            target?.focus?.({ preventScroll: true })
                        "
                    >
                        <span class="sm-seo-mark" aria-hidden="true">○</span>
                        <span>{{ $text }}</span>
                    </button>
                @endif
            </li>
        @endforeach
    </ul>

    @if ($result['pending'] ?? false)
        <p class="sm-seo-sub">Foto yang baru diunggah ikut dinilai setelah disimpan.</p>
    @endif
</div>
