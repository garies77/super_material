<?php

namespace App\Filament\Resources\FormSubmissions;

use App\Filament\Resources\FormSubmissions\Pages\ListFormSubmissions;
use App\Models\CustomForm;
use App\Models\FormSubmission;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Jawaban formulir (prospek). Data pribadi: hanya Admin dan Editor. */
class FormSubmissionResource extends Resource
{
    protected static ?string $model = FormSubmission::class;

    protected static ?string $modelLabel = 'Jawaban formulir';

    protected static ?string $pluralModelLabel = 'Jawaban formulir';

    protected static ?string $navigationLabel = 'Jawaban formulir';

    protected static ?string $slug = 'jawaban-formulir';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|\UnitEnum|null $navigationGroup = 'Pemasaran';

    protected static ?int $navigationSort = 3;

    public static function canAccess(): bool
    {
        return in_array(auth()->user()?->role, ['admin', 'editor'], true);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $new = FormSubmission::where('status', 'new')->count();

        return $new ? (string) $new : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Jawaban baru yang belum ditindaklanjuti';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('form'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Masuk (WIB)')->dateTime('d M Y H:i', 'Asia/Jakarta')->sortable(),
                TextColumn::make('form.name')->label('Formulir')->toggleable(),
                TextColumn::make('name')->label('Nama')->searchable()->placeholder('—'),
                TextColumn::make('email')->label('Email')->searchable()->copyable()->placeholder('—'),
                TextColumn::make('phone')->label('Telepon')->searchable()->copyable()->placeholder('—'),
                SelectColumn::make('status')->label('Tindak lanjut')->options(FormSubmission::STATUSES)->selectablePlaceholder(false),
                TextColumn::make('source_url')->label('Dikirim dari')->limit(40)->tooltip(fn (FormSubmission $r) => $r->source_url)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('custom_form_id')->label('Formulir')->options(fn () => CustomForm::orderBy('name')->pluck('name', 'id')),
                SelectFilter::make('status')->label('Tindak lanjut')->options(FormSubmission::STATUSES),
                Filter::make('created_at')->label('Tanggal masuk')
                    ->schema([
                        DatePicker::make('from')->label('Dari'),
                        DatePicker::make('until')->label('Sampai'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', Carbon::parse($d, 'Asia/Jakarta')->startOfDay()->utc()))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->where('created_at', '<=', Carbon::parse($d, 'Asia/Jakarta')->endOfDay()->utc())))
                    ->indicateUsing(fn (array $data) => array_filter([
                        ($data['from'] ?? null) ? 'Dari '.$data['from'] : null,
                        ($data['until'] ?? null) ? 'Sampai '.$data['until'] : null,
                    ])),
            ])
            ->recordActions([
                Action::make('view')->label('Lihat')->icon('heroicon-o-eye')->color('gray')
                    ->modalHeading(fn (FormSubmission $r) => $r->form?->title ?? 'Jawaban')
                    ->modalContent(fn (FormSubmission $r) => view('filament.form-submissions.answers', ['submission' => $r]))
                    ->modalSubmitAction(false)->modalCancelActionLabel('Tutup'),
                DeleteAction::make(),
            ])
            ->toolbarActions([BulkActionGroup::make([
                BulkAction::make('markContacted')->label('Tandai sudah dihubungi')->icon('heroicon-o-check')
                    ->action(fn (Collection $records) => $records->each->update(['status' => 'contacted']))
                    ->deselectRecordsAfterCompletion(),
                DeleteBulkAction::make(),
            ])]);
    }

    public static function getPages(): array
    {
        return ['index' => ListFormSubmissions::route('/')];
    }
}
