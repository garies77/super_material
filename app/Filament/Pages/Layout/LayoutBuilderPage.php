<?php

namespace App\Filament\Pages\Layout;

use App\Models\AuditLog;
use App\Models\Setting;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;

/**
 * Dasar Navbar Builder dan Footer Builder: konfigurasi disimpan sebagai JSON di tabel settings;
 * Preview menampilkan situs dengan konfigurasi yang belum disimpan tanpa mengubah situs publik.
 */
abstract class LayoutBuilderPage extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.pages.layout-builder';

    protected static string|\UnitEnum|null $navigationGroup = 'Tampilan situs';

    public ?array $data = [];

    abstract protected function settingKey(): string;

    abstract protected function defaults(): array;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $saved = json_decode((string) Setting::get($this->settingKey()), true);
        $this->form->fill(is_array($saved) ? $saved : $this->defaults());
    }

    public function save(): void
    {
        $state = $this->form->getState();
        Setting::put($this->settingKey(), json_encode($state));
        AuditLog::record(request(), $this->settingKey().'.update', null, ['saved' => true]);

        Notification::make()->title('Perubahan disimpan dan langsung tampil di situs')->success()->send();
    }

    public function preview(): void
    {
        session()->put('layout_preview.'.$this->settingKey(), $this->form->getState());
        $this->js('window.open('.json_encode(route('layout.preview')).', "_blank")');
    }
}
