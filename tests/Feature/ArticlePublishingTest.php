<?php

use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Models\Article;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->actingAs(User::where('email', 'admin@supermaterial.test')->first());
});

it('reads the scheduled time as WIB, so a time just past is published', function () {
    $this->travelTo('2026-10-23 02:40:00'); // 09:40 WIB

    Livewire::test(CreateArticle::class)
        ->fillForm(['title' => 'Jadwal', 'slug' => 'jadwal', 'content' => '<p>Isi</p>', 'published_at' => '2026-10-23 09:38:00'])
        ->call('create')->assertHasNoFormErrors();

    $article = Article::where('slug', 'jadwal')->first();
    expect($article->published_at->utc()->format('Y-m-d H:i'))->toBe('2026-10-23 02:38')
        ->and(Article::published()->whereKey($article->id)->exists())->toBeTrue();
});

it('publishes drafts and scheduled articles immediately from the table', function () {
    $draft = Article::first();
    $draft->update(['published_at' => null]);
    $scheduled = Article::whereKeyNot($draft->id)->first();
    $scheduled->update(['published_at' => now()->addWeek()]);

    Livewire::test(ListArticles::class)->callTableAction('publishNow', $draft);
    Livewire::test(ListArticles::class)->callTableBulkAction('publishNow', [$scheduled]);

    expect(Article::published()->whereKey([$draft->id, $scheduled->id])->count())->toBe(2);
});
