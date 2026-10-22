<?php

namespace App\Filament\Pages;

use App\Models\Campaign;
use App\Models\CampaignEvent;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Laporan banner kampanye: impresi, klik, CTR, dan tingkat penutupan per banner (PRD 8.9). */
class CampaignReport extends Page
{
    protected string $view = 'filament.pages.campaign-report';

    protected static ?string $title = 'Laporan kampanye';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartPie;

    protected static string|\UnitEnum|null $navigationGroup = 'Laporan';

    public ?string $from = null;

    public ?string $until = null;

    public static function canAccess(): bool
    {
        return in_array(auth()->user()?->role, ['admin', 'editor'], true);
    }

    public function mount(): void
    {
        $this->from = now('Asia/Jakarta')->subDays(29)->toDateString();
        $this->until = now('Asia/Jakarta')->toDateString();
    }

    /** @return Collection<int, array{name: string, impressions: int, clicks: int, closes: int, ctr: string, close_rate: string}> */
    public function rows(): Collection
    {
        $names = Campaign::pluck('name', 'id');

        return CampaignEvent::query()
            ->select('campaign_id')
            ->addSelect([
                DB::raw("SUM(CASE WHEN type = 'impression' THEN 1 ELSE 0 END) as impressions"),
                DB::raw("SUM(CASE WHEN type = 'click' THEN 1 ELSE 0 END) as clicks"),
                DB::raw("SUM(CASE WHEN type = 'close' THEN 1 ELSE 0 END) as closes"),
            ])
            ->where('is_bot', false)
            // Filter tanggal dalam WIB; created_at disimpan UTC
            ->when($this->from, fn (Builder $q, $d) => $q->where('created_at', '>=', $this->utc($d)))
            ->when($this->until, fn (Builder $q, $d) => $q->where('created_at', '<', $this->utc($d, 1)))
            ->groupBy('campaign_id')
            ->get()
            ->map(function ($r) use ($names) {
                $imp = (int) $r->impressions;
                $pct = fn (int $n) => $imp ? round($n / $imp * 100, 2) : 0;

                return [
                    'name' => $names[$r->campaign_id] ?? '#'.$r->campaign_id,
                    'impressions' => $imp,
                    'clicks' => (int) $r->clicks,
                    'closes' => (int) $r->closes,
                    'ctr_raw' => $pct((int) $r->clicks),
                    'close_rate_raw' => $pct((int) $r->closes),
                    'ctr' => $imp ? number_format($pct((int) $r->clicks), 2, ',', '.').'%' : '—',
                    'close_rate' => $imp ? number_format($pct((int) $r->closes), 2, ',', '.').'%' : '—',
                ];
            })
            ->sortByDesc('impressions')->values();
    }

    public function export()
    {
        $rows = $this->rows();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['dari', 'sampai', 'kampanye', 'impresi', 'klik', 'tutup', 'ctr_persen', 'tingkat_tutup_persen']);
            foreach ($rows as $r) {
                fputcsv($out, [$this->from, $this->until, $r['name'], $r['impressions'], $r['clicks'], $r['closes'], $r['ctr_raw'], $r['close_rate_raw']]);
            }
            fclose($out);
        }, 'kampanye-'.now()->format('Ymd-His').'.csv');
    }

    private function utc(string $date, int $addDays = 0): string
    {
        return \Illuminate\Support\Carbon::parse($date, 'Asia/Jakarta')->startOfDay()->addDays($addDays)->utc()->toDateTimeString();
    }
}
