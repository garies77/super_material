<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Alur tinjauan konten Kontributor: Kontributor membuat & mengubah draf miliknya lalu mengajukan tinjauan;
 * Admin/Editor menerbitkan atau meminta perbaikan. Ditegakkan di model agar berlaku untuk semua jalur
 * (form, aksi tabel, duplikat), bukan hanya tombol yang disembunyikan.
 *
 * Model wajib mendefinisikan isLive(), wasLive(), dan markAsDraft().
 */
trait HasReviewWorkflow
{
    public const REVIEW_PENDING = 'pending';

    public const REVIEW_CHANGES_REQUESTED = 'changes_requested';

    public const REVIEW_STATUSES = [
        self::REVIEW_PENDING => 'Menunggu tinjauan',
        self::REVIEW_CHANGES_REQUESTED => 'Perlu perbaikan',
    ];

    /** Konten sedang tayang di situs (nilai saat ini). */
    abstract public function isLive(): bool;

    /** Konten sudah tayang sebelum perubahan ini (nilai di database). */
    abstract public function wasLive(): bool;

    /** Kosongkan status terbit/unggulan: konten Kontributor selalu dimulai sebagai draf. */
    abstract public function markAsDraft(): void;

    /** Tandai tayang (dipakai saat Admin/Editor menyetujui kiriman). */
    abstract public function markAsLive(): void;

    public function initializeHasReviewWorkflow(): void
    {
        $this->mergeCasts(['submitted_at' => 'datetime']);
    }

    protected static function bootHasReviewWorkflow(): void
    {
        static::saving(function ($model) {
            $user = auth()->user();

            // Event "saving" berjalan sebelum "creating": konten baru ditandai pembuat & draf di sini
            if (! $model->exists) {
                $model->created_by ??= $user?->id;

                if ($user?->isContributor()) {
                    $model->markAsDraft();
                }
            }

            if ($user?->isContributor()) {
                if ($model->exists && ! $model->isEditableBy($user)) {
                    throw new AuthorizationException('Kontributor hanya dapat mengubah draf miliknya yang belum terbit.');
                }

                if ($model->isLive()) {
                    throw new AuthorizationException('Kontributor tidak dapat menerbitkan. Ajukan untuk ditinjau.');
                }
            }

            // Terbit lewat jalur mana pun (tombol, toggle, aksi massal) = tinjauan selesai
            if ($model->isLive()) {
                $model->review_status = null;
                $model->review_note = null;
            }
        });

        static::deleting(function () {
            if (auth()->user()?->isContributor()) {
                throw new AuthorizationException('Kontributor tidak dapat menghapus konten.');
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** Admin/Editor/Penulis mengikuti aturan peran masing-masing; Kontributor hanya draf miliknya. */
    public function isEditableBy(User $user): bool
    {
        return ! $user->isContributor() || ((int) $this->created_by === $user->id && ! $this->wasLive());
    }

    public function isAwaitingReview(): bool
    {
        return $this->review_status === self::REVIEW_PENDING;
    }

    public function submitForReview(User $user): void
    {
        $this->forceFill([
            'review_status' => self::REVIEW_PENDING,
            'review_note' => null,
            'submitted_by' => $user->id,
            'submitted_at' => now(),
        ])->save();
    }

    public function requestChanges(string $note): void
    {
        $this->forceFill(['review_status' => self::REVIEW_CHANGES_REQUESTED, 'review_note' => $note])->save();
    }

    public function scopeAwaitingReview(Builder $q): Builder
    {
        return $q->where('review_status', self::REVIEW_PENDING);
    }
}
