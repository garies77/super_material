<?php

namespace App\Filament\Pages\Auth;

use App\Filament\Resources\Authors\AuthorResource;
use App\Models\Author;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use SensitiveParameter;

/**
 * Satu halaman Profil: data akun, ditambah profil penulis bila akun punya profil penulis.
 * Foto profil penulis sekaligus menjadi avatar akun, jadi cukup diganti di satu tempat.
 */
class EditProfile extends BaseEditProfile
{
    protected Width|string|null $maxContentWidth = Width::Full;

    public static bool $formActionsAreSticky = true;

    public function getTitle(): string|Htmlable
    {
        return 'Profil saya';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return $this->authorProfile()
            ? 'Nama, foto, dan biografi ini juga tampil di akhir setiap artikel Anda dan di halaman profil penulis.'
            : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewPublic')->label('Lihat profil publik')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                ->url(fn () => $this->authorProfile()?->url(), shouldOpenInNewTab: true)
                ->visible(fn () => (bool) $this->authorProfile()?->is_active)
                ->tooltip('Tampilan profil penulis Anda bagi pembaca situs'),
        ];
    }

    public function form(Schema $schema): Schema
    {
        $hasAuthor = (bool) $this->authorProfile();

        // Kiri: akun dan profil penulis. Kanan: foto dan kelengkapan profil. Satu kolom di bawah xl.
        return $schema->inlineLabel(false)->columns(['default' => 1, 'lg' => 1, 'xl' => 12])->components([
            Group::make()->columnSpan($hasAuthor ? ['xl' => 7, '2xl' => 8] : 'full')->schema([
                Section::make('Akun')
                    ->description('Data masuk ke panel admin.')
                    ->columns(2)
                    ->schema([
                        $this->getNameFormComponent(),
                        $this->getEmailFormComponent(),
                        $this->getPasswordFormComponent(),
                        $this->getPasswordConfirmationFormComponent(),
                        $this->getCurrentPasswordFormComponent(),
                    ]),
                Group::make()->statePath('author')->visible($hasAuthor)->schema([
                    Section::make('Profil penulis')
                        ->description('Tampil di kartu penulis pada artikel dan di halaman /penulis/'.$this->authorProfile()?->slug.'.')
                        ->schema(AuthorResource::detailFields()),
                    AuthorResource::externalProfileSection(),
                ]),
            ]),

            Group::make()->columnSpan(['xl' => 5, '2xl' => 4])->statePath('author')->visible($hasAuthor)->schema([
                Section::make('Foto profil')
                    ->description('Satu foto untuk akun Anda dan profil penulis.')
                    ->schema([AuthorResource::avatarField()->hiddenLabel()]),
                AuthorResource::completenessSection('author'),
            ]),
        ]);
    }

    /** Nama akun sekaligus nama penulis, jadi wajib murni bila akun punya profil penulis. */
    protected function getNameFormComponent(): TextInput
    {
        $field = TextInput::make('name')->label('Nama')->required()->maxLength(150);

        return $this->authorProfile()
            ? $field->rule(AuthorResource::pureNameRule())->helperText('Nama asli saja, tanpa gelar. Juga dipakai sebagai nama penulis.')
            : $field;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if ($author = $this->authorProfile()) {
            $data['author'] = $author->only(['job_title', 'motto', 'bio', 'same_as', 'avatar']);
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, #[SensitiveParameter] array $data): Model
    {
        $authorData = $data['author'] ?? null;
        unset($data['author']);

        $record = parent::handleRecordUpdate($record, $data);

        if ($authorData !== null && ($author = $this->authorProfile())) {
            $author->update([...$authorData, 'name' => $record->getAttribute('name')]);
        }

        return $record;
    }

    private function authorProfile(): ?Author
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user->author;
    }
}
