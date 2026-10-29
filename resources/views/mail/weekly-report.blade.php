@php
    use App\Support\Reports\CatalogHealth;
    use App\Support\Reports\ReportSummary;
    use App\Support\Reports\TeamMetrics;

    $number = fn (int|float $value) => number_format($value, 0, ',', '.');
    $team = $summary['team'];
    $catalog = $summary['catalog'];
@endphp
<x-mail::message>
# Laporan mingguan

Periode **{{ $summary['period']['label'] }}**, dibanding minggu sebelumnya. Kunjungan bot tidak dihitung.

<x-mail::table>
| Angka utama | Minggu ini | Perubahan |
|:--|--:|--:|
@foreach ($summary['kpis'] as $kpi)
| {{ $kpi['label'] }} | **{{ $number($kpi['value']) }}** | {{ ReportSummary::formatChange($kpi['change'], $kpi['previous'], $kpi['value']) }} |
@endforeach
</x-mail::table>

## Produk paling banyak diklik

@if ($summary['top_products'])
<x-mail::table>
| Produk | Klik | Perubahan |
|:--|--:|--:|
@foreach ($summary['top_products'] as $product)
| {{ $product['name'] }} | {{ $number($product['clicks']) }} | {{ ReportSummary::formatChange($product['change'], 0, $product['clicks']) }} |
@endforeach
</x-mail::table>
@else
Belum ada klik minggu ini.
@endif

## Yang dicari pengunjung

@if ($summary['top_searches'])
{{ collect($summary['top_searches'])->map(fn ($s) => $s['term'].' ('.$number($s['searches']).'×)')->implode(', ') }}.
@else
Belum ada pencarian minggu ini.
@endif

@if ($summary['zero_result_searches'])
**Dicari tapi tidak ketemu:** {{ collect($summary['zero_result_searches'])->pluck('term')->implode(', ') }}. Pertimbangkan menambah produk/artikel atau istilah ini ke deskripsi.
@endif

## Tim & katalog

- Kiriman Kontributor: **{{ $number($team['submissions']) }}**, disetujui **{{ $number($team['approved']) }}**{{ $team['first_time_right'] !== null ? ' ('.number_format($team['first_time_right'], 0, ',', '.').'% tepat sekali)' : '' }}.
- Median waktu tunggu tinjauan: **{{ TeamMetrics::formatHours($team['median_wait_hours']) }}**. Antrean saat ini: **{{ $number($team['queue']) }}**.
- Produk lengkap: **{{ $number($catalog['complete']) }} dari {{ $number($catalog['products']) }}**.
@foreach (collect($catalog['issues'])->filter()->sortDesc()->take(3) as $issue => $count)
- {{ CatalogHealth::ISSUES[$issue] }}: {{ $number($count) }} produk.
@endforeach

<x-mail::button :url="$reportUrl">
Buka Pusat Laporan
</x-mail::button>

Email ini dikirim otomatis setiap Senin pagi. Penerima diatur di Pusat Laporan → Laporan mingguan.
</x-mail::message>
