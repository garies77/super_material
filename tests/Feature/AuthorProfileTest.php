<?php

use App\Models\Author;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

it('shows avatar, motto and short bio on the public author page', function () {
    $author = User::where('role', 'admin')->first()->authorProfile();
    $author->update(['motto' => 'Bangun sekali, kokoh selamanya.', 'bio' => 'Spesialis semen.', 'avatar' => 'authors/anto.jpg']);
    $author->articles()->update(['published_at' => now()->subDay()]);

    $this->get("/penulis/{$author->slug}")->assertOk()
        ->assertSee('Bangun sekali, kokoh selamanya.')
        ->assertSee('Spesialis semen.')
        ->assertSee('storage/authors/anto.jpg', false);
});
