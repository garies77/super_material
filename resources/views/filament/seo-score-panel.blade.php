{{-- Panel skor SEO langsung (App\Filament\Support\SeoScorePanel). Gaya: .sm-seo-* di filament/admin-styles.blade.php --}}
@php
    $score = $result['score'];
    $checks = $result['checks'];
    $level = $score >= 80 ? 'good' : ($score >= 50 ? 'fair' : 'poor');
@endphp

<div class="sm-seo">
    <div class="sm-seo-head">
        <span @class(['sm-seo-score', "is-{$level}"])>{{ $score }}%</span>
        <span>
            <strong>Skor SEO</strong><br>
            <span class="sm-seo-sub">{{ count(array_filter($checks)) }} dari {{ count($checks) }} pemeriksaan terpenuhi</span>
        </span>
    </div>

    <ul class="sm-seo-list">
        @foreach ($checks as $label => $passed)
            <li @class(['is-passed' => $passed])>
                <span aria-hidden="true">{{ $passed ? '✓' : '✗' }}</span>
                {{ $label }}
            </li>
        @endforeach
    </ul>

    @if ($result['pending'] ?? false)
        <p class="sm-seo-sub">Foto yang baru diunggah ikut dinilai setelah disimpan.</p>
    @endif
</div>
