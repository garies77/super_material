<?php

namespace App\Support;

use App\Models\Article;
use App\Models\AuditLog;
use App\Models\Media;
use App\Models\User;
use App\Models\XpEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Gamifikasi fase 2: XP, level, dan lencana untuk Kontributor. XP hanya diberikan saat kiriman disetujui
 * Editor/Admin (kualitas, bukan jumlah), sekali per konten, dan dapat dibatalkan Admin di "Riwayat XP".
 */
class Gamification
{
    public const POINTS_APPROVED = 40;

    public const POINTS_COMPLETE = 20;

    public const POINTS_PERFECT = 20;

    public const POINTS_FIRST_TIME_RIGHT = 10;

    public const COMPLETE_SCORE = 80;

    /** XP minimum => nama level. */
    public const LEVELS = [
        0 => 'Magang',
        200 => 'Juru Katalog',
        600 => 'Ahli Konten',
        1500 => 'Master Supermaterial',
    ];

    /**
     * Beri XP ke pengaju saat kiriman disetujui. Tidak memberi apa pun bila konten ini sudah pernah dinilai
     * (termasuk yang dibatalkan Admin), sehingga terbit-ulang tidak menambah poin.
     */
    public static function awardApproval(Model $record, User $submitter, int $score): ?XpEvent
    {
        $key = 'approval:'.$record->getMorphClass().':'.$record->getKey();

        if (XpEvent::where('award_key', $key)->exists()) {
            return null;
        }

        $firstTimeRight = ! AuditLog::where('action', 'review.changes_requested')
            ->where('subject_type', $record->getMorphClass())->where('subject_id', $record->getKey())->exists();

        $breakdown = array_filter([
            'Disetujui' => self::POINTS_APPROVED,
            'Skor ≥ '.self::COMPLETE_SCORE.'%' => $score >= self::COMPLETE_SCORE ? self::POINTS_COMPLETE : 0,
            'Skor 100%' => $score === 100 ? self::POINTS_PERFECT : 0,
            'Tepat sekali' => $firstTimeRight ? self::POINTS_FIRST_TIME_RIGHT : 0,
        ]);

        $type = $record instanceof Article ? 'Artikel' : 'Produk';

        return XpEvent::create([
            'user_id' => $submitter->id,
            'points' => array_sum($breakdown),
            'reason' => "{$type} disetujui: ".($record->name ?? $record->title),
            'subject_type' => $record->getMorphClass(),
            'subject_id' => $record->getKey(),
            'award_key' => $key,
            'meta' => ['score' => $score, 'first_time_right' => $firstTimeRight, 'breakdown' => $breakdown],
        ]);
    }

    public static function totalXp(User $user): int
    {
        return (int) XpEvent::active()->where('user_id', $user->id)->sum('points');
    }

    /**
     * @return array{name: string, xp: int, next: ?string, next_at: ?int, progress: int}
     */
    public static function level(int $xp): array
    {
        $thresholds = array_keys(self::LEVELS);
        $current = collect($thresholds)->filter(fn (int $min) => $xp >= $min)->last();
        $next = collect($thresholds)->first(fn (int $min) => $min > $xp);

        return [
            'name' => self::LEVELS[$current],
            'xp' => $xp,
            'next' => $next !== null ? self::LEVELS[$next] : null,
            'next_at' => $next,
            'progress' => $next === null ? 100 : (int) floor(($xp - $current) / ($next - $current) * 100),
        ];
    }

    /**
     * Lencana dihitung dari riwayat XP aktif dan unggahan media (tanpa tabel tersendiri).
     *
     * @return Collection<int, array{key: string, name: string, description: string, icon: string, earned: bool, progress: string}>
     */
    public static function badges(User $user): Collection
    {
        $approvals = XpEvent::active()->where('user_id', $user->id)->where('award_key', 'like', 'approval:%')->get();
        $perfect = $approvals->filter(fn (XpEvent $event) => ($event->meta['score'] ?? 0) === 100)->count();
        $firstTimeRight = $approvals->filter(fn (XpEvent $event) => $event->meta['first_time_right'] ?? false)->count();
        $photos = Media::where('uploaded_by', $user->id)->count();
        $streak = self::longestDailyStreak($approvals->pluck('created_at'));

        return collect([
            ['first_approval', 'Langkah Pertama', 'Kiriman pertama disetujui', 'heroicon-o-flag', $approvals->count(), 1],
            ['prolific', 'Produktif', '25 kiriman disetujui', 'heroicon-o-rocket-launch', $approvals->count(), 25],
            ['perfectionist', 'Perfeksionis', '10 kiriman disetujui dengan skor 100%', 'heroicon-o-sparkles', $perfect, 10],
            ['first_time_right', 'Tepat Sekali', '10 kiriman disetujui tanpa perbaikan', 'heroicon-o-check-badge', $firstTimeRight, 10],
            ['photographer', 'Fotografer', '50 foto diunggah ke Pustaka Media', 'heroicon-o-camera', $photos, 50],
            ['consistent', 'Konsisten', 'Kiriman disetujui 5 hari berturut-turut', 'heroicon-o-fire', $streak, 5],
        ])->map(fn (array $badge) => [
            'key' => $badge[0],
            'name' => $badge[1],
            'description' => $badge[2],
            'icon' => $badge[3],
            'earned' => $badge[4] >= $badge[5],
            'progress' => min($badge[4], $badge[5]).'/'.$badge[5],
        ]);
    }

    /** @param  Collection<int, Carbon>  $dates */
    private static function longestDailyStreak(Collection $dates): int
    {
        $days = $dates->map(fn (Carbon $date) => $date->copy()->startOfDay())->unique()->sort()->values();
        $longest = $current = 0;
        $previous = null;

        foreach ($days as $day) {
            $current = $previous && $previous->copy()->addDay()->equalTo($day) ? $current + 1 : 1;
            $longest = max($longest, $current);
            $previous = $day;
        }

        return $longest;
    }
}
