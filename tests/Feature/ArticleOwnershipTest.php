<?php

use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Models\Article;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(DatabaseSeeder::class));

it('shows a new article in the list of the editor or author who created it', function (string $role) {
    $user = User::factory()->create(['role' => $role]);
    $this->actingAs($user);

    Livewire::test(CreateArticle::class)
        ->fillForm(['title' => "Artikel {$role}", 'slug' => "artikel-{$role}", 'content' => '<p>Isi</p>'])
        ->call('create')
        ->assertHasNoFormErrors();

    $article = Article::where('slug', "artikel-{$role}")->firstOrFail();

    expect($article->author->user_id)->toBe($user->id);
    Livewire::test(ListArticles::class)->assertCanSeeTableRecords([$article]);

    if ($role === 'author') {
        Livewire::test(ListArticles::class)->assertCanNotSeeTableRecords(Article::whereKeyNot($article->id)->get());
    }
})->with(['editor', 'author']);
