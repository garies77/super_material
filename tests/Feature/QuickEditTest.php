<?php

use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Article;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\Redirect;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->actingAs(User::where('email', 'admin@supermaterial.test')->first());
});

it('opens published pages on the site and drafts in preview', function () {
    $published = Page::published()->first();
    $draft = Page::create(['title' => 'Draf', 'slug' => 'draf-uji', 'is_published' => false]);

    Livewire::test(ListPages::class)
        ->assertActionHasUrl(TestAction::make('preview')->table($published), $published->url())
        ->assertActionHasLabel(TestAction::make('preview')->table($published), 'Lihat')
        ->assertActionHasUrl(TestAction::make('preview')->table($draft), route('page.preview', $draft))
        ->assertActionHasLabel(TestAction::make('preview')->table($draft), 'Pratinjau');
});

it('quick edits a page title and slug and keeps the old url redirecting', function () {
    $page = Page::published()->first();
    $oldPath = '/'.$page->slug;

    Livewire::test(ListPages::class)
        ->callAction(TestAction::make('quickEdit')->table($page), ['title' => 'Judul Baru', 'slug' => 'judul-baru'])
        ->assertHasNoFormErrors();

    expect($page->fresh()->only(['title', 'slug']))->toBe(['title' => 'Judul Baru', 'slug' => 'judul-baru'])
        ->and(Redirect::where('from_path', $oldPath)->value('to_path'))->toBe('/judul-baru');
});

it('rejects a quick edit slug that another page already uses', function () {
    [$page, $other] = Page::take(2)->get();

    Livewire::test(ListPages::class)
        ->callAction(TestAction::make('quickEdit')->table($page), ['title' => $page->title, 'slug' => $other->slug])
        ->assertHasFormErrors(['slug' => 'unique']);
});

it('quick edits an article including its categories and schedule', function () {
    $article = Article::first();
    $category = Category::first();

    Livewire::test(ListArticles::class)
        ->callAction(TestAction::make('quickEdit')->table($article), [
            'title' => 'Artikel diperbarui', 'slug' => $article->slug, 'categories' => [$category->id], 'tags' => [], 'published_at' => null,
        ])
        ->assertHasNoFormErrors();

    $article->refresh();
    expect($article->title)->toBe('Artikel diperbarui')
        ->and($article->published_at)->toBeNull()
        ->and($article->categories->pluck('id')->all())->toBe([$category->id]);
});

it('quick edits a product name, badge and visibility', function () {
    $product = Product::where('is_published', true)->first();

    Livewire::test(ListProducts::class)
        ->callAction(TestAction::make('quickEdit')->table($product), ['name' => 'Nama Baru', 'slug' => $product->slug, 'badge' => 'SNI', 'is_published' => false])
        ->assertHasNoFormErrors();

    expect($product->fresh()->only(['name', 'badge', 'is_published']))->toBe(['name' => 'Nama Baru', 'badge' => 'SNI', 'is_published' => false]);
});

it('hides quick edit from contributors so their changes still go through review', function () {
    $this->actingAs(User::factory()->create(['role' => 'contributor']));

    Livewire::test(ListProducts::class)
        ->assertActionHidden(TestAction::make('quickEdit')->table(Product::first()));
});
