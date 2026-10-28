<?php

use App\Filament\Pages\ManageHomepage;
use App\Filament\Pages\SiteSettings;
use App\Filament\Resources\Ads\AdResource;
use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Filament\Resources\Brands\BrandResource;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\CustomForms\CustomFormResource;
use App\Filament\Resources\FormSubmissions\FormSubmissionResource;
use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Users\UserResource;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->contributor = User::factory()->create(['role' => 'contributor', 'is_active' => true]);
    $this->editor = User::factory()->create(['role' => 'editor', 'is_active' => true]);
});

it('gives contributors products, articles and media but nothing else', function () {
    $this->actingAs($this->contributor);

    foreach (['/admin/products', '/admin/products/create', '/admin/articles', '/admin/articles/create', '/admin/media'] as $url) {
        $this->get($url)->assertOk();
    }

    foreach ([CategoryResource::class, BrandResource::class, PageResource::class, AdResource::class, CustomFormResource::class,
        FormSubmissionResource::class, UserResource::class, SiteSettings::class, ManageHomepage::class] as $screen) {
        $this->get($screen::getUrl())->assertForbidden();
    }
});

it('saves products created by a contributor as drafts even when publishing is requested', function () {
    $this->actingAs($this->contributor);

    Livewire::test(CreateProduct::class)
        ->fillForm(['name' => 'Semen Gresik', 'slug' => 'semen-gresik', 'is_published' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    $viaModel = Product::create(['name' => 'Bata', 'slug' => 'bata', 'is_published' => true, 'is_featured' => true]);

    expect(Product::firstWhere('slug', 'semen-gresik')->only(['is_published', 'created_by']))->toBe(['is_published' => false, 'created_by' => $this->contributor->id])
        ->and($viaModel->only(['is_published', 'is_featured']))->toBe(['is_published' => false, 'is_featured' => false]);
});

it('lets contributors edit only their own unpublished drafts', function () {
    $own = Product::create(['name' => 'Draf saya', 'slug' => 'draf-saya', 'created_by' => $this->contributor->id]);
    $published = Product::create(['name' => 'Tayang', 'slug' => 'tayang', 'created_by' => $this->contributor->id, 'is_published' => true]);
    $others = Product::create(['name' => 'Draf orang lain', 'slug' => 'draf-lain', 'created_by' => $this->editor->id]);
    $this->actingAs($this->contributor);

    $this->get("/admin/products/{$own->id}/edit")->assertOk();
    $this->get("/admin/products/{$published->id}/edit")->assertForbidden();
    $this->get("/admin/products/{$others->id}/edit")->assertForbidden();

    expect(fn () => $published->update(['name' => 'Diubah diam-diam']))->toThrow(AuthorizationException::class)
        ->and(fn () => $own->update(['is_published' => true]))->toThrow(AuthorizationException::class)
        ->and(fn () => $own->delete())->toThrow(AuthorizationException::class);
});

it('hides publishing, deleting and importing from contributors', function () {
    $draft = Product::create(['name' => 'Draf saya', 'slug' => 'draf-saya', 'created_by' => $this->contributor->id]);
    $this->actingAs($this->contributor);

    Livewire::test(ListProducts::class)
        ->assertActionHidden('import_csv')
        ->assertTableBulkActionHidden('publish')
        ->assertTableBulkActionHidden('delete');

    Livewire::test(EditProduct::class, ['record' => $draft->getRouteKey()])
        ->assertFormFieldIsDisabled('is_published')
        ->assertActionHidden('delete')
        ->assertActionVisible('submitForReview')
        ->assertActionHidden('approve');
});

it('runs the submit, request changes, and approve review cycle', function () {
    $draft = Product::create(['name' => 'Semen Gresik', 'slug' => 'semen-gresik', 'created_by' => $this->contributor->id]);

    $this->actingAs($this->contributor);
    Livewire::test(EditProduct::class, ['record' => $draft->getRouteKey()])->callAction('submitForReview');
    expect($draft->refresh()->only(['review_status', 'submitted_by']))->toBe(['review_status' => 'pending', 'submitted_by' => $this->contributor->id]);

    $this->actingAs($this->editor);
    Livewire::test(EditProduct::class, ['record' => $draft->getRouteKey()])
        ->callAction('requestChanges', ['note' => 'Foto utama buram, tolong ganti.']);
    expect($draft->refresh()->review_status)->toBe('changes_requested');

    $this->actingAs($this->contributor);
    $this->get("/admin/products/{$draft->id}/edit")->assertSee('Foto utama buram, tolong ganti.');
    Livewire::test(EditProduct::class, ['record' => $draft->getRouteKey()])->callAction('submitForReview');

    $this->actingAs($this->editor);
    Livewire::test(EditProduct::class, ['record' => $draft->getRouteKey()])->callAction('approve');

    expect($draft->refresh()->only(['is_published', 'review_status', 'review_note']))->toBe(['is_published' => true, 'review_status' => null, 'review_note' => null])
        ->and(AuditLog::whereIn('action', ['review.submitted', 'review.changes_requested', 'review.approved'])->count())->toBe(4);

    $this->actingAs($this->contributor);
    $this->get("/admin/products/{$draft->id}/edit")->assertForbidden();
});

it('limits contributors to their own articles and lets an editor approve them', function () {
    $mine = $this->contributor->authorProfile();
    $someoneElse = $this->editor->authorProfile();
    $other = Article::create(['title' => 'Artikel editor', 'slug' => 'artikel-editor', 'content' => '<p>Isi</p>', 'author_id' => $someoneElse->id]);
    $this->actingAs($this->contributor);

    Livewire::test(CreateArticle::class)
        ->fillForm(['title' => 'Tips memilih semen', 'slug' => 'tips-memilih-semen', 'content' => '<p>Isi</p>', 'published_at' => now()])
        ->call('create')
        ->assertHasNoFormErrors();
    $article = Article::firstWhere('slug', 'tips-memilih-semen');

    expect($article->only(['author_id', 'published_at']))->toBe(['author_id' => $mine->id, 'published_at' => null]);
    Livewire::test(ListArticles::class)->assertCanSeeTableRecords([$article])->assertCanNotSeeTableRecords([$other])
        ->assertTableActionHidden('publishNow', $article);

    Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])->callAction('submitForReview');

    $this->actingAs($this->editor);
    Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])->callAction('approve');

    expect($article->refresh()->published_at)->not->toBeNull();
});

it('shows the review queue to editors on the dashboard only while something is waiting', function () {
    $this->actingAs($this->editor);
    $this->get('/admin')->assertOk()->assertDontSee('Produk menunggu tinjauan');

    Product::create(['name' => 'Semen', 'slug' => 'semen', 'review_status' => 'pending', 'submitted_by' => $this->contributor->id, 'submitted_at' => now()]);

    $this->get('/admin')->assertOk()->assertSee('Produk menunggu tinjauan');
    $this->actingAs($this->contributor)->get('/admin')->assertOk()->assertDontSee('Produk menunggu tinjauan');
});

it('makes a duplicate of a published product into a draft owned by the contributor', function () {
    $published = Product::create(['name' => 'Semen 40 kg', 'slug' => 'semen-40-kg', 'is_published' => true, 'created_by' => $this->editor->id]);
    $this->actingAs($this->contributor);

    Livewire::test(ListProducts::class)->callTableAction('duplicate', $published);

    expect(Product::firstWhere('slug', 'semen-40-kg-salinan')->only(['is_published', 'created_by']))
        ->toBe(['is_published' => false, 'created_by' => $this->contributor->id]);
});

it('duplicates for an editor without custom HTML permission, leaving the custom code behind', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]));
    $product = Product::create(['name' => 'Semen', 'slug' => 'semen', 'custom_html' => '<div>promo</div>']);
    $this->actingAs($this->editor);

    Livewire::test(ListProducts::class)->callTableAction('duplicate', $product);

    expect(Product::firstWhere('slug', 'semen-salinan')?->custom_html)->toBeNull();
});

it('never grants custom HTML to contributors', function () {
    $this->contributor->forceFill(['can_manage_custom_html' => true])->saveQuietly();

    expect($this->contributor->canManageCustomHtml())->toBeFalse();
});
