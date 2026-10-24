<?php

namespace App\Filament\Pages;

use App\Models\Ad;
use App\Models\AdEvent;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Laporan Kampanye: impresi, klik, CTR, dan penutupan pop-up per kampanye dan per posisi, dengan filter tanggal dan ekspor CSV. */
class AdReport extends Page
{
    protected string $view = 'filament.pages.ad-report';

    protected static ?string $title = 'Laporan kampanye';

    protected static ?string $slug = 'laporan-kampanye';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 2;

    public ?string $from = null;

    public ?string $until = null;

    public static function canAccess(): bool
    {
        return in_array(auth()->user()?->role, ['admin', 'editor'], true);
    }

    public function mount(): void
    {
        $this->from = now()->subDays(29)->toDateString();
        $this->until = now()->toDateString();
    }

    /** @return Collection<int, array{name: string, slot: string, impressions: int, clicks: int, ctr: string}> */
    public function byAd(): Collection
    {
        $names = Ad::pluck('name', 'id');

        return $this->aggregate(['ad_id', 'slot'])->map(fn ($r) => $this->row($r, [
            'name' => $names[$r->ad_id] ?? '#'.$r->ad_id,
            'slot' => Ad::SLOTS[$r->slot] ?? $r->slot,
        ]))->sortByDesc('impressions')->values();
    }

    /** @return Collection<int, array{slot: string, impressions: int, clicks: int, ctr: string}> */
    public function bySlot(): Collection
    {
        return $this->aggregate(['slot'])->map(fn ($r) => $this->row($r, [
            'slot' => Ad::SLOTS[$r->slot] ?? $r->slot,
        ]))->sortByDesc('impressions')->values();
    }

    public function export()
    {
        $names = Ad::pluck('name', 'id');
        $rows = $this->aggregate(['ad_id', 'slot', 'article_id'])->load('article');

        return response()->streamDownload(function () use ($rows, $names) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['dari', 'sampai', 'kampanye', 'posisi', 'halaman_asal', 'impresi', 'klik', 'ditutup', 'ctr_persen']);
            foreach ($rows as $r) {
                $row = $this->row($r, []);
                fputcsv($out, [
                    $this->from, $this->until, $names[$r->ad_id] ?? '#'.$r->ad_id, $r->slot,
                    $r->article?->url() ?? '', $row['impressions'], $row['clicks'], $row['closes'], $row['ctr_raw'],
                ]);
            }
            fclose($out);
        }, 'kampanye-'.now()->format('Ymd-His').'.csv');
    }

    /** Agregasi impresi dan klik manusia (bot dikecualikan) dalam rentang tanggal. */
    private function aggregate(array $groups): Collection
    {
        return AdEvent::query()
            ->select($groups)
            ->addSelect([
                DB::raw("SUM(CASE WHEN type = 'impression' THEN 1 ELSE 0 END) as impressions"),
                DB::raw("SUM(CASE WHEN type = 'click' THEN 1 ELSE 0 END) as clicks"),
                DB::raw("SUM(CASE WHEN type = 'close' THEN 1 ELSE 0 END) as closes"),
            ])
            ->where('is_bot', false)
            ->when($this->from, fn (Builder $q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($this->until, fn (Builder $q, $d) => $q->whereDate('created_at', '<=', $d))
            ->groupBy($groups)
            ->get();
    }

    private function row($r, array $extra): array
    {
        $imp = (int) $r->impressions;
        $clicks = (int) $r->clicks;
        $ctr = $imp ? round($clicks / $imp * 100, 2) : 0;

        return $extra + [
            'impressions' => $imp,
            'clicks' => $clicks,
            'closes' => (int) ($r->closes ?? 0),
            'ctr' => $imp ? number_format($ctr, 2, ',', '.').'%' : '—',
            'ctr_raw' => $ctr,
        ];
    }
}
