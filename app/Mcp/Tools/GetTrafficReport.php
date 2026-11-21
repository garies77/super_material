<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ReadsReportPeriod;
use App\Support\Reports\ReportMetrics;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Sumber trafik: tayangan harian, halaman asal klik, kampanye UTM, kinerja artikel, pencarian pengunjung teratas, dan pencarian tanpa hasil (ide konten/produk baru).')]
#[IsReadOnly]
class GetTrafficReport extends AbilityTool
{
    use ReadsReportPeriod;

    protected string $ability = 'reports:read';

    public function handle(Request $request): ResponseFactory
    {
        $validated = $this->validatedPeriodArguments($request, ['limit' => ['nullable', 'integer', 'between:1,100']]);
        $period = $this->period($validated);
        $limit = $validated['limit'] ?? 20;

        return Response::structured([
            'period' => $this->periodMeta($period),
            'daily_views' => ReportMetrics::dailyViews($period),
            'click_origins' => ReportMetrics::clickOrigins($period)->values()->all(),
            'utm_campaigns' => ReportMetrics::utmCampaigns($period)->take($limit)->values()->all(),
            'articles' => ReportMetrics::articlePerformance($period, $limit)->values()->all(),
            'top_searches' => ReportMetrics::topSearches($period, $limit)->values()->all(),
            'zero_result_searches' => ReportMetrics::zeroResultSearches($period, $limit)->values()->all(),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->periodSchema($schema),
            'limit' => $schema->integer()->min(1)->max(100)->default(20)->description('Jumlah baris per daftar, 1–100.'),
        ];
    }
}
