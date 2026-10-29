<?php

namespace App\Support;

use App\Filament\Pages\ReportCenter;
use App\Filament\Pages\TeamReport;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Article;
use App\Models\Product;
use App\Models\SearchStat;
use App\Models\SearchSynonym;
use App\Models\User;
use App\Support\Reports\ClickReport;
use App\Support\Reports\ReportSummary;

/**
 * Pengingat dashboard untuk Editor/Admin: hal yang perlu ditangani sekarang, masing-masing menuju daftar
 * tersaring (bisa diperbaiki massal), bukan satu item seperti Papan misi.
 */
class AttentionBoard
{
    public const STALE_REVIEW_HOURS = 48;

    /**
     * @return list<array{icon: string, tone: string, title: string, hint: string, count: int, url: string, action: string}>
     */
    public static function for(User $user): array
    {
        if (! $user->canReview()) {
            return [];
        }

        $items = [
            [
                'heroicon-o-exclamation-triangle', 'danger', 'Harga produk kedaluwarsa',
                'Pilih semua di daftar, lalu aksi massal "Perpanjang masa harga".',
                Product::published()->where('price_review_needed', true)->count(),
                ProductResource::getUrl('index', ['filters' => ['price_review_needed' => ['value' => 1]]]), 'Perbarui',
            ],
            [
                'heroicon-o-clock', 'warning', 'Masa harga habis dalam 7 hari',
                'Cek harganya sebelum ditandai kedaluwarsa.',
                Product::published()->whereBetween('price_valid_until', [now(), now()->addDays(7)])->count(),
                ProductResource::getUrl('index', ['filters' => ['price_expiring' => ['isActive' => true]]]), 'Lihat',
            ],
            [
                'heroicon-o-inbox-stack', 'danger', 'Kiriman menunggu lebih dari 2 hari',
                'Kontributor menunggu keputusan Anda.',
                Product::awaitingReview()->where('submitted_at', '<', now()->subHours(self::STALE_REVIEW_HOURS))->count()
                    + Article::awaitingReview()->where('submitted_at', '<', now()->subHours(self::STALE_REVIEW_HOURS))->count(),
                TeamReport::getUrl(), 'Tinjau',
            ],
            [
                'heroicon-o-magnifying-glass', 'info', 'Pencarian tanpa hasil minggu ini',
                'Beri sinonim agar pengunjung menemukan produknya.',
                self::unansweredSearches(),
                ReportCenter::getUrl(['filters' => ['period' => '7d']]), 'Beri sinonim',
            ],
        ];

        if ($user->isAdmin() && ReportSummary::isEnabled() && ! ReportSummary::mailConfigured()) {
            $items[] = [
                'heroicon-o-envelope', 'warning', 'Email laporan mingguan belum terkirim',
                'Pengaturan email (SMTP) di server belum diisi.',
                count(ReportSummary::recipients()),
                ReportCenter::getUrl(), 'Lihat',
            ];
        }

        return collect($items)
            ->filter(fn (array $item) => $item[4] > 0)
            ->map(fn (array $item) => array_combine(['icon', 'tone', 'title', 'hint', 'count', 'url', 'action'], $item))
            ->values()->all();
    }

    /** Kata kunci 7 hari terakhir yang hasil terakhirnya 0 dan belum punya sinonim. */
    private static function unansweredSearches(): int
    {
        $since = now(ClickReport::TIMEZONE)->subDays(6)->toDateString();

        return SearchStat::where('date', '>=', $since)->orderBy('date')->get(['term', 'results'])
            ->groupBy('term')
            ->filter(fn ($days, string $term) => $days->last()->results === 0 && ! isset(SearchSynonym::map()[$term]))
            ->count();
    }
}
