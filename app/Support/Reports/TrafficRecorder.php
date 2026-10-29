<?php

namespace App\Support\Reports;

use App\Models\PageViewStat;
use App\Models\SearchStat;
use App\Support\BotDetector;
use App\Support\Search;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Pencatat lalu lintas untuk Pusat Laporan: hanya penghitung agregat per hari (WIB), tanpa IP atau data pribadi.
 * Bot tidak dihitung dan satu sesi hanya dihitung sekali per halaman / kata kunci.
 */
class TrafficRecorder
{
    /** Catat pencarian dari halaman /cari (bukan live search, yang terpicu tiap ketikan). */
    public static function search(Request $request, string $term, int $results): void
    {
        $term = mb_strtolower($term);

        if (mb_strlen($term) < Search::MIN_LENGTH || ! self::isFirstInSession($request, 'searched_'.md5($term))) {
            return;
        }

        SearchStat::upsert(
            [['date' => self::today(), 'term' => $term, 'searches' => 1, 'results' => $results]],
            ['date', 'term'],
            ['searches' => DB::raw('searches + 1'), 'results'],
        );
    }

    /**
     * Catat tayangan halaman produk/artikel.
     *
     * @return bool true bila tayangan ini dihitung (bukan bot, belum pernah dilihat di sesi ini)
     */
    public static function view(Request $request, string $type, int $id): bool
    {
        if (! self::isFirstInSession($request, "viewed_{$type}_{$id}")) {
            return false;
        }

        PageViewStat::upsert(
            [['date' => self::today(), 'viewable_type' => $type, 'viewable_id' => $id, 'views' => 1]],
            ['date', 'viewable_type', 'viewable_id'],
            ['views' => DB::raw('views + 1')],
        );

        return true;
    }

    private static function isFirstInSession(Request $request, string $key): bool
    {
        if (BotDetector::isBot($request->userAgent()) || $request->session()->has($key)) {
            return false;
        }

        $request->session()->put($key, now()->timestamp);

        return true;
    }

    private static function today(): string
    {
        return now(ClickReport::TIMEZONE)->toDateString();
    }
}
