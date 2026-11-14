<?php

use App\Filament\Resources\LinkPages\Pages\EditLinkPage;
use App\Models\LinkItem;
use App\Models\LinkPage;
use App\Models\User;
use App\Support\LinkBioDesign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders the saved design as css variables, button style and font', function () {
    $page = LinkPage::factory()->create(['theme' => 'light', 'design' => [
        'bg_type' => 'gradient', 'bg_color' => '#0b3d91', 'bg_color_2' => '#061f4a',
        'button_style' => 'hard', 'button_radius' => 'pill', 'font' => 'montserrat',
    ]]);

    $this->get(route('link.show', $page->slug))->assertOk()
        ->assertSee('linear-gradient(180deg, #0B3D91 0%, #061F4A 100%)', false)
        ->assertSee('--lb-radius:999px', false)
        ->assertSee('lb-style-hard', false)
        ->assertSee('family=Montserrat', false);
});

it('drops unsafe or unknown design values instead of printing them into the page', function () {
    $page = LinkPage::factory()->create(['theme' => 'dark', 'design' => [
        'bg_color' => 'red;}</style><script>alert(1)</script>',
        'layout' => 'evil',
        'font' => 'comic',
        'bg_type' => 'image',
        'bg_image_path' => '../../.env',
    ]]);

    $html = $this->get(route('link.show', $page->slug))->assertOk()->getContent();

    expect($html)->not->toContain('<script>alert(1)')
        ->not->toContain('.env')
        ->toContain('--lb-bg:#0B0F19')
        ->toContain('family=Plus+Jakarta+Sans');
    expect(LinkBioDesign::resolve('dark', ['layout' => 'evil'])['layout'])->toBe('classic');
});

it('renders each header layout', function (string $layout, string $marker) {
    $page = LinkPage::factory()->create(['design' => ['layout' => $layout, 'shape' => 'flower'], 'banner_path' => 'link-bio/banner.jpg', 'avatar_path' => 'link-bio/foto.png']);

    $this->get(route('link.show', $page->slug))->assertOk()->assertSee($marker, false);
})->with([
    ['hero', 'lb-hero-fade absolute'],
    ['banner', 'link-bio/banner.jpg'],
    ['shape', 'lb-shape-flower h-52'],
    ['cutout', 'object-contain'],
]);

it('shows a live preview beside the editor and applies a theme preset', function () {
    $page = LinkPage::factory()->create(['title' => 'Bio Bengkel', 'theme' => 'light', 'design' => null]);
    LinkItem::factory()->for($page)->create(['label' => 'Katalog pagar']);
    $this->actingAs(User::factory()->create(['role' => 'editor', 'is_active' => true]));

    $component = Livewire::test(EditLinkPage::class, ['record' => $page->getRouteKey()])
        ->assertFormSet(['design.layout' => 'classic', 'design.bg_color' => '#F6F7F9'])
        ->assertSee('Pratinjau halaman Link Bio')
        ->assertSeeHtml('Katalog pagar');

    $component->fillForm(['theme' => 'jaya'])
        ->assertFormSet(['design.bg_type' => 'gradient', 'design.button_color' => '#FFC20E', 'design.font' => 'montserrat'])
        ->fillForm(['design.layout' => 'shape', 'design.shape' => 'burst', 'title' => 'Jaya Intero'])
        ->assertSeeHtml('lb-shape-burst')
        ->call('save')
        ->assertHasNoFormErrors();

    expect($page->refresh()->resolvedDesign())
        ->toMatchArray(['layout' => 'shape', 'shape' => 'burst', 'bg_color' => '#0B3D91', 'font' => 'montserrat']);
});

it('rejects colors that are not 6-digit hex', function () {
    $page = LinkPage::factory()->create();
    $this->actingAs(User::factory()->create(['role' => 'editor', 'is_active' => true]));

    Livewire::test(EditLinkPage::class, ['record' => $page->getRouteKey()])
        ->fillForm(['design.text_color' => 'red'])
        ->call('save')
        ->assertHasFormErrors(['design.text_color']);
});

it('lists a ready-to-copy bio link for every platform', function () {
    $page = LinkPage::factory()->create(['slug' => 'pembelian']);
    $this->actingAs(User::factory()->create(['role' => 'editor', 'is_active' => true]));

    $component = Livewire::test(EditLinkPage::class, ['record' => $page->getRouteKey()]);

    foreach (['instagram', 'tiktok', 'facebook', 'threads', 'youtube', 'linkedin'] as $source) {
        $component->assertFormSet(["bio_link_{$source}" => route('link.show', 'pembelian').'?utm_source='.$source]);
    }
});

it('saves a custom color per button from the editor', function () {
    $page = LinkPage::factory()->create();
    $item = LinkItem::factory()->for($page)->create(['label' => 'Shopee']);
    $this->actingAs(User::factory()->create(['role' => 'editor', 'is_active' => true]));

    $component = Livewire::test(EditLinkPage::class, ['record' => $page->getRouteKey()]);
    $key = array_key_first($component->get('data.items'));

    $component->set("data.items.{$key}.button_color", '#EE4D2D')
        ->call('save')
        ->assertHasNoFormErrors();

    expect($item->refresh()->button_color)->toBe('#EE4D2D');

    $component->set("data.items.{$key}.button_color", 'oranye')->call('save')
        ->assertHasFormErrors(["items.{$key}.button_color"]);
});
