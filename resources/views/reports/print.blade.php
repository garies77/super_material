{{-- Ringkasan Pusat Laporan siap cetak (A4). Simpan sebagai PDF lewat dialog cetak browser. --}}
@php
    use App\Models\Setting;
    use App\Support\Reports\CatalogHealth;
    use App\Support\Reports\ReportSummary;
    use App\Support\Reports\TeamMetrics;

    $number = fn (int|float $value) => number_format($value, 0, ',', '.');
    $tone = fn (?float $change) => $change === null || $change == 0 ? 'flat' : ($change > 0 ? 'up' : 'down');
    $team = $summary['team'];
    $catalog = $summary['catalog'];
    $brand = Setting::get('site_brand_name', 'Supermaterial');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Laporan {{ $brand }} {{ $summary['period']['from'] }} s.d. {{ $summary['period']['to'] }}</title>
    <style>
        @page { size: A4; margin: 14mm 14mm 16mm; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f1f5f9; color: #0f172a; font: 13px/1.5 ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .toolbar { position: sticky; top: 0; z-index: 1; display: flex; align-items: center; gap: 12px; padding: 12px 24px; background: #0f172a; color: #e2e8f0; font-size: 13px; }
        .toolbar button { margin-left: auto; border: 0; border-radius: 8px; padding: 8px 16px; background: #f59309; color: #fff; font: 600 13px/1 inherit; cursor: pointer; }
        .sheet { width: 210mm; min-height: 297mm; margin: 24px auto; padding: 14mm; background: #fff; box-shadow: 0 1px 3px rgb(15 23 42 / .12); }
        header { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; padding-bottom: 14px; border-bottom: 3px solid #f59309; }
        .brand { font-size: 13px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: #f59309; }
        h1 { margin: 2px 0 0; font-size: 24px; line-height: 1.2; }
        .meta { text-align: right; color: #64748b; font-size: 12px; }
        .meta strong { display: block; color: #0f172a; font-size: 14px; }
        h2 { margin: 22px 0 8px; font-size: 14px; text-transform: uppercase; letter-spacing: .05em; color: #334155; }
        .kpis { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-top: 18px; }
        .kpi { padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px; }
        .kpi .label { color: #64748b; font-size: 12px; }
        .kpi .value { margin-top: 2px; font-size: 22px; font-weight: 700; }
        .change { font-size: 12px; font-weight: 600; }
        .up { color: #15803d; } .down { color: #b91c1c; } .flat { color: #64748b; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 6px 8px; border-bottom: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
        th { color: #64748b; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        .cols { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .facts { margin: 0; padding: 0; list-style: none; }
        .facts li { display: flex; justify-content: space-between; gap: 12px; padding: 6px 0; border-bottom: 1px solid #e2e8f0; }
        .facts li span:last-child { font-weight: 600; text-align: right; }
        .muted { color: #64748b; }
        .tag { display: inline-block; margin: 0 4px 4px 0; padding: 2px 8px; border-radius: 999px; background: #fef3c7; color: #92400e; font-size: 12px; }
        footer { margin-top: 26px; padding-top: 10px; border-top: 1px solid #e2e8f0; color: #94a3b8; font-size: 11px; }
        section { break-inside: avoid; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <span>Pratinjau cetak. Untuk PDF, pilih tujuan <strong>"Simpan sebagai PDF"</strong> di dialog cetak.</span>
        <button type="button" id="print-report">Cetak / Simpan PDF</button>
    </div>

    <main class="sheet">
        <header>
            <div>
                <div class="brand">{{ $brand }}</div>
                <h1>Laporan Kinerja</h1>
            </div>
            <div class="meta">
                <strong>{{ $summary['period']['label'] }}</strong>
                Dibanding periode yang sama sebelumnya · bot tidak dihitung
            </div>
        </header>

        <div class="kpis">
            @foreach ($summary['kpis'] as $kpi)
                <div class="kpi">
                    <div class="label">{{ $kpi['label'] }}</div>
                    <div class="value">{{ $number($kpi['value']) }}</div>
                    <div class="change {{ $tone($kpi['change']) }}">{{ ReportSummary::formatChange($kpi['change'], $kpi['previous'], $kpi['value']) }} <span class="muted">vs {{ $number($kpi['previous']) }}</span></div>
                </div>
            @endforeach
        </div>

        <section>
            <h2>Produk paling banyak diklik</h2>
            @if ($summary['top_products'])
                <table>
                    <thead><tr><th>#</th><th>Produk</th><th class="num">Klik</th><th class="num">Perubahan</th></tr></thead>
                    <tbody>
                        @foreach ($summary['top_products'] as $i => $product)
                            <tr>
                                <td class="muted">{{ $i + 1 }}</td>
                                <td>{{ $product['name'] }}</td>
                                <td class="num"><strong>{{ $number($product['clicks']) }}</strong></td>
                                <td class="num change {{ $tone($product['change']) }}">{{ ReportSummary::formatChange($product['change'], 0, $product['clicks']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="muted">Belum ada klik di periode ini.</p>
            @endif
        </section>

        <div class="cols">
            <section>
                <h2>Pencarian terpopuler</h2>
                @if ($summary['top_searches'])
                    <table>
                        <thead><tr><th>Kata kunci</th><th class="num">Dicari</th><th class="num">Hasil</th></tr></thead>
                        <tbody>
                            @foreach ($summary['top_searches'] as $search)
                                <tr>
                                    <td>{{ $search['term'] }}</td>
                                    <td class="num">{{ $number($search['searches']) }}</td>
                                    <td class="num {{ $search['results'] === 0 ? 'down' : 'muted' }}">{{ $number($search['results']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="muted">Belum ada pencarian di periode ini.</p>
                @endif

                @if ($summary['zero_result_searches'])
                    <p style="margin: 12px 0 4px"><strong>Dicari tapi tidak ketemu</strong></p>
                    @foreach ($summary['zero_result_searches'] as $search)
                        <span class="tag">{{ $search['term'] }} ({{ $number($search['searches']) }}×)</span>
                    @endforeach
                @endif
            </section>

            <section>
                <h2>Tim konten</h2>
                <ul class="facts">
                    <li><span>Kiriman Kontributor</span><span>{{ $number($team['submissions']) }}</span></li>
                    <li><span>Disetujui</span><span>{{ $number($team['approved']) }}</span></li>
                    <li><span>Tepat sekali (tanpa perbaikan)</span><span>{{ $team['first_time_right'] === null ? '—' : number_format($team['first_time_right'], 0, ',', '.').'%' }}</span></li>
                    <li><span>Median waktu tunggu tinjauan</span><span>{{ TeamMetrics::formatHours($team['median_wait_hours']) }}</span></li>
                    <li><span>Antrean tinjauan saat ini</span><span>{{ $number($team['queue']) }}</span></li>
                </ul>

                <h2>Kesehatan katalog</h2>
                <ul class="facts">
                    <li><span>Produk lengkap</span><span>{{ $number($catalog['complete']) }} dari {{ $number($catalog['products']) }}</span></li>
                    @foreach (collect($catalog['issues'])->sortDesc() as $issue => $count)
                        <li><span>{{ CatalogHealth::ISSUES[$issue] }}</span><span class="{{ $count > 0 ? '' : 'up' }}">{{ $number($count) }}</span></li>
                    @endforeach
                </ul>
            </section>
        </div>

        <footer>
            Dibuat {{ $generatedAt->translatedFormat('d M Y H:i') }} WIB oleh {{ $author }} dari Pusat Laporan {{ $brand }}.
            Angka berbasis data agregat tanpa data pribadi pengunjung.
        </footer>
    </main>

    <script nonce="{{ Vite::cspNonce() }}">
        document.getElementById('print-report').addEventListener('click', function () { window.print(); });
    </script>
</body>
</html>
