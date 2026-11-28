<?php

namespace App\Mcp\Prompts;

use App\Mcp\Concerns\RequiresAbility;
use App\Models\Setting;
use App\Support\Reports\ClickReport;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Prompt;

#[Name('laporan-mingguan')]
#[Description('Susun laporan mingguan situs (7 hari terakhir dibanding 7 hari sebelumnya): trafik, klik tombol beli, produk teratas, dan 3 rekomendasi tindakan.')]
class WeeklyReportPrompt extends Prompt
{
    use RequiresAbility;

    /** @var list<string> */
    protected array $abilities = ['reports:read'];

    public function handle(Request $request): Response
    {
        $today = now(ClickReport::TIMEZONE)->startOfDay();
        $thisWeek = [$today->copy()->subDays(6)->toDateString(), $today->toDateString()];
        $lastWeek = [$today->copy()->subDays(13)->toDateString(), $today->copy()->subDays(7)->toDateString()];
        $brand = Setting::brandName();

        return Response::text(<<<MD
        Tugas: buat laporan mingguan {$brand} untuk pemilik toko (bukan orang teknis).

        Periode minggu ini: {$thisWeek[0]} s/d {$thisWeek[1]} (WIB). Pembanding: {$lastWeek[0]} s/d {$lastWeek[1]}.

        Data yang diambil (pakai tanggal di atas untuk kedua periode):
        1. get-report-summary: pengunjung, tampilan halaman, klik tombol beli.
        2. get-traffic-report: sumber pengunjung dan halaman terpopuler.
        3. get-click-report: klik per kanal (Shopee, Tokopedia, WhatsApp, dll.) dan produk yang paling banyak diklik.
        4. get-product-report: produk dengan tampilan tinggi tapi klik rendah.
        5. Bila tool audit-catalog tersedia, sebutkan 3 kekurangan katalog terbanyak dari issue_counts.

        Format jawaban:
        - Ringkasan 3 kalimat: naik atau turun dibanding minggu lalu, dengan persen.
        - Tabel singkat angka utama (minggu ini, minggu lalu, perubahan).
        - 5 produk teratas berdasarkan klik.
        - 3 rekomendasi tindakan yang bisa dikerjakan minggu ini, masing-masing dengan alasan dari data (mis. "lengkapi foto produk X yang dilihat 200 kali tapi hanya 2 klik").
        Jangan mengarang angka; bila sebuah laporan kosong, katakan datanya belum ada.
        MD);
    }
}
