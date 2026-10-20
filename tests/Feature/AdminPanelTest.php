<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets an admin open every admin screen', function () {
    $this->seed(DatabaseSeeder::class);
    $this->actingAs(User::where('email', 'admin@supermaterial.test')->first());

    foreach ([
        '/admin', '/admin/products', '/admin/products/create', '/admin/products/1/edit',
        '/admin/categories', '/admin/categories/create', '/admin/categories/1/edit',
        '/admin/articles', '/admin/articles/create', '/admin/articles/1/edit',
        '/admin/brands', '/admin/sales-channels', '/admin/pages', '/admin/users',
        '/admin/redirects', '/admin/click-events', '/admin/site-settings',
    ] as $url) {
        $this->get($url)->assertOk();
    }
});

it('keeps authors out of admin-only areas', function () {
    $this->seed(DatabaseSeeder::class);
    $author = User::factory()->create(['role' => 'author']);
    $this->actingAs($author);

    $this->get('/admin/users')->assertForbidden();
    $this->get('/admin/site-settings')->assertForbidden();
    $this->get('/admin/products')->assertForbidden();
    $this->get('/admin/articles')->assertOk();
});

it('allows editors to manage content but protects settings and users', function () {
    $this->seed(DatabaseSeeder::class);
    $editor = User::factory()->create(['role' => 'editor']);
    $this->actingAs($editor);

    $this->get('/admin/products')->assertOk();
    $this->get('/admin/categories')->assertOk();
    $this->get('/admin/articles')->assertOk();
    $this->get('/admin/users')->assertForbidden();
    $this->get('/admin/site-settings')->assertForbidden();
});

