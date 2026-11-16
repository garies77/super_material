<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ReadsReportPeriod;
use App\Support\Reports\ClickReport;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Jumlah klik ke marketplace/WhatsApp dikelompokkan per produk, kategori, artikel asal, kanal, atau hari. Bisa difilter per kanal dan kategori. Agregat tanpa data pribadi.')]
#[IsReadOnly]
class GetClickReport extends AbilityTool
{
    use ReadsReportPeriod;

    protected string $ability = 'reports:read';

    public function handle(Request $request): ResponseFactory
    {
        $validated = $this->validatedPeriodArguments($request, [
            'group_by' => ['nullable', Rule::in(ClickReport::GROUPS)],
            'channel' => ['nullable', 'string', Rule::exists('sales_channels', 'slug')],
            'category' => ['nullable', 'string', Rule::exists('categories', 'slug')],
            'include_bots' => ['nullable', 'boolean'],
        ]);
        $period = $this->period($validated);

        $report = new ClickReport(
            from: $period->from,
            to: $period->to,
            groupBy: $validated['group_by'] ?? 'product',
            channel: $validated['channel'] ?? null,
            category: $validated['category'] ?? null,
            includeBots: (bool) ($validated['include_bots'] ?? false),
        );

        return Response::structured([
            'date_from' => $report->from->toDateString(),
            'date_to' => $report->to->toDateString(),
            'group_by' => $report->groupBy,
            'include_bots' => $report->includeBots,
            'total_clicks' => $report->totalClicks(),
            'rows' => $report->rows()->all(),
        ]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->periodSchema($schema),
            'group_by' => $schema->string()->enum(ClickReport::GROUPS)->default('product')->description('Pengelompokan baris.'),
            'channel' => $schema->string()->description('Slug kanal penjualan, mis. shopee atau whatsapp.'),
            'category' => $schema->string()->description('Slug kategori produk.'),
            'include_bots' => $schema->boolean()->default(false)->description('Ikut hitung klik bot.'),
        ];
    }
}
