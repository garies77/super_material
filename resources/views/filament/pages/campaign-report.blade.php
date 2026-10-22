@php
    $th = 'padding:.5rem .75rem;text-align:left;font-weight:600;border-bottom:1px solid rgba(127,127,127,.25)';
    $td = 'padding:.5rem .75rem;border-bottom:1px solid rgba(127,127,127,.12)';
    $num = $td.';text-align:right;font-variant-numeric:tabular-nums';
    $fmt = fn (int $n) => number_format($n, 0, ',', '.');
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

    <x-filament::section heading="Per banner" description="Impresi dicatat saat modal tampil; tingkat tutup = penutupan ÷ impresi. Tanggal dalam WIB, lalu lintas bot dikecualikan.">
        <table style="width:100%;font-size:.875rem;border-collapse:collapse">
            <thead><tr>
                <th style="{{ $th }}">Kampanye</th>
                <th style="{{ $th }};text-align:right">Impresi</th>
                <th style="{{ $th }};text-align:right">Klik</th>
                <th style="{{ $th }};text-align:right">CTR</th>
                <th style="{{ $th }};text-align:right">Tutup</th>
                <th style="{{ $th }};text-align:right">Tingkat tutup</th>
            </tr></thead>
            <tbody>
                @forelse($this->rows() as $row)
                    <tr>
                        <td style="{{ $td }}">{{ $row['name'] }}</td>
                        <td style="{{ $num }}">{{ $fmt($row['impressions']) }}</td>
                        <td style="{{ $num }}">{{ $fmt($row['clicks']) }}</td>
                        <td style="{{ $num }}">{{ $row['ctr'] }}</td>
                        <td style="{{ $num }}">{{ $fmt($row['closes']) }}</td>
                        <td style="{{ $num }}">{{ $row['close_rate'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="{{ $td }};opacity:.6">Belum ada data pada rentang ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-filament::section>
</x-filament-panels::page>
