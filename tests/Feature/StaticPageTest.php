<?php

use App\Filament\Resources\Pages\Pages\CreatePage;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Support\PageSectionTemplates;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->actingAs(User::where('email', 'admin@supermaterial.test')->first());
});

/** Berkas gambar blok contoh harus ada di disk publik agar field unggahan menerimanya. */
function fakeBlockImage(): void
{
    Storage::fake('public');
    Storage::disk('public')->put('pages/b.jpg', 'gambar');
}

/** State form Filament: field unggahan tunggal berisi daftar path, bukan string. */
function sampleFormBlocks(): array
{
    return array_map(fn (array $block) => $block['type'] === 'image'
        ? ['type' => 'image', 'data' => [...$block['data'], 'path' => [$block['data']['path']]]]
        : $block, sampleBlocks());
}

function sampleBlocks(array $override = []): array
{
    return [
        ['type' => 'heading', 'data' => ['level' => 'h2', 'text' => 'Tentang Kami', 'align' => 'center']],
        ['type' => 'text', 'data' => ['content' => '<p style="text-align: center">Halo <strong>dunia</strong></p><p><img src="/storage/pages/foto.jpg" alt="Foto" width="320" style="width: 320px"></p>']],
        ['type' => 'image', 'data' => ['path' => 'pages/b.jpg', 'alt' => 'Gudang', 'caption' => 'Gudang kami', 'align' => 'right']],
        ['type' => 'button', 'data' => ['label' => 'Hubungi', 'style' => 'primary', 'align' => 'left', 'link_type' => 'internal', 'url' => '/produk', 'new_tab' => false]],
        ['type' => 'list', 'data' => ['style' => 'number', 'items' => [['text' => 'Satu'], ['text' => 'Dua']]]],
        ['type' => 'divider', 'data' => ['spacing' => 'md']],
        ['type' => 'spacer', 'data' => ['height' => 48]],
        ['type' => 'embed', 'data' => ['layout' => '2', 'videos' => [
            ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'title' => 'Video'],
            ['url' => 'https://www.tiktok.com/@jayaintero/video/7312345678901234567', 'title' => 'Video TikTok'],
        ]]],
        ['type' => 'html', 'data' => ['html' => '<div class="kalkulator">Hitung</div><script>alert(1)</script>']],
        ...$override,
    ];
}

it('opens create and edit as full editor pages, not modals', function () {
    $page = Page::first();

    $this->get('/admin/pages')->assertOk()->assertSee('Tambah Halaman');
    $this->get('/admin/pages/create')->assertOk()->assertSee('Simpan draf')->assertSee('Terbitkan')->assertSee('Pratinjau');
    $this->get("/admin/pages/{$page->id}/edit")->assertOk()->assertSee('Konten halaman');
});

it('saves a new page as draft without publishing it', function () {
    fakeBlockImage();

    Livewire::test(CreatePage::class)
        ->fillForm(['title' => 'Tentang Kami', 'slug' => 'tentang-kami-baru', 'draft_blocks' => sampleFormBlocks()])
        ->call('submitAs', 'draft')
        ->assertHasNoFormErrors();

    $page = Page::where('slug', 'tentang-kami-baru')->first();
    expect($page->is_published)->toBeFalse()->and($page->draft_blocks)->toHaveCount(9);
    $this->get('/tentang-kami-baru')->assertNotFound();
});

it('publishes all block types to the public page with sanitized output', function () {
    fakeBlockImage();

    Livewire::test(CreatePage::class)
        ->fillForm(['title' => 'Tentang Kami', 'slug' => 'tentang-kami-baru', 'draft_blocks' => sampleFormBlocks()])
        ->call('submitAs', 'publish')
        ->assertHasNoFormErrors();

    $html = $this->get('/tentang-kami-baru')->assertOk()->getContent();

    expect($html)
        ->toContain('<h2 class="text-center">Tentang Kami</h2>')
        ->toContain('style="text-align: center"')
        ->toContain('style="width: 320px"')                  // ukuran hasil tarik tersimpan
        ->toContain('src="'.asset('storage/pages/b.jpg').'" alt="Gudang"') // blok gambar unggahan
        ->toContain('items-end')                                           // rata kanan
        ->toContain('Gudang kami')
        ->toContain('href="/produk"')
        ->toContain('<ol><li>Satu</li><li>Dua</li></ol>')
        ->toContain('style="height:48px"')
        ->toContain('youtube-nocookie.com/embed/dQw4w9WgXcQ')
        ->toContain('https://www.tiktok.com/player/v1/7312345678901234567')
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
    fakeBlockImage();
    $page = Page::first();
    $page->update(['draft_blocks' => sampleBlocks()]);

    Livewire::test(EditPage::class, ['record' => $page->id])
        ->call('submitAs', 'draft')
        ->assertHasNoFormErrors();

    expect(json_encode($page->fresh()->draft_blocks))->toContain('width: 320px');
});

it('converts legacy rich-text image blocks to uploaded image blocks', function () {
    $legacy = [
        ['type' => 'image', 'data' => ['image' => '<p style="text-align: right"><img src="https://supermaterial.test/storage/pages/a%20b.png" alt="Gudang &amp; toko" data-id="pages/a.png"></p>', 'width' => 50, 'width_unit' => '%', 'ratio' => '16/9', 'caption' => 'Gudang kami']],
        ['type' => 'image', 'data' => ['image' => '<p><img src="https://cdn.example.com/luar.png"></p>', 'caption' => null]],
        ['type' => 'heading', 'data' => ['level' => 'h2', 'text' => 'Tetap', 'align' => 'left']],
    ];

    $upgraded = Page::upgradeLegacyImageBlocks($legacy);

    expect($upgraded[0]['data'])->toBe(['path' => 'pages/a%20b.png', 'alt' => 'Gudang & toko', 'caption' => 'Gudang kami', 'align' => 'right'])
        ->and($upgraded[1])->toBe($legacy[1]) // gambar luar /storage tidak dikonversi dan tetap tampil dengan format lama
        ->and($upgraded[2])->toBe($legacy[2]);
});

it('summarizes each block content in its editor header', function () {
    fakeBlockImage();
    $page = Page::first();
    $page->update(['draft_blocks' => sampleBlocks()]);

    $this->get("/admin/pages/{$page->id}/edit")->assertOk()
        ->assertSee('Judul · H2 · Tentang Kami')
        ->assertSee('Paragraf · Halo dunia')
        ->assertSee('Gambar · Gudang')
        ->assertSee('Daftar · 2 butir · bernomor')
        ->assertSee('Video · 2 video · 2 kolom')
        ->assertSee('Spasi · 48 px');
});

it('appends a ready-made section of several blocks at the end of the page', function () {
    $page = Page::first();
    $page->update(['draft_blocks' => [['type' => 'heading', 'data' => ['level' => 'h2', 'text' => 'Sudah ada', 'align' => 'left']]]]);

    $component = Livewire::test(EditPage::class, ['record' => $page->id])
        ->callAction(TestAction::make('addSectionTemplate')->schemaComponent('draft_blocks'), ['template' => 'closing_cta']);

    $blocks = array_values($component->get('data.draft_blocks'));

    expect(array_slice(array_column($blocks, 'type'), 0, 4))->toBe(['heading', 'heading', 'text', 'button'])
        ->and($blocks[0]['data']['text'])->toBe('Sudah ada')
        ->and($blocks[3]['data'])->toMatchArray(['label' => 'Lihat semua produk', 'url' => '/produk']);
});

it('adds a whatsapp button to the closing section only when a number is set', function () {
    Setting::put('whatsapp_number', '0812-3456-7890');

    $buttons = array_values(array_filter(PageSectionTemplates::blocks('closing_cta'), fn ($block) => $block['type'] === 'button'));

    expect($buttons)->toHaveCount(2)
        ->and($buttons[1]['data'])->toMatchArray(['label' => 'Tanya via WhatsApp', 'url' => 'https://wa.me/6281234567890', 'link_type' => 'external']);
});

it('does not save a section template until its empty texts are filled in', function () {
    $page = Page::first();
    $page->update(['draft_blocks' => []]);

    Livewire::test(EditPage::class, ['record' => $page->id])
        ->callAction(TestAction::make('addSectionTemplate')->schemaComponent('draft_blocks'), ['template' => 'intro'])
        ->call('submitAs', 'draft')
        ->assertHasFormErrors();

    expect($page->fresh()->draft_blocks)->toBe([]);
});

it('shows several videos from one block side by side in the chosen layout', function () {
    $html = Page::renderBlocks([['type' => 'embed', 'data' => ['layout' => '3', 'videos' => [
        ['url' => 'https://www.tiktok.com/@jayaintero/video/7312345678901234567', 'title' => 'Pesanan pertama'],
        ['url' => 'https://www.tiktok.com/@jayaintero/video/7312345678901234568', 'title' => 'Pesanan kedua'],
        ['url' => 'https://contoh.com/bukan-video', 'title' => 'Dilewati'],
    ]]]]);

    expect($html)
        ->toContain('grid-cols-2 sm:grid-cols-3')   // video vertikal: 2 kolom di HP, 3 kolom di layar lebar
        ->toContain('title="Pesanan pertama"')
        ->toContain('player/v1/7312345678901234568')
        ->not->toContain('Dilewati');
});

it('upgrades legacy single-url video blocks without losing the url', function () {
    $legacy = [['type' => 'embed', 'data' => ['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'title' => 'Lama']]];

    expect(Page::upgradeLegacyEmbedBlocks($legacy)[0]['data'])
        ->toBe(['videos' => [['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'title' => 'Lama']], 'layout' => '1'])
        ->and(Page::renderBlocks($legacy))->toContain('youtube-nocookie.com/embed/dQw4w9WgXcQ');

    $page = Page::first();
    $page->update(['draft_blocks' => $legacy]);

    Livewire::test(EditPage::class, ['record' => $page->id])
        ->call('submitAs', 'draft')
        ->assertHasNoFormErrors();

    expect($page->fresh()->draft_blocks[0]['data']['videos'][0]['url'])->toBe('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
});

it('still renders legacy image blocks that could not be converted', function () {
    $html = Page::renderBlocks([['type' => 'image', 'data' => ['image' => '<p><img src="https://cdn.example.com/luar.png" alt="Luar"></p>', 'caption' => 'Dari CDN']]]);

    expect($html)->toContain('src="https://cdn.example.com/luar.png"')->toContain('Dari CDN');
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
