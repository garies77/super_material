<?php

namespace App\Filament\Support;

use Closure;
use Filament\Actions\Action;
use Filament\Schemas\Components\Component;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * Aksi "Edit cepat" di tabel (seperti Quick Edit WordPress): ubah judul, slug, kategori, atau status terbit
 * lewat modal kecil tanpa membuka halaman edit penuh. Disimpan lewat Eloquent, jadi redirect slug,
 * observer, dan cache tetap berjalan seperti saat menyimpan dari halaman edit.
 */
class QuickEdit
{
    /**
     * @param  Closure(): array<int, Component>  $fields
     * @param  (Closure(Model): array<string, mixed>)|null  $fill  Isi awal form; default atribut record
     * @param  (Closure(Model, array<string, mixed>): void)|null  $save  Penyimpanan; default $record->update($data)
     */
    public static function make(Closure $fields, ?Closure $fill = null, ?Closure $save = null): Action
    {
        return Action::make('quickEdit')
            ->label('Edit cepat')
            ->icon(Heroicon::OutlinedBolt)
            ->color('gray')
            ->modalHeading(fn (Model $record) => 'Edit cepat: '.($record->getAttribute('title') ?? $record->getAttribute('name')))
            ->modalSubmitActionLabel('Simpan perubahan')
            ->modalWidth(Width::Large)
            ->schema($fields)
            ->fillForm(fn (Model $record) => $fill ? $fill($record) : $record->attributesToArray())
            ->action(function (Model $record, array $data, Action $action) use ($save): void {
                $save ? $save($record, $data) : $record->update($data);
                $action->success();
            })
            ->successNotificationTitle('Perubahan disimpan');
    }
}
