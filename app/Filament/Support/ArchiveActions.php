<?php

namespace App\Filament\Support;

use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Filters\TrashedFilter;

/**
 * Arsip produk & artikel (soft delete) dengan bahasa yang sama di tabel dan halaman edit: "Hapus" berarti
 * Arsipkan (bisa dipulihkan), dan hanya Admin yang boleh menghapus permanen.
 */
class ArchiveActions
{
    public static function canForceDelete(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public static function filter(): TrashedFilter
    {
        return TrashedFilter::make()->label('Arsip')
            ->placeholder('Tanpa arsip')
            ->trueLabel('Semua, termasuk arsip')
            ->falseLabel('Hanya arsip');
    }

    /** Tombol ikon Pulihkan; hanya tampil di baris yang diarsipkan. */
    public static function restore(): RestoreAction
    {
        return RestoreAction::make()->label('Pulihkan')->iconButton()->tooltip('Pulihkan dari arsip')
            ->visible(ReviewWorkflow::canPublish());
    }

    public static function forceDelete(): ForceDeleteAction
    {
        return ForceDeleteAction::make()->label('Hapus permanen')
            ->modalDescription('Data dan riwayatnya dihapus selamanya dan tidak bisa dipulihkan.')
            ->visible(self::canForceDelete());
    }

    public static function archive(): DeleteAction
    {
        return DeleteAction::make()->label('Arsipkan')->icon('heroicon-o-archive-box')
            ->modalHeading('Arsipkan?')
            ->modalDescription('Disembunyikan dari situs dan daftar. Bisa dipulihkan kapan saja lewat filter Arsip.')
            ->modalSubmitActionLabel('Arsipkan')
            ->successNotificationTitle('Diarsipkan')
            ->visible(ReviewWorkflow::canPublish());
    }

    public static function archiveBulk(): DeleteBulkAction
    {
        return DeleteBulkAction::make()->label('Arsipkan')->icon('heroicon-o-archive-box')
            ->modalHeading('Arsipkan yang dipilih?')
            ->modalDescription('Disembunyikan dari situs dan daftar. Bisa dipulihkan lewat filter Arsip.')
            ->modalSubmitActionLabel('Arsipkan')
            ->visible(ReviewWorkflow::canPublish());
    }

    /** @return list<RestoreBulkAction|ForceDeleteBulkAction> */
    public static function bulk(): array
    {
        return [
            RestoreBulkAction::make()->label('Pulihkan dari arsip')->visible(ReviewWorkflow::canPublish()),
            ForceDeleteBulkAction::make()->label('Hapus permanen')
                ->modalDescription('Data yang dipilih dihapus selamanya dan tidak bisa dipulihkan.')
                ->visible(self::canForceDelete()),
        ];
    }
}
