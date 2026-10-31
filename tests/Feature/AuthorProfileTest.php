<?php

use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Authors\Pages\ListAuthors;
use App\Models\Article;
use App\Models\Author;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(DatabaseSeeder::class));

it('lets a Penulis edit only their own author profile', function () {
    $user = User::factory()->create(['role' => 'author', 'name' => 'Anto']);
    $own = $user->authorProfile();
    $admin = Author::where('user_id', User::where('role', 'admin')->value('id'))->first();
    $this->actingAs($user);

    $this->get('/admin/authors')->assertOk()->assertSee('Anto')->assertDontSee($admin->job_title);
    $this->get("/admin/authors/{$own->id}/edit")->assertOk();
    $this->get("/admin/authors/{$admin->id}/edit")->assertNotFound();
    $this->get('/admin/authors/create')->assertForbidden();
});

it('gives Penulis and Kontributor accounts a writer profile as soon as they are created', function () {
    $penulis = User::factory()->create(['role' => 'author', 'name' => 'Rina Penulis']);
    $kontributor = User::factory()->create(['role' => 'contributor', 'name' => 'Budi PKL']);
    $editor = User::factory()->create(['role' => 'editor', 'name' => 'Edi Editor']);

    expect($penulis->author)->name->toBe('Rina Penulis')
        ->and($kontributor->author)->name->toBe('Budi PKL')
        ->and($editor->author)->toBeNull();

    // Peran diubah menjadi Penulis: profil dibuat; diubah bolak-balik: tidak ganda
    $editor->update(['role' => 'author']);
    $editor->update(['role' => 'editor']);
    $editor->update(['role' => 'contributor']);

    expect(Author::where('user_id', $editor->id)->count())->toBe(1);
});

it('hides the create button on the writer table from Penulis and Kontributor only', function (string $role, bool $canCreate) {
    $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => true]));

    $page = Livewire::test(ListAuthors::class);
    $canCreate ? $page->assertActionVisible('create') : $page->assertActionHidden('create');
})->with([
    'Penulis' => ['author', false],
    'Kontributor' => ['contributor', false],
    'Editor' => ['editor', true],
    'Admin' => ['admin', true],
]);

it('lets an admin publish under any writer name, including pen names and other users\' writers', function () {
    $admin = User::where('role', 'admin')->first();
    $penName = Author::create(['name' => 'Redaksi Supermaterial', 'slug' => 'redaksi', 'is_active' => true]);
    $otherUsersWriter = User::factory()->create(['role' => 'author', 'name' => 'Rina Penulis'])->author;
    $this->actingAs($admin);

    foreach ([$penName, $otherUsersWriter] as $i => $writer) {
        Livewire::test(CreateArticle::class)
            ->fillForm(['title' => "Artikel {$i}", 'slug' => "artikel-{$i}", 'content' => '<p>Isi</p>', 'author_id' => $writer->id])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Article::where('slug', "artikel-{$i}")->value('author_id'))->toBe($writer->id);
    }
});

it('shows avatar, motto and short bio on the public author page', function () {
    $author = User::where('role', 'admin')->first()->authorProfile();
    $author->update(['motto' => 'Bangun sekali, kokoh selamanya.', 'bio' => 'Spesialis semen.', 'avatar' => 'authors/anto.jpg']);
    $author->articles()->update(['published_at' => now()->subDay()]);

    $this->get("/penulis/{$author->slug}")->assertOk()
        ->assertSee('Bangun sekali, kokoh selamanya.')
        ->assertSee('Spesialis semen.')
        ->assertSee('storage/authors/anto.jpg', false);
});
