<?php

namespace App\Support\Reports;

use Illuminate\Support\Carbon;

/**
 * Rentang tanggal Pusat Laporan (WIB) beserta periode pembanding dengan panjang sama tepat sebelumnya.
 * Dibentuk dari filter halaman: 7d, 30d, this_month, last_month, atau custom (from/until).
 */
class ReportPeriod
{
    public const PRESETS = [
        '7d' => '7 hari terakhir',
        '30d' => '30 hari terakhir',
        'this_month' => 'Bulan ini',
        'last_month' => 'Bulan lalu',
        'custom' => 'Rentang tanggal sendiri',
    ];

    public function __construct(public Carbon $from, public Carbon $to) {}

    /** @param  array<string, mixed>|null  $filters */
    public static function fromFilters(?array $filters): self
    {
        $now = now(ClickReport::TIMEZONE);
        $preset = $filters['period'] ?? '30d';

        [$from, $to] = match ($preset) {
            '7d' => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
            'last_month' => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'custom' => [
                Carbon::parse($filters['from'] ?? $now->copy()->subDays(29), ClickReport::TIMEZONE)->startOfDay(),
                Carbon::parse($filters['until'] ?? $now, ClickReport::TIMEZONE)->endOfDay(),
            ],
            default => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
        };

        return $from->greaterThan($to) ? new self($to->copy()->startOfDay(), $from->copy()->endOfDay()) : new self($from, $to);
    }

    /** Periode sebelumnya dengan jumlah hari yang sama, berakhir tepat sebelum periode ini. */
    public function previous(): self
    {
        $days = $this->days();

        return new self($this->from->copy()->subDays($days)->startOfDay(), $this->from->copy()->subDay()->endOfDay());
    }

    public function days(): int
    {
        return (int) $this->from->copy()->startOfDay()->diffInDays($this->to->copy()->startOfDay()) + 1;
    }

    /** Batas dalam UTC untuk query kolom created_at. @return array{0: Carbon, 1: Carbon} */
    public function utcRange(): array
    {
        return [$this->from->copy()->utc(), $this->to->copy()->utc()];
    }

    /** Batas tanggal (Y-m-d, WIB) untuk tabel agregat harian. @return array{0: string, 1: string} */
    public function dateRange(): array
    {
        return [$this->from->toDateString(), $this->to->toDateString()];
    }

    public function label(): string
    {
        return $this->from->translatedFormat('d M Y').' – '.$this->to->translatedFormat('d M Y');
    }

    /** Perubahan persen terhadap pembanding; null bila pembanding 0 (tidak bermakna). */
    public static function change(int|float $current, int|float $previous): ?float
    {
        return $previous > 0 ? round(($current - $previous) / $previous * 100, 1) : null;
    }

    public function clickReport(string $groupBy = 'product'): ClickReport
    {
        return new ClickReport($this->from, $this->to, $groupBy);
    }
}
