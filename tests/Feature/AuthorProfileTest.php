<?php

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Authors\AuthorResource;
use App\Filament\Resources\Authors\Pages\EditAuthor;
use App\Filament\Resources\Authors\Pages\ListAuthors;
use App\Models\Article;
use App\Models\Author;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(DatabaseSeeder::class));

it('sends a Penulis from every writer page to their own Profil page', function () {
    $user = User::factory()->create(['role' => 'author', 'name' => 'Anto']);
    $own = $user->authorProfile();
    $admin = Author::where('user_id', User::where('role', 'admin')->value('id'))->first();
    $this->actingAs($user);

    // Tanpa tabel dan tanpa form penulis terpisah: semuanya diubah di halaman Profil akun
    $this->get('/admin/authors')->assertRedirect(EditProfile::getUrl());
    $this->get("/admin/authors/{$own->id}/edit")->assertRedirect(EditProfile::getUrl());
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

it('shows the writer table with a create button to Admin and Editor', function (string $role) {
    $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => true]));

    Livewire::test(ListAuthors::class)->assertActionVisible('create');
    expect(AuthorResource::shouldRegisterNavigation())->toBeTrue()
        ->and(AuthorResource::getNavigationUrl())->toBe(ListAuthors::getUrl());
})->with(['editor', 'admin']);

it('shows the writer profile as its own section on the Profil page for Penulis and Kontributor', function (string $role) {
    $user = User::factory()->create(['role' => $role, 'is_active' => true]);
    $this->actingAs($user);

    Livewire::test(ListAuthors::class)->assertRedirect(EditProfile::getUrl());
    // Satu menu "Profil saya" di sidebar, tanpa menu Penulis terpisah
    expect(AuthorResource::shouldRegisterNavigation())->toBeFalse();

    $this->get(EditProfile::getUrl())->assertOk()
        ->assertSee('Profil saya')
        ->assertSee('Akun')
        ->assertSee('Profil penulis')
        ->assertSee('Foto profil')
        ->assertSee('Lihat profil publik')
        ->assertSee('Kelengkapan profil')
        ->assertSee('Unggah foto profil yang jelas');
})->with(['author', 'contributor']);

it('links the Profil page from the sidebar for every role', function (string $role) {
    $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => true]));

    $sidebarItems = collect(filament()->getNavigation())->flatMap->getItems();
    $profileItem = $sidebarItems->first(fn ($item) => $item->getLabel() === 'Profil saya');

    expect($profileItem)->not->toBeNull()
        ->and($profileItem->getUrl())->toBe(EditProfile::getUrl())
        ->and($sidebarItems->filter(fn ($item) => $item->getUrl() === EditProfile::getUrl()))->toHaveCount(1);
})->with(['admin', 'editor', 'author', 'contributor']);

it('keeps the Profil page to account fields for users without a writer profile', function () {
    $this->actingAs(User::factory()->create(['role' => 'editor', 'is_active' => true]));

    $this->get(EditProfile::getUrl())->assertOk()
        ->assertSee('Akun')
        ->assertDontSee('Kelengkapan profil')
        ->assertDontSee('Lihat profil publik');
});

it('saves account and writer profile together, using one photo for both', function () {
    Storage::fake('public')->put('authors/anto.jpg', UploadedFile::fake()->image('anto.jpg', 400, 400)->getContent());
    $user = User::factory()->create(['role' => 'author', 'name' => 'Anto', 'is_active' => true]);
    $user->author->update(['avatar' => 'authors/anto.jpg']);
    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->fillForm([
            'name' => 'Anto Wijaya',
            'author.job_title' => 'Ahli semen',
            'author.motto' => 'Kokoh selamanya',
            'author.bio' => 'Berpengalaman di proyek beton.',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->refresh()->name)->toBe('Anto Wijaya')
        ->and($user->author->refresh())
        ->name->toBe('Anto Wijaya')
        ->job_title->toBe('Ahli semen')
        ->motto->toBe('Kokoh selamanya')
        ->avatar->toBe('authors/anto.jpg')
        ->and($user->getFilamentAvatarUrl())->toBe($user->author->avatarUrl());
});

it('rejects academic titles in the account name of a writer', function () {
    $user = User::factory()->create(['role' => 'author', 'is_active' => true]);
    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->fillForm(['name' => 'Ir. Anto, S.T.'])
        ->call('save')
        ->assertHasFormErrors(['name']);
});

it('never lets Penulis or Kontributor delete their own writer profile', function () {
    $user = User::factory()->create(['role' => 'contributor', 'is_active' => true]);
    $profile = $user->author;
    $this->actingAs($user);

    expect(fn () => $profile->delete())->toThrow(AuthorizationException::class)
        ->and(Author::whereKey($profile->id)->exists())->toBeTrue();

    // Admin tetap bisa
    $this->actingAs(User::where('role', 'admin')->first());
    Livewire::test(EditAuthor::class, ['record' => $profile->getRouteKey()])->assertActionVisible('delete');
});

it('scores writer profile completeness from the form as it is filled in', function () {
    $user = User::factory()->create(['role' => 'author', 'is_active' => true]);
    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->assertSee('0%')
        ->fillForm(['author.job_title' => 'Ahli semen', 'author.motto' => 'Kokoh selamanya', 'author.bio' => str_repeat('Berpengalaman di proyek beton. ', 3)])
        ->assertSee('60%')
        ->assertSee('3 dari 5 terpenuhi');
});

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
