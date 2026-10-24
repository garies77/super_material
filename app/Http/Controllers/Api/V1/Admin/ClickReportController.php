<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\ApiController;
use App\Support\Reports\ClickReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** @tags Admin: Laporan */
class ClickReportController extends ApiController
{
    protected bool $admin = true;

    private const MAX_RANGE_DAYS = 366;

    /**
     * Ringkasan klik
     *
     * Ability: reports:read. Data agregat tanpa data pribadi. Bawaan 30 hari terakhir (tanggal WIB),
     * klik bot tidak dihitung.
     *
     * @queryParam filter[date_from] string Tanggal awal Y-m-d.
     * @queryParam filter[date_to] string Tanggal akhir Y-m-d.
     * @queryParam filter[channel] string Slug kanal.
     * @queryParam filter[category] string Slug kategori, termasuk subkategori.
     * @queryParam filter[include_bots] bool Sertakan klik bot.
     * @queryParam group_by string product, category, article, channel, atau day. Bawaan product.
     */
    public function index(Request $request): JsonResponse
    {
        $report = $this->report($request);

        return response()->json([
            'data' => $report->rows()->all(),
            'meta' => $this->meta($report),
        ]);
    }

    /**
     * Ekspor CSV ringkasan klik
     *
     * Ability: reports:read. Parameter sama dengan ringkasan klik.
     */
    public function export(Request $request): StreamedResponse
    {
        $report = $this->report($request);
        $rows = $report->rows();
        $filename = sprintf('laporan-klik-%s-%s-%s.csv', $report->groupBy, $report->from->toDateString(), $report->to->toDateString());

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            if ($rows->isNotEmpty()) {
                fputcsv($out, array_keys($rows->first()), ',', '"', '');
            }
            foreach ($rows as $row) {
                fputcsv($out, array_values($row), ',', '"', '');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function report(Request $request): ClickReport
    {
        $validated = $request->validate([
            'filter' => ['sometimes', 'array'],
            'filter.date_from' => ['sometimes', 'date_format:Y-m-d'],
            'filter.date_to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:filter.date_from'],
            'filter.channel' => ['sometimes', 'string', Rule::exists('sales_channels', 'slug')],
            'filter.category' => ['sometimes', 'string', Rule::exists('categories', 'slug')],
            'filter.include_bots' => ['sometimes', 'boolean'],
            'group_by' => ['sometimes', Rule::in(ClickReport::GROUPS)],
        ]);
        $filters = $validated['filter'] ?? [];

        $tz = ClickReport::TIMEZONE;
        $to = isset($filters['date_to']) ? Carbon::parse($filters['date_to'], $tz)->endOfDay() : now($tz)->endOfDay();
        $from = isset($filters['date_from']) ? Carbon::parse($filters['date_from'], $tz)->startOfDay() : $to->copy()->subDays(29)->startOfDay();

        if ($from->diffInDays($to) > self::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages(['filter.date_from' => ['Rentang tanggal maksimal '.self::MAX_RANGE_DAYS.' hari.']]);
        }

        return new ClickReport(
            from: $from,
            to: $to,
            groupBy: $validated['group_by'] ?? 'product',
            channel: $filters['channel'] ?? null,
            category: $filters['category'] ?? null,
            includeBots: filter_var($filters['include_bots'] ?? false, FILTER_VALIDATE_BOOLEAN),
        );
    }

    /** @return array<string, mixed> */
    private function meta(ClickReport $report): array
    {
        return [
            'date_from' => $report->from->toDateString(),
            'date_to' => $report->to->toDateString(),
            'group_by' => $report->groupBy,
            'include_bots' => $report->includeBots,
            'total_clicks' => $report->totalClicks(),
        ];
    }
}
