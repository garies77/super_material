<?php

namespace App\Support\Reports;

use App\Models\Article;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\User;
use App\Models\XpEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Kinerja tim dari jejak alur tinjauan (audit log review.*) dan riwayat XP:
 * kiriman per Kontributor, kualitas (skor, tepat sekali), dan waktu tunggu tinjauan oleh Editor/Admin.
 */
class TeamMetrics
{
    public const SUBMITTED = 'review.submitted';

    public const APPROVED = 'review.approved';

    public const CHANGES_REQUESTED = 'review.changes_requested';

    public static function submissions(ReportPeriod $period): int
    {
        return AuditLog::where('action', self::SUBMITTED)->whereBetween('created_at', $period->utcRange())->count();
    }

    /** Persetujuan pertama tiap konten (dari riwayat XP, termasuk yang XP-nya dibatalkan). @return Collection<int, XpEvent> */
    public static function approvals(ReportPeriod $period): Collection
    {
        return XpEvent::where('award_key', 'like', 'approval:%')->whereBetween('created_at', $period->utcRange())->get();
    }

    /** Persen persetujuan tanpa permintaan perbaikan; null bila belum ada persetujuan. */
    public static function firstTimeRightRate(Collection $approvals): ?float
    {
        return $approvals->isEmpty() ? null
            : round($approvals->filter(fn (XpEvent $event) => $event->meta['first_time_right'] ?? false)->count() / $approvals->count() * 100, 1);
    }

    /**
     * Keputusan tinjauan dalam periode beserta lama kiriman menunggu (sejak pengajuan terakhir sebelum keputusan).
     *
     * @return Collection<int, array{reviewer_id: ?int, submitter_id: ?int, approved: bool, hours: float}>
     */
    public static function reviewDecisions(ReportPeriod $period): Collection
    {
        $decisions = AuditLog::whereIn('action', [self::APPROVED, self::CHANGES_REQUESTED])
            ->whereBetween('created_at', $period->utcRange())->orderBy('created_at')->get();

        $submissions = AuditLog::where('action', self::SUBMITTED)
            ->whereIn('subject_id', $decisions->pluck('subject_id')->unique())
            ->where('created_at', '<=', $period->utcRange()[1])
            ->orderBy('created_at')->get()
            ->groupBy(fn (AuditLog $log) => $log->subject_type.'#'.$log->subject_id);

        return $decisions->map(function (AuditLog $decision) use ($submissions) {
            $submitted = collect($submissions[$decision->subject_type.'#'.$decision->subject_id] ?? [])
                ->filter(fn (AuditLog $log) => $log->created_at->lte($decision->created_at))->last();

            return $submitted ? [
                'reviewer_id' => $decision->user_id,
                'submitter_id' => $submitted->user_id,
                'approved' => $decision->action === self::APPROVED,
                'hours' => round($submitted->created_at->diffInMinutes($decision->created_at) / 60, 1),
            ] : null;
        })->filter()->values();
    }

    /** "45 menit", "5,2 jam", atau "1,5 hari". */
    public static function formatHours(?float $hours): string
    {
        return match (true) {
            $hours === null => '—',
            $hours < 1 => max(1, (int) round($hours * 60)).' menit',
            $hours < 24 => number_format($hours, 1, ',', '.').' jam',
            default => number_format($hours / 24, 1, ',', '.').' hari',
        };
    }

    /** Median jam tunggu (lebih tahan pencilan daripada rata-rata). */
    public static function medianHours(Collection $decisions): ?float
    {
        return $decisions->isEmpty() ? null : round((float) $decisions->median('hours'), 1);
    }

    /**
     * Ringkasan per Kontributor: kiriman, disetujui, diminta perbaikan, rata-rata skor, tepat sekali, XP periode ini.
     *
     * @return Collection<string, array{user_id: int, name: string, submissions: int, approved: int, changes: int, score: ?float, first_time_right: ?float, xp: int}>
     */
    public static function contributors(ReportPeriod $period): Collection
    {
        $submitted = AuditLog::where('action', self::SUBMITTED)->whereBetween('created_at', $period->utcRange())
            ->whereNotNull('user_id')->get()->groupBy('user_id');
        $approvals = self::approvals($period)->groupBy('user_id');
        $changes = self::reviewDecisions($period)->where('approved', false)->countBy('submitter_id');
        $xp = XpEvent::active()->whereBetween('created_at', $period->utcRange())
            ->groupBy('user_id')->selectRaw('user_id, sum(points) as points')->pluck('points', 'user_id');

        $ids = $submitted->keys()->merge($approvals->keys())->unique();

        return User::whereIn('id', $ids)->get(['id', 'name'])
            ->map(function (User $user) use ($submitted, $approvals, $changes, $xp) {
                $approved = collect($approvals[$user->id] ?? []);

                return [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'submissions' => collect($submitted[$user->id] ?? [])->count(),
                    'approved' => $approved->count(),
                    'changes' => (int) ($changes[$user->id] ?? 0),
                    'score' => $approved->isEmpty() ? null : round((float) $approved->avg(fn (XpEvent $event) => $event->meta['score'] ?? 0), 1),
                    'first_time_right' => self::firstTimeRightRate($approved),
                    'xp' => (int) ($xp[$user->id] ?? 0),
                ];
            })
            ->sortBy([['approved', 'desc'], ['submissions', 'desc']])
            ->mapWithKeys(fn (array $row) => ["u{$row['user_id']}" => $row]);
    }

    /**
     * Ringkasan per peninjau (Editor/Admin): jumlah keputusan dan median lama kiriman menunggu.
     *
     * @return Collection<string, array{name: string, reviews: int, approved: int, changes: int, median_hours: ?float}>
     */
    public static function reviewers(ReportPeriod $period): Collection
    {
        $decisions = self::reviewDecisions($period)->whereNotNull('reviewer_id')->groupBy('reviewer_id');
        $names = User::whereIn('id', $decisions->keys())->pluck('name', 'id');

        return $decisions->map(fn (Collection $rows, int $id) => [
            'name' => $names[$id] ?? 'Pengguna terhapus',
            'reviews' => $rows->count(),
            'approved' => $rows->where('approved', true)->count(),
            'changes' => $rows->where('approved', false)->count(),
            'median_hours' => self::medianHours($rows),
        ])->sortByDesc('reviews')->mapWithKeys(fn (array $row, int $id) => ["r{$id}" => $row]);
    }

    /**
     * Antrean tinjauan saat ini, yang paling lama menunggu di atas.
     *
     * @return Collection<string, array{type: string, title: string, submitter: string, id: int, submitted_at: ?Carbon}>
     */
    public static function queue(): Collection
    {
        $items = Product::awaitingReview()->with('submitter:id,name')->get(['id', 'name', 'submitted_by', 'submitted_at', 'review_status'])
            ->concat(Article::awaitingReview()->with('submitter:id,name')->get(['id', 'title', 'submitted_by', 'submitted_at', 'review_status']));

        return $items->sortBy('submitted_at')->mapWithKeys(fn (Model $record) => [
            ($record instanceof Article ? 'a' : 'p').$record->getKey() => [
                'type' => $record instanceof Article ? 'Artikel' : 'Produk',
                'title' => $record instanceof Article ? $record->title : $record->name,
                'submitter' => $record->submitter?->name ?? '—',
                'id' => $record->getKey(),
                'submitted_at' => $record->submitted_at,
            ],
        ]);
    }
}
