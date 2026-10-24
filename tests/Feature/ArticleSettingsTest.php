<?php

use App\Filament\Pages\ArticleSettingsPage;
use App\Filament\Pages\Layout\FooterBuilder;
use App\Models\Article;
use App\Models\Setting;
use App\Models\User;
use App\Support\ArticleSettings;
use App\Support\SiteLayout;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->article = Article::published()->has('tags')->first() ?? Article::published()->first();
    $this->article->update(['summary' => 'RINGKASAN-UJI', 'content' => '<h2>Satu</h2><p>a</p><h2>Dua</h2><p>b</p><h2>Tiga</h2><p>c</p>']);
});

function saveArticleSettings(array $settings = [], ?array $social = null): void
{
    Setting::put(ArticleSettings::KEY, json_encode(array_replace(ArticleSettings::defaults(), $settings)));
    if ($social !== null) {
        Setting::put(ArticleSettings::SOCIAL_KEY, json_encode(array_replace(ArticleSettings::socialDefaults(), $social)));
    }
}

it('keeps the previous article look by default', function () {
    $html = $this->get($this->article->url())->assertOk()->getContent();

    expect($html)->toContain('data-article-layout="sidebar_right"')
        ->toContain('<aside')
        ->toContain('--content: 740px; --sb: 340px; --gap: 40px')
        ->toContain('RINGKASAN-UJI')
        ->toContain('Daftar isi');
});

it('switches between standard, right-sidebar, and left-sidebar layouts with custom widths', function () {
    saveArticleSettings(['layout' => 'standard']);
    $html = $this->get($this->article->url())->getContent();
    expect($html)->toContain('data-article-layout="standard"')->not->toContain('<aside');

    saveArticleSettings(['layout' => 'sidebar_left', 'content_width' => 800, 'sidebar_width' => 300, 'gap' => 24]);
    $html = $this->get($this->article->url())->getContent();
    expect($html)->toContain('lg:grid-cols-[var(--sb)_minmax(0,1fr)]')
        ->toContain('lg:order-1')->toContain('lg:order-2')
        ->toContain('--content: 800px; --sb: 300px; --gap: 24px');

    // Nilai di luar batas dipotong agar layout tidak rusak
    saveArticleSettings(['content_width' => 5000, 'sidebar_width' => 10, 'gap' => 999]);
    expect($this->get($this->article->url())->getContent())->toContain('--content: 960px; --sb: 240px; --gap: 80px');
});

it('hides and shows article components', function () {
    saveArticleSettings(['show' => ['summary' => false, 'toc' => false, 'breadcrumb' => false, 'title' => false, 'author' => false, 'tags' => false, 'author_box' => false]]);
    // Hanya isi halaman; ringkasan tetap dipakai sebagai meta description untuk SEO
    $html = $this->get($this->article->url())->getContent();
    $html = substr($html, strpos($html, '<article'));

    expect($html)->not->toContain('RINGKASAN-UJI')
        ->not->toContain('Daftar isi')
        ->not->toContain('Topik Terkait')
        ->not->toContain('Profil Penulis &amp; Artikel Lainnya')
        ->toContain('<h1 class="sr-only'); // judul tetap ada untuk SEO

    saveArticleSettings();
    $this->get($this->article->url())->assertSee('RINGKASAN-UJI')->assertSee('Daftar isi');
});

it('reorders the header blocks and sidebar widgets', function () {
    saveArticleSettings(['header_order' => ['toc', 'summary', 'meta', 'image'], 'sidebar_order' => ['categories', 'popular', 'consult', 'campaign']]);

    $this->get($this->article->url())
        ->assertSeeInOrder(['Daftar isi', 'RINGKASAN-UJI', 'menit baca'])
        ->assertSeeInOrder(['Kategori Terkait', 'Terpopuler', 'Konsultasi Material']);

    saveArticleSettings(['show_widgets' => ['popular' => false]]);
    $html = $this->get($this->article->url())->getContent();
    expect(preg_replace('#<aside.*?</aside>#s', '', $html))->not->toContain('Trending');
});

it('builds article social links: share uses the article URL, profiles use the configured account, order is kept', function () {
    saveArticleSettings(social: ['positions' => ['end'], 'items' => [
        ['platform' => 'instagram', 'type' => 'profile', 'url' => 'https://www.instagram.com/supermaterial/', 'visible' => true],
        ['platform' => 'threads', 'type' => 'share', 'url' => null, 'visible' => true],
        ['platform' => 'whatsapp', 'type' => 'share', 'url' => null, 'visible' => true],
        ['platform' => 'facebook', 'type' => 'share', 'url' => null, 'visible' => false],
        ['platform' => 'x', 'type' => 'profile', 'url' => 'https://evil.example.com/x', 'visible' => true],
    ]]);

    $encoded = rawurlencode($this->article->url());
    $html = $this->get($this->article->url())->getContent();

    expect($html)->toContain('href="https://www.instagram.com/supermaterial/"')
        ->toContain('https://www.threads.net/intent/post?text=')
        ->toContain($encoded)
        ->not->toContain('facebook.com/sharer')
        ->not->toContain('evil.example.com');
    $this->get($this->article->url())->assertSeeInOrder(['Ikuti kami di Instagram', 'Bagikan ke Threads', 'Bagikan ke WhatsApp'], false);
    expect(substr_count($html, 'data-article-social='))->toBe(1);
});

it('renders the sidebar position once, falling back to the end when there is no sidebar', function () {
    saveArticleSettings(['layout' => 'standard'], ['positions' => ['sidebar', 'end']]);
    $html = $this->get($this->article->url())->getContent();
    expect(substr_count($html, 'data-article-social="end"'))->toBe(1)->and($html)->not->toContain('data-article-social="sidebar"');

    saveArticleSettings(['layout' => 'sidebar_right'], ['positions' => ['sidebar']]);
    $html = $this->get($this->article->url())->getContent();
    expect($html)->toContain('data-article-social="sidebar"')->not->toContain('data-article-social="end"');
});

it('keeps article and footer social media independent', function () {
    Setting::put(SiteLayout::FOOTER_KEY, json_encode(['socials' => [['platform' => 'facebook', 'url' => 'https://facebook.com/footer-saja']]]));
    saveArticleSettings(social: ['positions' => ['end'], 'items' => [
        ['platform' => 'instagram', 'type' => 'profile', 'url' => 'https://www.instagram.com/artikel-saja/', 'visible' => true],
    ]]);

    $html = $this->get($this->article->url())->getContent();
    $footer = substr($html, strpos($html, '<footer'));
    $body = substr($html, 0, strpos($html, '<footer'));

    expect($body)->toContain('instagram.com/artikel-saja')->not->toContain('facebook.com/footer-saja')
        ->and($footer)->toContain('facebook.com/footer-saja')->not->toContain('instagram.com/artikel-saja');

    // Menyimpan Footer Builder tidak mengubah media sosial artikel
    $this->actingAs(User::where('email', 'admin@supermaterial.test')->first());
    Livewire::test(FooterBuilder::class)->set('data.socials', [['platform' => 'x', 'url' => 'https://x.com/baru']])->call('save');
    expect(ArticleSettings::social()['items'][0]['url'])->toBe('https://www.instagram.com/artikel-saja/');
});

it('saves settings from the admin page, validates profile URLs, and keeps them after reload', function () {
    $this->actingAs(User::where('email', 'admin@supermaterial.test')->first());

    $this->get('/admin/articles')->assertOk()->assertSee('Pengaturan Artikel');

    Livewire::test(ArticleSettingsPage::class)
        ->set('data.social.items', [['platform' => 'instagram', 'type' => 'profile', 'url' => 'https://facebook.com/salah', 'visible' => true]])
        ->call('save')
        ->assertHasErrors();

    Livewire::test(ArticleSettingsPage::class)
        ->set('data.layout', 'sidebar_left')
        ->set('data.show.summary', false)
        ->set('data.social.items', [
            ['platform' => 'instagram', 'type' => 'profile', 'url' => 'https://www.instagram.com/baru/', 'visible' => true],
            ['platform' => 'telegram', 'type' => 'share', 'url' => null, 'visible' => true],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $settings = ArticleSettings::get();
    expect($settings['layout'])->toBe('sidebar_left')
        ->and($settings['show']['summary'])->toBeFalse()
        ->and(ArticleSettings::social()['items'][0]['url'])->toBe('https://www.instagram.com/baru/');

    Livewire::test(ArticleSettingsPage::class)->assertSet('data.layout', 'sidebar_left')->assertSet('data.show.summary', false);
    $html = $this->get($this->article->url())->assertSee('t.me/share/url', false)->getContent();
    expect(substr($html, strpos($html, '<article')))->not->toContain('RINGKASAN-UJI')->toContain('data-article-layout="sidebar_left"');
});

it('restricts article settings to admins', function () {
    $this->actingAs(User::factory()->create(['role' => 'editor']));

    $this->get('/admin/pengaturan-artikel')->assertForbidden();
    $this->get('/admin/articles')->assertOk()->assertDontSee('Pengaturan Artikel');
});

it('renders a platform without a share link as its profile, even if stored as share', function () {
    saveArticleSettings(social: ['positions' => ['end'], 'items' => [
        ['platform' => 'instagram', 'type' => 'share', 'url' => 'https://www.instagram.com/lama/', 'visible' => true],
    ]]);

    $this->get($this->article->url())->assertSee('href="https://www.instagram.com/lama/"', false)->assertSee('Ikuti kami di Instagram');
});
