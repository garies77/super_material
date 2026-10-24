<?php

use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\Page;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(DatabaseSeeder::class));

it('allows admins and whitelisted editors or authors, nobody else', function () {
    $admin = User::where('email', 'admin@supermaterial.test')->first();
    $editor = User::factory()->create(['role' => 'editor']);
    $trusted = User::factory()->create(['role' => 'author', 'can_manage_custom_html' => true]);
    $inactive = User::factory()->create(['role' => 'editor', 'can_manage_custom_html' => true, 'is_active' => false]);

    expect($admin->canManageCustomHtml())->toBeTrue()
        ->and($editor->canManageCustomHtml())->toBeFalse()
        ->and($trusted->canManageCustomHtml())->toBeTrue()
        ->and($inactive->canManageCustomHtml())->toBeFalse();
});

it('shows the block to a whitelisted author and saves their raw code', function () {
    $this->actingAs(User::factory()->create(['role' => 'author', 'can_manage_custom_html' => true]));

    Livewire::test(CreateArticle::class)->assertFormFieldIsVisible('custom_js')
        ->fillForm(['title' => 'Kode Tepercaya', 'slug' => 'kode-tepercaya', 'content' => '<p>Isi</p>',
            'custom_html' => '<script type="application/ld+json">{"a":1}</script>', 'custom_js' => 'window.ok=1'])
        ->call('create')->assertHasNoFormErrors();

    $article = Article::where('slug', 'kode-tepercaya')->first();
    expect($article->custom_html)->toContain('application/ld+json')->and($article->custom_js)->toBe('window.ok=1');
});

it('rejects custom code changes from users without permission on every model, whatever the entry point', function () {
    $this->actingAs(User::factory()->create(['role' => 'editor']));

    foreach ([Article::first(), Product::first(), Page::first()] as $model) {
        expect(fn () => $model->update(['custom_js' => 'alert(1)']))->toThrow(AuthorizationException::class);
        expect($model->fresh()->custom_js)->toBeNull();
    }

    // Menyimpan bagian lain tetap boleh
    Article::first()->update(['summary' => 'Ringkasan baru']);
    expect(Article::first()->summary)->toBe('Ringkasan baru');
});

it('lets only admins change the permission flag, with password confirmation, and logs it', function () {
    $editor = User::factory()->create(['role' => 'editor']);

    $this->actingAs($editor);
    expect(fn () => $editor->update(['can_manage_custom_html' => true]))->toThrow(AuthorizationException::class);

    $this->actingAs(User::where('email', 'admin@supermaterial.test')->first());
    Livewire::test(ManageUsers::class)
        ->callTableAction('edit', $editor, ['can_manage_custom_html' => true])
        ->assertHasTableActionErrors(['sudo_password']);
    expect($editor->fresh()->can_manage_custom_html)->toBeFalse();

    Livewire::test(ManageUsers::class)
        ->callTableAction('edit', $editor, ['can_manage_custom_html' => true, 'sudo_password' => 'password'])
        ->assertHasNoTableActionErrors();
    expect($editor->fresh()->can_manage_custom_html)->toBeTrue()
        ->and(AuditLog::where('action', 'user.access_changed')->where('subject_id', $editor->id)->latest('id')->first()->changes)
        ->toHaveKey('can_manage_custom_html');
});
