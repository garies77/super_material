<?php

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\SiteSettings;
use App\Filament\Resources\ApiTokens\Pages\ManageApiTokens;
use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\SalesChannels\Pages\ManageSalesChannels;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\Article;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\SalesChannel;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\LoginVerificationLink;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(DatabaseSeeder::class));

function admin(): User
{
    return User::where('email', 'admin@supermaterial.test')->first();
}

it('SEC-01: hides the custom code block from editors and authors without permission', function () {
    foreach (['editor', 'author'] as $role) {
        $this->actingAs(User::factory()->create(['role' => $role]));

        Livewire::test(CreateArticle::class)->assertFormFieldIsHidden('custom_html')
            ->assertFormFieldIsHidden('custom_css')->assertFormFieldIsHidden('custom_js');
    }
});

it('SEC-01: ignores custom code smuggled into the form by a user without permission', function () {
    $this->actingAs(User::factory()->create(['role' => 'editor']));

    Livewire::test(CreateArticle::class)
        ->fillForm(['title' => 'Uji XSS', 'slug' => 'uji-xss', 'content' => '<p>Isi</p>'])
        ->set('data.custom_html', '<script>alert(1)</script>')
        ->set('data.custom_js', 'alert(3)')
        ->call('create')->assertHasNoFormErrors();

    $article = Article::where('slug', 'uji-xss')->first();
    expect($article->custom_html)->toBeNull()->and($article->custom_js)->toBeNull();
});

it('SEC-02: renders custom code inline with the CSP nonce and CSS scoped to the block', function () {
    $article = Article::published()->first();
    $article->update([
        'custom_html' => '<div id="w">x</div><script type="application/ld+json">{"@type":"Thing"}</script><script nonce="palsu">window.a=1</script>',
        'custom_css' => '.k{color:red}</style><script>x</script>',
        'custom_js' => 'window.dataLayer=window.dataLayer||[];</script><b>',
    ]);

    $response = $this->get($article->url())->assertOk();
    $html = $response->getContent();
    preg_match("/'nonce-([^']+)'/", $response->headers->get('Content-Security-Policy'), $m);
    $nonce = $m[1];

    expect($html)->not->toContain('<iframe sandbox')
        ->toContain("<div id=\"cc-article-{$article->id}\" class=\"custom-html mt-8\"><div id=\"w\">x</div>")
        ->toContain("<script nonce=\"{$nonce}\" type=\"application/ld+json\">{\"@type\":\"Thing\"}</script>")
        ->toContain("<script nonce=\"{$nonce}\">window.a=1</script>")
        ->not->toContain('nonce="palsu"')
        ->toContain("<script nonce=\"{$nonce}\">window.dataLayer=window.dataLayer||[];<\/script><b></script>")
        ->toContain("#cc-article-{$article->id}{")->not->toContain('</style><script>x');
});

it('SEC-03: requires the admin password to change branding, and logs it', function () {
    $this->actingAs(admin());

    Livewire::test(SiteSettings::class)->set('data.theme_accent', '#123456')->call('save')
        ->assertHasErrors(['data.confirm_password']);
    expect(Setting::themeColor('theme_accent'))->not->toBe('#123456');

    Livewire::test(SiteSettings::class)->set('data.theme_accent', '#123456')->set('data.confirm_password', 'password')->call('save')
        ->assertHasNoErrors();
    expect(Setting::themeColor('theme_accent'))->toBe('#123456')
        ->and(AuditLog::where('action', 'branding.update')->first()->changes['theme_accent']['to'])->toBe('#123456');

    // Perubahan non-branding tidak meminta password
    Livewire::test(SiteSettings::class)->set('data.site_tagline', 'Tagline baru')->call('save')->assertHasNoErrors();
});

it('SEC-04: locks password confirmation after 5 failures and logs each failure', function () {
    $this->actingAs(admin());

    foreach (range(1, 5) as $i) {
        Livewire::test(SiteSettings::class)->set('data.theme_accent', '#123456')->set('data.confirm_password', 'salah')->call('save');
    }

    // Password benar pun ditolak selama terkunci
    Livewire::test(SiteSettings::class)->set('data.theme_accent', '#123456')->set('data.confirm_password', 'password')->call('save')
        ->assertHasErrors(['data.confirm_password']);

    expect(AuditLog::where('action', 'sudo.failed')->where('status', 'failed')->count())->toBe(5)
        ->and(Setting::themeColor('theme_accent'))->not->toBe('#123456');
});

it('SEC-04: requires password confirmation to promote a user to admin', function () {
    $this->actingAs(admin());
    $author = User::factory()->create(['role' => 'author']);

    Livewire::test(ManageUsers::class)
        ->callTableAction('edit', $author, ['role' => 'admin', 'sudo_password' => 'salah'])
        ->assertHasTableActionErrors(['sudo_password']);
    expect($author->fresh()->role)->toBe('author');

    Livewire::test(ManageUsers::class)
        ->callTableAction('edit', $author, ['role' => 'admin', 'sudo_password' => 'password'])
        ->assertHasNoTableActionErrors();
    expect($author->fresh()->role)->toBe('admin')
        ->and(AuditLog::where('action', 'user.access_changed')->where('subject_id', $author->id)->exists())->toBeTrue();
});

it('SEC-05: never redirects /go/ to a domain outside the allow-list', function () {
    $product = Product::published()->first();
    $channel = SalesChannel::where('slug', 'shopee')->first();
    $product->links()->updateOrCreate(['sales_channel_id' => $channel->id], ['url' => 'https://evil.com/phish', 'is_active' => true]);

    $this->get("/go/{$product->slug}/shopee")->assertRedirect($product->url());
});

it('SEC-06: sends security headers and a CSP on public and admin pages', function () {
    foreach (['/', '/admin/login'] as $url) {
        $response = $this->get($url);
        $response->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy');
        $csp = $response->headers->get('Content-Security-Policy');
        expect($csp)->toContain("default-src 'self'")->toContain("object-src 'none'")->toContain("frame-ancestors 'self'");

        // script-src berbasis nonce, tanpa 'unsafe-inline'; setiap skrip inline membawa nonce yang sama
        preg_match('/script-src ([^;]+)/', $csp, $scriptSrc);
        preg_match("/'nonce-([^']+)'/", $scriptSrc[1], $nonce);
        expect($scriptSrc[1])->not->toContain('unsafe-inline')->and($nonce[1] ?? null)->not->toBeEmpty();

        // JSON-LD adalah blok data yang tidak dieksekusi, jadi tidak perlu nonce
        preg_match_all('/<script\b(?![^>]*(?:\bsrc=|application\/ld\+json))[^>]*>/i', $response->getContent(), $inline);
        foreach ($inline[0] as $tag) {
            expect($tag)->toContain('nonce="'.$nonce[1].'"');
        }
    }
});

it('SEC-06: uses a fresh nonce on every request', function () {
    $nonce = fn () => preg_match("/'nonce-([^']+)'/", $this->get('/')->headers->get('Content-Security-Policy'), $m) ? $m[1] : null;

    expect($nonce())->not->toBe($nonce());
});

it('SEC-07: strips scripts from uploaded SVG logos before saving', function () {
    Storage::fake('public');
    $this->actingAs(admin());
    $svg = UploadedFile::fake()->createWithContent('logo.svg',
        '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"><script>alert(2)</script><rect width="10" height="10"/></svg>');
    $channel = SalesChannel::where('slug', 'shopee')->first();

    Livewire::test(ManageSalesChannels::class)
        ->callTableAction('edit', $channel, ['logo' => $svg])
        ->assertHasNoTableActionErrors();

    $stored = Storage::disk('public')->get($channel->fresh()->logo);
    expect($stored)->toContain('<rect')->not->toContain('<script')->not->toContain('onload');
});

it('SEC-08: stores only the SHA-256 hash of API tokens', function () {
    $this->actingAs(admin());

    Livewire::test(ManageApiTokens::class)
        ->callAction('create', ['user_id' => admin()->id, 'name' => 'n8n', 'abilities' => ['catalog:read']])
        ->assertHasNoActionErrors();

    $row = (array) DB::table('personal_access_tokens')->first();
    expect($row)->not->toHaveKey('plain_token')
        ->and($row['token'])->toMatch('/^[a-f0-9]{64}$/');
});

it('keeps audit logs append-only', function () {
    $log = AuditLog::create(['action' => 'x', 'created_at' => now()]);

    expect($log->update(['action' => 'y']))->toBeFalse()
        ->and($log->delete())->toBeFalse()
        ->and(AuditLog::find($log->id)->action)->toBe('x');
});

function attemptLogin(string $email, string $password)
{
    return Livewire::test(Login::class)
        ->fillForm(['email' => $email, 'password' => $password])
        ->call('authenticate');
}

it('logs in normally before any brute force is detected', function () {
    attemptLogin('admin@supermaterial.test', 'password')->assertHasNoFormErrors();

    $this->assertAuthenticatedAs(admin());
});

it('requires an email link after repeated failures, without revealing whether the password was right', function () {
    Notification::fake();

    foreach (range(1, 5) as $i) {
        attemptLogin('admin@supermaterial.test', "salah-{$i}");
        RateLimiter::clear('livewire-rate-limiter:'.sha1(Login::class.'|authenticate|127.0.0.1'));
    }
    expect(AuditLog::where('action', 'login.brute_force_detected')->count())->toBe(1);

    // Password benar: tidak langsung masuk, tautan dikirim ke email
    attemptLogin('admin@supermaterial.test', 'password')->assertNotified('Verifikasi email diperlukan');
    $this->assertGuest();

    $url = null;
    Notification::assertSentTo(admin(), LoginVerificationLink::class,
        function ($n) use (&$url) {
            $url = $n->url;

            return true;
        });

    // Password salah memberi pesan yang sama dan tidak mengirim tautan
    attemptLogin('admin@supermaterial.test', 'masih-salah')->assertNotified('Verifikasi email diperlukan');
    Notification::assertSentToTimes(admin(), LoginVerificationLink::class, 1);

    // Tautan membuka sesi sekali saja
    $this->get($url)->assertRedirect(Filament::getPanel('admin')->getUrl());
    $this->assertAuthenticatedAs(admin());

    auth()->logout();
    $this->get($url)->assertRedirect();
    $this->assertGuest();
});

it('rejects tampered login links', function () {
    $this->get('/admin/login/link/'.str_repeat('a', 64).'?expires=9999999999&signature=palsu')->assertForbidden();
    $this->assertGuest();
});

it('requires passwords longer than 8 characters with letters, numbers and symbols', function (string $password, bool $valid) {
    $passes = Validator::make(['p' => $password], ['p' => Password::default()])->passes();

    expect($passes)->toBe($valid);
})->with([
    'terlalu pendek' => ['Ab1!xyz8', false],
    'tanpa simbol' => ['Abcdefgh12', false],
    'tanpa angka' => ['Abcdefgh!!', false],
    'tanpa huruf' => ['123456789!', false],
    'memenuhi syarat' => ['Supermat1!', true],
]);

it('enforces the password policy when admins create users', function () {
    $this->actingAs(admin());

    Livewire::test(ManageUsers::class)
        ->callAction('create', ['name' => 'Budi', 'email' => 'budi@example.com', 'role' => 'author', 'password' => 'password'])
        ->assertHasActionErrors(['password']);

    expect(User::where('email', 'budi@example.com')->exists())->toBeFalse();
});
