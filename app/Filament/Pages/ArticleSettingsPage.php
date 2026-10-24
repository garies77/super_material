<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Articles\ArticleResource;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Support\ArticleSettings;
use App\Support\SocialPlatforms;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;

/**
 * Pengaturan global tampilan artikel dan Social Media Builder khusus artikel.
 * Dibuka dari tombol roda gigi di daftar artikel; terpisah dari media sosial Footer Builder.
 */
class ArticleSettingsPage extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.pages.article-settings';

    protected static ?string $title = 'Pengaturan Artikel';

    protected static ?string $slug = 'pengaturan-artikel';

    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function getBreadcrumbs(): array
    {
        return [ArticleResource::getUrl() => 'Artikel', 'Pengaturan'];
    }

    public function mount(): void
    {
        $settings = ArticleSettings::get();
        $settings['header_order'] = array_map(fn ($k) => ['key' => $k], $settings['header_order']);
        $settings['sidebar_order'] = array_map(fn ($k) => ['key' => $k], $settings['sidebar_order']);
        unset($settings['has_sidebar']);

        $this->form->fill($settings + ['social' => ArticleSettings::social()]);
    }

    public function form(Schema $schema): Schema
    {
        $limit = fn (string $key) => ArticleSettings::LIMITS[$key];
        $order = fn (string $name, array $labels) => Repeater::make($name)->hiddenLabel()
            ->schema([Hidden::make('key')])
            ->itemLabel(fn (array $state) => $labels[$state['key'] ?? ''] ?? '—')
            ->addable(false)->deletable(false)->reorderableWithDragAndDrop()->collapsed()->defaultItems(0);

        return $schema->statePath('data')->components([
            Section::make('Layout artikel')->columns(4)->schema([
                Select::make('layout')->label('Tata letak')->options(ArticleSettings::LAYOUTS)->required()->live()->columnSpan(1),
                TextInput::make('content_width')->label('Lebar konten (px)')->numeric()->required()
                    ->minValue($limit('content_width')[0])->maxValue($limit('content_width')[1]),
                TextInput::make('sidebar_width')->label('Lebar sidebar (px)')->numeric()->required()
                    ->minValue($limit('sidebar_width')[0])->maxValue($limit('sidebar_width')[1])
                    ->disabled(fn (Get $get) => $get('layout') === 'standard'),
                TextInput::make('gap')->label('Jarak konten–sidebar (px)')->numeric()->required()
                    ->minValue($limit('gap')[0])->maxValue($limit('gap')[1]),
            ])->description('Di layar kecil sidebar otomatis pindah ke bawah isi. Pada layout standar, widget yang masih aktif tampil di bawah artikel.'),

            Section::make('Komponen yang ditampilkan')->columns(4)->schema(
                collect(ArticleSettings::COMPONENTS)->map(fn ($label, $key) => Toggle::make("show.{$key}")->label($label)
                    ->helperText($key === 'title' ? 'Disembunyikan secara visual; tetap terbaca mesin pencari.' : null))->values()->all()
            ),

            Grid::make(2)->schema([
                Section::make('Urutan bagian atas artikel')->description('Seret untuk mengubah urutan setelah judul.')
                    ->schema([$order('header_order', ArticleSettings::HEADER_BLOCKS)]),
                Section::make('Widget sidebar')->description('Seret untuk mengurutkan; matikan untuk menyembunyikan.')
                    ->schema([
                        $order('sidebar_order', ArticleSettings::SIDEBAR_WIDGETS),
                        Grid::make(2)->schema(collect(ArticleSettings::SIDEBAR_WIDGETS)
                            ->map(fn ($label, $key) => Toggle::make("show_widgets.{$key}")->label($label))->values()->all()),
                    ]),
            ]),

            Section::make('Media sosial artikel')
                ->description('Terpisah dari media sosial Footer Builder. "Bagikan" memakai URL artikel yang sedang dibuka; "Profil" menuju akun resmi Anda. Platform yang disembunyikan tetap tersimpan.')
                ->schema([
                    Grid::make(3)->schema([
                        CheckboxList::make('social.positions')->label('Posisi')->options(ArticleSettings::SOCIAL_POSITIONS)
                            ->helperText(fn (Get $get) => $get('layout') === 'standard' ? 'Layout standar tidak punya sidebar: posisi Sidebar dialihkan ke akhir artikel.' : null),
                        TextInput::make('social.label')->label('Label')->maxLength(30)->placeholder('Bagikan'),
                        Toggle::make('social.show_copy')->label('Tombol "Salin tautan"')->inline(false),
                    ]),
                    Repeater::make('social.items')->label('Platform (seret untuk mengurutkan)')
                        ->schema([
                            Grid::make(4)->schema([
                                Select::make('platform')->label('Platform')->options(SocialPlatforms::options())->required()->live()->distinct(),
                                Select::make('type')->label('Jenis')->required()->live()
                                    ->options(fn (Get $get) => SocialPlatforms::canShare((string) $get('platform'))
                                        ? ['share' => 'Bagikan artikel', 'profile' => 'Profil akun']
                                        : ['profile' => 'Profil akun']),
                                TextInput::make('url')->label('URL profil')->maxLength(500)->placeholder('https://')
                                    ->visible(fn (Get $get) => $get('type') === 'profile')
                                    ->required(fn (Get $get) => $get('type') === 'profile' && $get('visible'))
                                    ->rule(fn (Get $get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                                        if (filled($value) && ! SocialPlatforms::isValidProfileUrl((string) $get('platform'), $value)) {
                                            $fail('URL harus https dan berada di domain resmi '.(SocialPlatforms::ALL[$get('platform')]['label'] ?? 'platform').'.');
                                        }
                                    }),
                                Toggle::make('visible')->label('Tampilkan')->default(true)->inline(false),
                            ]),
                        ])
                        ->itemLabel(fn (array $state) => (SocialPlatforms::ALL[$state['platform'] ?? '']['label'] ?? 'Platform baru')
                            .(($state['visible'] ?? false) ? '' : ' (disembunyikan)'))
                        ->reorderableWithDragAndDrop()->collapsible()
                        ->maxItems(count(SocialPlatforms::ALL))->addActionLabel('Tambah platform')->defaultItems(0),
                ]),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        $settings = collect($state)->only(['layout', 'content_width', 'sidebar_width', 'gap', 'show', 'show_widgets'])->all();
        $settings['header_order'] = array_column($state['header_order'] ?? [], 'key');
        $settings['sidebar_order'] = array_column($state['sidebar_order'] ?? [], 'key');

        $social = $state['social'] ?? [];
        // Jenis Bagikan hanya untuk platform yang punya tautan berbagi (Instagram selalu Profil)
        $social['items'] = collect($social['items'] ?? [])->map(fn ($i) => array_merge($i, [
            'type' => ($i['type'] ?? 'share') === 'share' && SocialPlatforms::canShare($i['platform']) ? 'share' : 'profile',
            'visible' => (bool) ($i['visible'] ?? false),
        ]))->values()->all();

        Setting::put(ArticleSettings::KEY, json_encode($settings));
        Setting::put(ArticleSettings::SOCIAL_KEY, json_encode($social));
        AuditLog::record(request(), 'article_settings.update', null, ['layout' => $settings['layout'], 'platforms' => count($social['items'])]);

        Notification::make()->title('Pengaturan artikel disimpan')->body('Perubahan langsung tampil di halaman artikel.')->success()->send();
    }
}
