<?php

use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Models\Page;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->actingAs(User::where('email', 'admin@supermaterial.test')->first());
});

function sampleBlocks(array $override = []): array
{
    return [
        ['type' => 'heading', 'data' => ['level' => 'h2', 'text' => 'Tentang Kami', 'align' => 'center']],
        ['type' => 'text', 'data' => ['content' => '<p style="text-align: center">Halo <strong>dunia</strong></p><p><img src="/storage/pages/foto.jpg" alt="Foto" width="320" style="width: 320px"></p>']],
        ['type' => 'image', 'data' => ['image' => '<p><img src="/storage/pages/b.jpg" alt="B"></p>', 'width' => 50, 'width_unit' => '%', 'ratio' => '16/9', 'caption' => 'Gudang kami']],
        ['type' => 'button', 'data' => ['label' => 'Hubungi', 'style' => 'primary', 'align' => 'left', 'link_type' => 'internal', 'url' => '/produk', 'new_tab' => false]],
        ['type' => 'list', 'data' => ['style' => 'number', 'items' => [['text' => 'Satu'], ['text' => 'Dua']]]],
        ['type' => 'divider', 'data' => ['spacing' => 'md']],
        ['type' => 'spacer', 'data' => ['height' => 48]],
        ['type' => 'embed', 'data' => ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'title' => 'Video']],
        ['type' => 'html', 'data' => ['html' => '<div class="kalkulator">Hitung</div><script>alert(1)</script>']],
        ...$override,
    ];
}

it('opens create and edit as full editor pages, not modals', function () {
    $page = Page::first();

    $this->get('/admin/pages')->assertOk()->assertSee('Tambah Halaman');
    $this->get('/admin/pages/create')->assertOk()->assertSee('Simpan Draft')->assertSee('Publikasikan')->assertSee('Pratinjau');
    $this->get("/admin/pages/{$page->id}/edit")->assertOk()->assertSee('Konten halaman');
});

it('saves a new page as draft without publishing it', function () {
    Livewire::test(CreatePage::class)
        ->fillForm(['title' => 'Tentang Kami', 'slug' => 'tentang-kami-baru', 'draft_blocks' => sampleBlocks()])
        ->call('submitAs', 'draft')
        ->assertHasNoFormErrors();

    $page = Page::where('slug', 'tentang-kami-baru')->first();
    expect($page->is_published)->toBeFalse()->and($page->draft_blocks)->toHaveCount(9);
    $this->get('/tentang-kami-baru')->assertNotFound();
});

it('publishes all block types to the public page with sanitized output', function () {
    Livewire::test(CreatePage::class)
        ->fillForm(['title' => 'Tentang Kami', 'slug' => 'tentang-kami-baru', 'draft_blocks' => sampleBlocks()])
        ->call('submitAs', 'publish')
        ->assertHasNoFormErrors();

    $html = $this->get('/tentang-kami-baru')->assertOk()->getContent();

    expect($html)
        ->toContain('<h2 class="text-center">Tentang Kami</h2>')
        ->toContain('style="text-align: center"')
        ->toContain('style="width: 320px"')                  // ukuran hasil tarik tersimpan
        ->toContain('style="--w:50%;--ratio:16/9"')           // lebar manual + rasio
        ->toContain('Gudang kami')
        ->toContain('href="/produk"')
        ->toContain('<ol><li>Satu</li><li>Dua</li></ol>')
        ->toContain('style="height:48px"')
        ->toContain('youtube-nocookie.com/embed/dQw4w9WgXcQ')
        ->toContain('<div class="kalkulator">Hitung</div>')
        ->not->toContain('alert(1)');
});

it('keeps edits as draft until published, and preview shows the draft', function () {
    $page = Page::published()->first();
    $page->update(['blocks' => [['type' => 'heading', 'data' => ['level' => 'h2', 'text' => 'Versi terbit', 'align' => 'left']]], 'draft_blocks' => null]);

    Livewire::test(EditPage::class, ['record' => $page->id])
        ->fillForm(['draft_blocks' => [['type' => 'heading', 'data' => ['level' => 'h2', 'text' => 'Versi draf', 'align' => 'left']]]])
        ->call('submitAs', 'draft')
        ->assertHasNoFormErrors();

    $this->get($page->url())->assertSee('Versi terbit')->assertDontSee('Versi draf');
    $this->get(route('page.preview-frame', $page))->assertOk()->assertSee('Versi draf')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $this->get(route('page.preview', $page))->assertOk()->assertSee('Kembali ke editor')->assertSee('Mobile');
    expect($page->fresh()->hasUnpublishedChanges())->toBeTrue();

    Livewire::test(EditPage::class, ['record' => $page->id])->call('submitAs', 'publish');
    $this->get($page->url())->assertSee('Versi draf');
});

it('keeps the resized image width when the editor is reopened', function () {
    $page = Page::first();
    $page->update(['draft_blocks' => sampleBlocks()]);

    Livewire::test(EditPage::class, ['record' => $page->id])
        ->call('submitAs', 'draft')
        ->assertHasNoFormErrors();

    expect(json_encode($page->fresh()->draft_blocks))->toContain('width: 320px');
});

it('redirects preview from the editor and restricts preview to admins and editors', function () {
    $page = Page::first();

    Livewire::test(EditPage::class, ['record' => $page->id])->call('submitAs', 'preview')
        ->assertRedirect(route('page.preview', $page));

    $this->actingAs(User::factory()->create(['role' => 'author']))->get(route('page.preview', $page))->assertForbidden();
    auth()->logout();
    $this->get(route('page.preview-frame', $page))->assertForbidden();
});

it('rejects unsafe button links', function () {
    Livewire::test(CreatePage::class)
        ->fillForm(['title' => 'X', 'slug' => 'x-page', 'draft_blocks' => [
            ['type' => 'button', 'data' => ['label' => 'Klik', 'style' => 'primary', 'align' => 'left', 'link_type' => 'external', 'url' => 'javascript:alert(1)']],
        ]])
        ->call('submitAs', 'draft')
        ->assertHasFormErrors();

    expect(Page::where('slug', 'x-page')->exists())->toBeFalse();
});

it('still renders legacy pages without blocks', function () {
    $page = Page::published()->first();
    $page->update(['blocks' => null, 'draft_blocks' => null, 'content' => '<p>Isi lama halaman</p>']);

    $this->get($page->url())->assertOk()->assertSee('Isi lama halaman');
});
