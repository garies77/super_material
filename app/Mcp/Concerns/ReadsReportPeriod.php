<?php

namespace App\Mcp\Concerns;

use App\Support\Reports\ReportPeriod;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;

/** Argumen rentang tanggal laporan (WIB) yang sama untuk semua tool laporan. */
trait ReadsReportPeriod
{
    /** @return array<string, Type> */
    protected function periodSchema(JsonSchema $schema): array
    {
        return [
            'date_from' => $schema->string()->description('Tanggal awal Y-m-d (WIB). Bawaan 30 hari sebelum date_to.'),
            'date_to' => $schema->string()->description('Tanggal akhir Y-m-d (WIB). Bawaan hari ini.'),
        ];
    }

    /** @param  array<string, mixed>  $validated */
    protected function period(array $validated): ReportPeriod
    {
        return ReportPeriod::between($validated['date_from'] ?? null, $validated['date_to'] ?? null);
    }

    /** @return array{date_from: string, date_to: string, previous_from: string, previous_to: string} */
    protected function periodMeta(ReportPeriod $period): array
    {
        return [
            'date_from' => $period->from->toDateString(),
            'date_to' => $period->to->toDateString(),
            'previous_from' => $period->previous()->from->toDateString(),
            'previous_to' => $period->previous()->to->toDateString(),
        ];
    }

    /**
     * @param  array<string, array<int, mixed>>  $extraRules
     * @return array<string, mixed>
     */
    protected function validatedPeriodArguments(Request $request, array $extraRules = []): array
    {
        return $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            ...$extraRules,
        ]);
    }
}
