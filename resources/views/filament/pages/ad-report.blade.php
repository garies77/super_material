@php
    $th = 'padding:.5rem .75rem;text-align:left;font-weight:600;border-bottom:1px solid rgba(127,127,127,.25)';
    $td = 'padding:.5rem .75rem;border-bottom:1px solid rgba(127,127,127,.12)';
    $num = $td.';text-align:right;font-variant-numeric:tabular-nums';
@endphp
<x-filament-panels::page>
    <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-end">
        <label style="display:grid;gap:.25rem;font-size:.875rem">
            Dari
            <x-filament::input.wrapper>
                <x-filament::input type="date" wire:model.live="from" />
            </x-filament::input.wrapper>
        </label>
        <label style="display:grid;gap:.25rem;font-size:.875rem">
            Sampai
            <x-filament::input.wrapper>
                <x-filament::input type="date" wire:model.live="until" />
            </x-filament::input.wrapper>
        </label>
        <x-filament::button wire:click="export" icon="heroicon-o-arrow-down-tray" color="gray">Ekspor CSV</x-filament::button>
    </div>

    <x-filament::section heading="Per posisi" description="Impresi banner/sidebar dihitung saat ≥50% terlihat selama 1 detik; pop-up saat dibuka. Lalu lintas bot dikecualikan.">
        <table style="width:100%;font-size:.875rem;border-collapse:collapse">
            <thead><tr><th style="{{ $th }}">Posisi</th><th style="{{ $th }};text-align:right">Impresi</th><th style="{{ $th }};text-align:right">Klik</th><th style="{{ $th }};text-align:right">Ditutup</th><th style="{{ $th }};text-align:right">CTR</th></tr></thead>
            <tbody>
                @forelse($this->bySlot() as $row)
                    <tr><td style="{{ $td }}">{{ $row['slot'] }}</td><td style="{{ $num }}">{{ number_format($row['impressions'], 0, ',', '.') }}</td><td style="{{ $num }}">{{ number_format($row['clicks'], 0, ',', '.') }}</td><td style="{{ $num }}">{{ $row['closes'] ?: '—' }}</td><td style="{{ $num }}">{{ $row['ctr'] }}</td></tr>
                @empty
                    <tr><td colspan="5" style="{{ $td }};opacity:.6">Belum ada data pada rentang ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-filament::section>

    <x-filament::section heading="Per kampanye">
        <table style="width:100%;font-size:.875rem;border-collapse:collapse">
            <thead><tr><th style="{{ $th }}">Kampanye</th><th style="{{ $th }}">Posisi</th><th style="{{ $th }};text-align:right">Impresi</th><th style="{{ $th }};text-align:right">Klik</th><th style="{{ $th }};text-align:right">Ditutup</th><th style="{{ $th }};text-align:right">CTR</th></tr></thead>
            <tbody>
                @forelse($this->byAd() as $row)
                    <tr><td style="{{ $td }}">{{ $row['name'] }}</td><td style="{{ $td }}">{{ $row['slot'] }}</td><td style="{{ $num }}">{{ number_format($row['impressions'], 0, ',', '.') }}</td><td style="{{ $num }}">{{ number_format($row['clicks'], 0, ',', '.') }}</td><td style="{{ $num }}">{{ $row['closes'] ?: '—' }}</td><td style="{{ $num }}">{{ $row['ctr'] }}</td></tr>
                @empty
                    <tr><td colspan="6" style="{{ $td }};opacity:.6">Belum ada data pada rentang ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-filament::section>
</x-filament-panels::page>
