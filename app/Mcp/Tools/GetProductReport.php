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

#[Description('Laporan produk: produk paling banyak diklik ke marketplace (rincian kanal & perubahan) dan konversi tayangan → klik per produk.')]
#[IsReadOnly]
class GetProductReport extends AbilityTool
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
            'top_clicked' => ReportMetrics::topProducts($period, $limit)->values()->all(),
            'conversion' => ReportMetrics::productConversion($period, $limit)->values()->all(),
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
