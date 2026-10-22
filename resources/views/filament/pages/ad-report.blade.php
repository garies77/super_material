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

    <x-filament::section heading="Per slot" description="Impresi dihitung saat ≥50% iklan terlihat selama 1 detik. Lalu lintas bot dikecualikan.">
        <table style="width:100%;font-size:.875rem;border-collapse:collapse">
            <thead><tr><th style="{{ $th }}">Slot</th><th style="{{ $th }};text-align:right">Impresi</th><th style="{{ $th }};text-align:right">Klik</th><th style="{{ $th }};text-align:right">CTR</th></tr></thead>
            <tbody>
                @forelse($this->bySlot() as $row)
                    <tr><td style="{{ $td }}">{{ $row['slot'] }}</td><td style="{{ $num }}">{{ number_format($row['impressions'], 0, ',', '.') }}</td><td style="{{ $num }}">{{ number_format($row['clicks'], 0, ',', '.') }}</td><td style="{{ $num }}">{{ $row['ctr'] }}</td></tr>
                @empty
                    <tr><td colspan="4" style="{{ $td }};opacity:.6">Belum ada data pada rentang ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-filament::section>

    <x-filament::section heading="Per iklan">
        <table style="width:100%;font-size:.875rem;border-collapse:collapse">
            <thead><tr><th style="{{ $th }}">Iklan</th><th style="{{ $th }}">Slot</th><th style="{{ $th }};text-align:right">Impresi</th><th style="{{ $th }};text-align:right">Klik</th><th style="{{ $th }};text-align:right">CTR</th></tr></thead>
            <tbody>
                @forelse($this->byAd() as $row)
                    <tr><td style="{{ $td }}">{{ $row['name'] }}</td><td style="{{ $td }}">{{ $row['slot'] }}</td><td style="{{ $num }}">{{ number_format($row['impressions'], 0, ',', '.') }}</td><td style="{{ $num }}">{{ number_format($row['clicks'], 0, ',', '.') }}</td><td style="{{ $num }}">{{ $row['ctr'] }}</td></tr>
                @empty
                    <tr><td colspan="5" style="{{ $td }};opacity:.6">Belum ada data pada rentang ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-filament::section>
</x-filament-panels::page>
