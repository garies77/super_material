<?php

use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Models\Article;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(function () {
    TrustProxies::flushState();
    URL::forceHttps(false);
});

/** Jalankan aplikasi sebagai produksi dengan konfigurasi HTTPS tertentu, plus rute uji tanpa database. */
function asProduction(?string $trustedProxies = null): void
{
    app()->detectEnvironment(fn () => 'production');
    config(['security.trusted_proxies' => $trustedProxies, 'security.force_https' => true]);
    app()->getProvider(AppServiceProvider::class)->configureHttps();
    Route::get('/_uji-https', fn () => url('/produk'));
}

it('redirects plain HTTP to HTTPS in production, except the health check', function () {
    asProduction();

    $this->get('http://supermaterial.test/_uji-https?x=1')
        ->assertStatus(301)->assertRedirect('https://supermaterial.test/_uji-https?x=1');
    $this->get('http://supermaterial.test/up')->assertOk();
});

it('trusts X-Forwarded-Proto from configured proxies, then serves https URLs with HSTS', function () {
    asProduction('*');

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
        ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Port' => '443'])
        ->get('http://supermaterial.test/_uji-https')
        ->assertOk()->assertSee('https://supermaterial.test/produk')
        ->assertHeader('Strict-Transport-Security');
});

it('ignores forwarded headers when no proxy is trusted', function () {
    asProduction();

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
        ->withHeaders(['X-Forwarded-Proto' => 'https'])
        ->get('http://supermaterial.test/_uji-https')
        ->assertStatus(301);
});

it('refuses to run the demo DatabaseSeeder in production', function () {
    app()->detectEnvironment(fn () => 'production');

    app(DatabaseSeeder::class)->run();

    expect(User::where('email', 'admin@supermaterial.test')->exists())->toBeFalse()
        ->and(Article::count())->toBe(0);
});

it('shows a branded 404 page with search and no debug details', function () {
    $this->seed(DatabaseSeeder::class);
    config(['app.debug' => false]);

    $this->get('/halaman-yang-tidak-ada')->assertNotFound()
        ->assertSee('Halaman tidak ditemukan')->assertSee('action="'.route('search').'"', false)
        ->assertSee('noindex', false);
});

it('shows a standalone 500 page without the exception message when debug is off', function () {
    config(['app.debug' => false]);
    Route::get('/_uji-galat', fn () => throw new RuntimeException('RAHASIA-INTERNAL'));

    $this->get('/_uji-galat')->assertStatus(500)
        ->assertSee('Maaf, terjadi kesalahan di server kami')->assertDontSee('RAHASIA-INTERNAL');
});

it('lists draft articles first in the admin table', function () {
    $this->seed(DatabaseSeeder::class);
    $this->actingAs(User::where('email', 'admin@supermaterial.test')->first());
    $draft = Article::published()->oldest('published_at')->first();
    $draft->update(['published_at' => null]);

    $records = Livewire::test(ListArticles::class)->instance()->getTableRecords();

    expect($records->first()->is($draft))->toBeTrue();
});
