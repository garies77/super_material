<?php

use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Models\Article;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('email', 'admin@supermaterial.test')->first();
});

it('shows a modern "Publikasi & Penulis" form without print-era fields', function () {
    $this->actingAs($this->admin);

    $this->get('/admin/articles/create')->assertOk()->assertSee('Publikasi & Penulis')->assertDontSee('CNN Style');

    Livewire::test(CreateArticle::class)
        ->assertFormFieldDoesNotExist('author_initials')
        ->assertFormFieldDoesNotExist('editor_name')
        ->assertFormFieldDoesNotExist('editor_initials')
        ->assertFormFieldDoesNotExist('dateline')
        ->assertFormFieldExists('schema_type')
        ->assertFormFieldExists('is_featured');
});

it('pre-fills the author with the logged-in user profile, editable for admins', function () {
    $this->actingAs($this->admin);
    $own = $this->admin->authorProfile();

    Livewire::test(CreateArticle::class)
        ->assertFormSet(['author_id' => $own->id])
        ->assertFormFieldIsEnabled('author_id');
});

it('locks authors to their own profile, even if another author id is submitted', function () {
    $writer = User::factory()->create(['role' => 'author']);
    $ownProfile = $writer->authorProfile();
    $adminProfile = $this->admin->authorProfile();
    $this->actingAs($writer);

    Livewire::test(CreateArticle::class)
        ->assertFormFieldIsDisabled('author_id')
        ->assertFormSet(['author_id' => $ownProfile->id])
        ->fillForm(['title' => 'Tulisan Saya', 'slug' => 'tulisan-saya', 'content' => '<p>Isi</p>'])
        ->set('data.author_id', $adminProfile->id) // percobaan manipulasi lewat Livewire
        ->call('create')
        ->assertHasNoFormErrors();

    $article = Article::where('slug', 'tulisan-saya')->first();
    expect($article->author_id)->toBe($ownProfile->id);

    Livewire::test(EditArticle::class, ['record' => $article->id])
        ->set('data.author_id', $adminProfile->id)
        ->call('save')
        ->assertHasNoFormErrors();
    expect($article->fresh()->author_id)->toBe($ownProfile->id);
});

it('fills the publish date with the current WIB time via "Terbitkan Sekarang"', function () {
    $this->actingAs($this->admin);
    $this->travelTo('2026-10-23 05:15:00'); // 12:15 WIB

    Livewire::test(CreateArticle::class)
        ->fillForm(['title' => 'Cepat Terbit', 'slug' => 'cepat-terbit', 'content' => '<p>Isi</p>'])
        ->callFormComponentAction('published_at', 'publishNow')
        // Field menampilkan 12:15 WIB; nilai form (waktu aplikasi, UTC) = 05:15
        ->assertSet('data.published_at', '2026-10-23 12:15')
        ->assertFormSet(['published_at' => '2026-10-23 05:15'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Article::published()->where('slug', 'cepat-terbit')->exists())->toBeTrue()
        ->and(Article::where('slug', 'cepat-terbit')->first()->published_at->utc()->format('Y-m-d H:i'))->toBe('2026-10-23 05:15');
});

it('publishes with one click and turns published articles back into drafts from the table', function () {
    $this->actingAs($this->admin);
    $draft = Article::first();
    $draft->update(['published_at' => null]);
    $published = Article::published()->whereKeyNot($draft->id)->first();

    Livewire::test(ListArticles::class)
        ->assertTableActionVisible('publishNow', $draft)
        ->assertTableActionHidden('unpublish', $draft)
        ->callTableAction('publishNow', $draft)
        ->assertNotified('Artikel diterbitkan');
    expect($draft->fresh()->published_at)->not->toBeNull();

    Livewire::test(ListArticles::class)
        ->assertTableActionVisible('unpublish', $published)
        ->callTableAction('unpublish', $published)
        ->assertNotified('Artikel dijadikan draf');
    expect($published->fresh()->published_at)->toBeNull();
});

it('keeps rendering legacy articles that still have dateline and initials stored', function () {
    $article = Article::published()->first();
    $article->update(['content' => '<p>Paragraf pembuka asli.</p>', 'dateline' => 'Medan, Supermaterial --', 'author_initials' => 'gar', 'editor_initials' => 'ed']);

    $this->get($article->url())->assertOk()
        ->assertSee('<p>Paragraf pembuka asli.</p>', false)
        ->assertDontSee('<strong>Medan, Supermaterial --</strong>', false)
        ->assertDontSee('(gar/ed)');
    expect($article->initialsBlock())->toBeNull();
});
