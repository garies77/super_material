<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\ReadsReportPeriod;
use App\Support\Reports\ReportSummary;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Ringkasan kinerja situs dari Pusat Laporan: tayangan, klik ke marketplace, prospek, perubahan dibanding periode sebelumnya, produk & pencarian teratas, tim konten, dan kesehatan katalog. Bot tidak dihitung.')]
#[IsReadOnly]
class GetReportSummary extends AbilityTool
{
    use ReadsReportPeriod;

    protected string $ability = 'reports:read';

    public function handle(Request $request): ResponseFactory
    {
        $period = $this->period($this->validatedPeriodArguments($request));

        return Response::structured(['period' => $this->periodMeta($period), 'summary' => ReportSummary::build($period, 10)]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return $this->periodSchema($schema);
    }
}
