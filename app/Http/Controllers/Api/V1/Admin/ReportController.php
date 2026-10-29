<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Support\Reports\ClickReport;
use App\Support\Reports\ReportMetrics;
use App\Support\Reports\ReportPeriod;
use App\Support\Reports\ReportSummary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Angka Pusat Laporan lewat API (sumber sama dengan panel dan email mingguan). Semua agregat tanpa data pribadi,
 * bot tidak dihitung, bawaan 30 hari terakhir (tanggal WIB), dan dibanding periode yang sama sebelumnya.
 *
 * @tags Admin: Laporan
 */
class ReportController extends ApiController
{
    protected bool $admin = true;

    private const MAX_RANGE_DAYS = 366;

    /**
     * Ringkasan kinerja
     *
     * Ability: reports:read. Angka utama dengan perubahan, produk & pencarian teratas, tim konten, dan kesehatan katalog.
     *
     * @queryParam filter[date_from] string Tanggal awal Y-m-d.
     * @queryParam filter[date_to] string Tanggal akhir Y-m-d.
     */
    public function summary(Request $request): JsonResponse
    {
        $period = $this->period($request);

        return response()->json(['data' => ReportSummary::build($period, 10), 'meta' => $this->meta($period)]);
    }

    /**
     * Laporan produk
     *
     * Ability: reports:read. Produk paling banyak diklik (rincian kanal & perubahan) dan konversi tayangan → klik.
     *
     * @queryParam filter[date_from] string Tanggal awal Y-m-d.
     * @queryParam filter[date_to] string Tanggal akhir Y-m-d.
     * @queryParam limit int Jumlah baris per daftar, 1–100. Bawaan 20.
     */
    public function products(Request $request): JsonResponse
    {
        $period = $this->period($request);
        $limit = $this->limit($request);

        return response()->json([
            'data' => [
                'top_clicked' => ReportMetrics::topProducts($period, $limit)->values()->all(),
                'conversion' => ReportMetrics::productConversion($period, $limit)->values()->all(),
            ],
            'meta' => $this->meta($period),
        ]);
    }

    /**
     * Sumber trafik
     *
     * Ability: reports:read. Tayangan harian, halaman asal klik, kampanye UTM, artikel, dan pencarian pengunjung.
     *
     * @queryParam filter[date_from] string Tanggal awal Y-m-d.
     * @queryParam filter[date_to] string Tanggal akhir Y-m-d.
     * @queryParam limit int Jumlah baris per daftar, 1–100. Bawaan 20.
     */
    public function traffic(Request $request): JsonResponse
    {
        $period = $this->period($request);
        $limit = $this->limit($request);

        return response()->json([
            'data' => [
                'daily_views' => ReportMetrics::dailyViews($period),
                'click_origins' => ReportMetrics::clickOrigins($period)->values()->all(),
                'utm_campaigns' => ReportMetrics::utmCampaigns($period)->take($limit)->values()->all(),
                'articles' => ReportMetrics::articlePerformance($period, $limit)->values()->all(),
                'top_searches' => ReportMetrics::topSearches($period, $limit)->values()->all(),
                'zero_result_searches' => ReportMetrics::zeroResultSearches($period, $limit)->values()->all(),
            ],
            'meta' => $this->meta($period),
        ]);
    }

    private function period(Request $request): ReportPeriod
    {
        $filters = $request->validate([
            'filter' => ['sometimes', 'array'],
            'filter.date_from' => ['sometimes', 'date_format:Y-m-d'],
            'filter.date_to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:filter.date_from'],
        ])['filter'] ?? [];

        $tz = ClickReport::TIMEZONE;
        $to = isset($filters['date_to']) ? Carbon::parse($filters['date_to'], $tz)->endOfDay() : now($tz)->endOfDay();
        $from = isset($filters['date_from']) ? Carbon::parse($filters['date_from'], $tz)->startOfDay() : $to->copy()->subDays(29)->startOfDay();

        if ($from->diffInDays($to) > self::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages(['filter.date_from' => ['Rentang tanggal maksimal '.self::MAX_RANGE_DAYS.' hari.']]);
        }

        return new ReportPeriod($from, $to);
    }

    private function limit(Request $request): int
    {
        return (int) ($request->validate(['limit' => ['sometimes', 'integer', 'between:1,100']])['limit'] ?? 20);
    }

    /** @return array{date_from: string, date_to: string, previous_from: string, previous_to: string} */
    private function meta(ReportPeriod $period): array
    {
        return [
            'date_from' => $period->from->toDateString(),
            'date_to' => $period->to->toDateString(),
            'previous_from' => $period->previous()->from->toDateString(),
            'previous_to' => $period->previous()->to->toDateString(),
        ];
    }
}
