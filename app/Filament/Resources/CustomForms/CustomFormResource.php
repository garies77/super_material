<?php

namespace App\Filament\Resources\CustomForms;

use App\Filament\Resources\CustomForms\Pages\CreateCustomForm;
use App\Filament\Resources\CustomForms\Pages\EditCustomForm;
use App\Filament\Resources\CustomForms\Pages\ListCustomForms;
use App\Filament\Resources\FormSubmissions\FormSubmissionResource;
use App\Models\CustomForm;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class CustomFormResource extends Resource
{
    protected static ?string $model = CustomForm::class;

    protected static ?string $modelLabel = 'Formulir';

    protected static ?string $pluralModelLabel = 'Formulir';

    protected static ?string $slug = 'formulir';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'Pemasaran';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return in_array(auth()->user()?->role, ['admin', 'editor'], true);
    }

    public static function form(Schema $schema): Schema
    {
        $choice = fn (Get $get) => in_array($get('type'), CustomForm::CHOICE_TYPES, true);

        // Kiri: isi formulir dan pertanyaan. Kanan: status, perilaku setelah kirim, notifikasi.
        return $schema->columns(['default' => 1, 'lg' => 1, 'xl' => 12])->components([
            Group::make()->columnSpan(['xl' => 7, '2xl' => 8])->schema([
                Section::make('Formulir')->description('Judul dan penjelasan yang dilihat pengisi.')->columns(2)->schema([
                    TextInput::make('title')->label('Judul tampil')->required()->maxLength(255)->live(onBlur: true)
                        ->afterStateUpdated(function (Set $set, Get $get, ?string $state, string $operation) {
                            if ($operation === 'create') {
                                $set('slug', Str::slug((string) $state));
                                $get('name') ?: $set('name', $state);
                            }
                        }),
                    TextInput::make('slug')->required()->unique(ignoreRecord: true)->maxLength(255)
                        ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')->prefix('/form/'),
                    Textarea::make('description')->label('Deskripsi / petunjuk')->rows(3)->maxLength(2000)->columnSpanFull(),
                ]),

                Section::make('Pertanyaan')->description('Seret untuk mengurutkan. Kolom email dan telepon pertama otomatis menjadi kontak untuk email marketing.')->schema([
                    Repeater::make('fields')->hiddenLabel()->required()->minItems(1)->maxItems(30)
                        ->schema([
                            Hidden::make('key'),
                            Grid::make(['default' => 1, 'md' => 6])->schema([
                                TextInput::make('label')->label('Pertanyaan')->required()->maxLength(200)->live(onBlur: true)->columnSpan(['md' => 3]),
                                Select::make('type')->label('Jenis jawaban')->options(CustomForm::FIELD_TYPES)->default('text')
                                    ->required()->selectablePlaceholder(false)->live()->columnSpan(['md' => 2]),
                                Toggle::make('required')->label('Wajib')->inline(false),
                            ]),
                            TagsInput::make('options')->label('Pilihan jawaban')->placeholder('Ketik pilihan lalu tekan Enter')
                                ->visible($choice)->required($choice)->reorderable(),
                            Grid::make(['default' => 1, 'md' => 2])->schema([
                                TextInput::make('placeholder')->label('Teks contoh di kolom')->maxLength(150)
                                    ->visible(fn (Get $get) => ! in_array($get('type'), ['radio', 'checkbox', 'date'], true)),
                                TextInput::make('help')->label('Teks bantuan')->maxLength(200),
                            ]),
                        ])
                        ->default([
                            ['label' => 'Nama lengkap', 'type' => 'text', 'required' => true],
                            ['label' => 'Email', 'type' => 'email', 'required' => true],
                        ])
                        ->itemLabel(fn (array $state) => ($state['label'] ?? 'Pertanyaan baru').' · '.(CustomForm::FIELD_TYPES[$state['type'] ?? 'text'] ?? '').(! empty($state['required']) ? ' · wajib' : ''))
                        ->reorderableWithDragAndDrop()->collapsible()->cloneable()
                        ->addActionLabel('Tambah pertanyaan'),
                ]),
            ]),

            Group::make()->columnSpan(['xl' => 5, '2xl' => 4])->schema([
                Section::make('Status & tampilan')->schema([
                    Toggle::make('is_active')->label('Aktif')->default(true)
                        ->helperText('Nonaktif: formulir tidak tampil dan tidak menerima kiriman.'),
                    Select::make('display_mode')->label('Ditampilkan sebagai')->options(CustomForm::DISPLAY_MODES)->default('both')
                        ->required()->selectablePlaceholder(false)
                        ->helperText('Sematan bisa dipasang di Kustomisasi Beranda, blok Halaman, kolom Footer, atau lewat komponen <x-custom-form>.'),
                    TextInput::make('name')->label('Nama internal')->required()->maxLength(255)
                        ->helperText('Hanya terlihat di panel admin.'),
                    Text::make(fn (?CustomForm $record) => $record ? 'Halaman: '.$record->url() : 'Alamat halaman tersedia setelah disimpan.')
                        ->visible(fn (?CustomForm $record) => ! $record || $record->hasFullPage()),
                    Text::make(fn (?CustomForm $record) => '<x-custom-form slug="'.$record?->slug.'" />')
                        ->visible(fn (?CustomForm $record) => $record?->isEmbeddable() ?? false),
                ]),

                Section::make('Setelah dikirim')->schema([
                    TextInput::make('submit_label')->label('Teks tombol kirim')->maxLength(60)->placeholder('Kirim'),
                    Textarea::make('success_message')->label('Pesan sukses')->rows(2)->maxLength(500)
                        ->placeholder('Terima kasih, data Anda sudah kami terima.'),
                    TextInput::make('redirect_url')->label('Alihkan ke (opsional)')->maxLength(2000)
                        ->placeholder('/terima-kasih atau https://…')
                        ->rule('regex:#^(/[^/\\\\]\S*|https://\S+)$#i')
                        ->helperText('Bila diisi, pengisi dialihkan ke sini alih-alih melihat pesan sukses.'),
                ]),

                Section::make('Notifikasi email')->schema([
                    TextInput::make('notify_email')->label('Kirim pemberitahuan ke')->email()->maxLength(255)
                        ->placeholder('admin@contoh.com')
                        ->helperText('Kosong = tanpa pemberitahuan. Jawaban tetap tersimpan di panel.'),
                    Toggle::make('send_confirmation')->label('Kirim email konfirmasi ke pengisi')->live()
                        ->helperText('Butuh pertanyaan berjenis Email.'),
                    Textarea::make('confirmation_message')->label('Isi email konfirmasi')->rows(3)->maxLength(2000)
                        ->visible(fn (Get $get) => (bool) $get('send_confirmation'))
                        ->placeholder('Kosong = memakai pesan sukses.'),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['submissions', 'submissions as new_submissions_count' => fn ($q) => $q->where('status', 'new')]))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->description(fn (CustomForm $r) => '/form/'.$r->slug),
                TextColumn::make('display_mode')->label('Tampil sebagai')->badge()->formatStateUsing(fn ($state) => CustomForm::DISPLAY_MODES[$state] ?? $state),
                TextColumn::make('submissions_count')->label('Jawaban')->numeric()->sortable()
                    ->description(fn (CustomForm $r) => $r->new_submissions_count ? $r->new_submissions_count.' baru' : null),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('updated_at')->label('Diubah')->since('Asia/Jakarta')->sortable(),
            ])
            ->recordActions([
                Action::make('responses')->label('Jawaban')->icon('heroicon-o-inbox')->color('gray')
                    ->url(fn (CustomForm $r) => FormSubmissionResource::getUrl('index', ['filters' => ['custom_form_id' => ['value' => $r->id]]])),
                Action::make('open')->label('Buka')->icon('heroicon-o-arrow-top-right-on-square')->color('gray')
                    ->url(fn (CustomForm $r) => $r->url(), true)->visible(fn (CustomForm $r) => $r->is_active && $r->hasFullPage()),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomForms::route('/'),
            'create' => CreateCustomForm::route('/create'),
            'edit' => EditCustomForm::route('/{record}/edit'),
        ];
    }
}
