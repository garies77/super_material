<?php

namespace App\Filament\Support;

use App\Models\Article;
use App\Models\AuditLog;
use App\Models\Product;
use App\Support\Gamification;
use App\Support\SeoScore;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Model;

/**
 * Bagian panel admin untuk alur tinjauan Kontributor (model memakai HasReviewWorkflow):
 * tombol di halaman edit, banner status, kolom & filter tabel.
 */
class ReviewWorkflow
{
    /** Skor kelengkapan minimum agar pengajuan dirayakan dengan confetti. */
    public const CELEBRATE_SCORE = 80;

    public static function seoScore(Model $record): int
    {
        return match (true) {
            $record instanceof Product => SeoScore::forProduct($record)['score'],
            $record instanceof Article => SeoScore::forArticle($record)['score'],
            default => 0,
        };
    }

    public static function isContributor(): bool
    {
        return (bool) auth()->user()?->isContributor();
    }

    /** Field & aksi terbit: semua peran kecuali Kontributor (Penulis tetap boleh menerbitkan artikelnya). */
    public static function canPublish(): bool
    {
        return ! self::isContributor();
    }

    public static function canReview(): bool
    {
        return (bool) auth()->user()?->canReview();
    }

    /** @return array<int, Action> */
    public static function pageActions(EditRecord $page): array
    {
        return [
            Action::make('submitForReview')->label('Ajukan untuk ditinjau')->icon(Heroicon::OutlinedPaperAirplane)->color('success')
                ->visible(fn (Model $record) => self::isContributor() && ! $record->isLive() && ! $record->isAwaitingReview())
                ->requiresConfirmation()
                ->modalHeading('Ajukan untuk ditinjau?')
                ->modalDescription('Perubahan disimpan dulu, lalu Editor/Admin akan memeriksa dan menerbitkannya. Selama menunggu, Anda masih bisa memperbaikinya.')
                ->modalSubmitActionLabel('Ajukan')
                ->action(function (Model $record) use ($page): void {
                    $page->save(shouldRedirect: false, shouldSendSavedNotification: false);
                    $record->submitForReview(auth()->user());
                    $score = self::seoScore($record);
                    AuditLog::record(request(), 'review.submitted', $record, ['seo_score' => $score]);

                    // Gamifikasi: kiriman lengkap dirayakan dengan confetti setelah halaman dimuat ulang (filament/celebrate)
                    if ($score >= self::CELEBRATE_SCORE) {
                        session()->flash('sm_celebrate', true);
                        Notification::make()->title("Mantap! Diajukan dengan skor {$score}% 🎉")
                            ->body('Editor/Admin akan memeriksanya. Kiriman yang lengkap biasanya lebih cepat disetujui.')->success()->send();
                    } else {
                        Notification::make()->title("Diajukan dengan skor {$score}%")
                            ->body('Editor/Admin akan memeriksanya. Selesaikan misi kelengkapan yang tersisa agar lebih cepat disetujui.')->success()->send();
                    }

                    $page->redirect($page::getUrl(['record' => $record]));
                }),

            Action::make('approve')->label('Setujui & terbitkan')->icon(Heroicon::OutlinedCheckBadge)->color('success')
                ->visible(fn (Model $record) => self::canReview() && $record->isAwaitingReview())
                ->requiresConfirmation()
                ->modalHeading('Setujui dan terbitkan?')
                ->modalDescription('Perubahan di form ini disimpan, lalu konten langsung terbit di situs.')
                ->modalSubmitActionLabel('Terbitkan')
                ->action(function (Model $record) use ($page): void {
                    $page->save(shouldRedirect: false, shouldSendSavedNotification: false);
                    $record->markAsLive();
                    $record->save();
                    $score = self::seoScore($record);
                    AuditLog::record(request(), 'review.approved', $record, ['submitted_by' => $record->submitted_by, 'seo_score' => $score]);

                    // Gamifikasi: XP untuk pengaju (sekali per konten; lihat App\Support\Gamification)
                    $award = ($submitter = $record->submitter) ? Gamification::awardApproval($record, $submitter, $score) : null;

                    Notification::make()->title('Disetujui dan diterbitkan')
                        ->body($award ? "{$submitter->name} mendapat +{$award->points} XP." : null)
                        ->success()->send();
                    $page->redirect($page::getUrl(['record' => $record]));
                }),

            Action::make('requestChanges')->label('Minta perbaikan')->icon(Heroicon::OutlinedChatBubbleLeftEllipsis)->color('warning')
                ->visible(fn (Model $record) => self::canReview() && $record->isAwaitingReview())
                ->modalHeading('Minta perbaikan ke kontributor')
                ->modalSubmitActionLabel('Kirim catatan')
                ->schema([
                    Textarea::make('note')->label('Apa yang perlu diperbaiki?')->required()->rows(4)->maxLength(2000)
                        ->placeholder('cth: Foto utama masih buram, tolong ganti. Deskripsi perlu ditambah cara pemakaian.'),
                ])
                ->action(function (Model $record, array $data) use ($page): void {
                    $record->requestChanges($data['note']);
                    AuditLog::record(request(), 'review.changes_requested', $record, ['note' => $data['note']]);

                    Notification::make()->title('Catatan perbaikan dikirim')->success()->send();
                    $page->redirect($page::getUrl(['record' => $record]));
                }),
        ];
    }

    /** Banner status tinjauan di bawah judul halaman edit. */
    public static function subheading(Model $record): ?string
    {
        return match ($record->review_status) {
            $record::REVIEW_PENDING => self::canReview()
                ? 'Menunggu tinjauan Anda: diajukan '.$record->submitter?->name.' '.$record->submitted_at?->diffForHumans().'.'
                : 'Menunggu tinjauan Editor/Admin. Anda masih bisa memperbaikinya.',
            $record::REVIEW_CHANGES_REQUESTED => 'Perlu perbaikan: '.$record->review_note.' Setelah diperbaiki, klik "Ajukan untuk ditinjau" lagi.',
            default => null,
        };
    }

    public static function statusColumn(): TextColumn
    {
        return TextColumn::make('review_status')->label('Tinjauan')->badge()
            ->formatStateUsing(fn (Model $record, ?string $state) => $record::REVIEW_STATUSES[$state] ?? null)
            ->color(fn (?string $state) => $state === 'pending' ? 'warning' : 'danger')
            ->placeholder('—');
    }

    public static function statusFilter(): SelectFilter
    {
        return SelectFilter::make('review_status')->label('Tinjauan')
            ->options(['pending' => 'Menunggu tinjauan', 'changes_requested' => 'Perlu perbaikan']);
    }
}
