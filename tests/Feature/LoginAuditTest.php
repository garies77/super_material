<?php

use App\Filament\Pages\Auth\Login;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(DatabaseSeeder::class));

it('records a successful panel login on the user and in the audit log', function () {
    $this->freezeTime();

    Livewire::test(Login::class)
        ->fillForm(['email' => 'admin@supermaterial.test', 'password' => 'password'])
        ->call('authenticate')->assertHasNoFormErrors();

    $admin = User::where('email', 'admin@supermaterial.test')->first();
    $log = AuditLog::where('action', 'auth.login')->latest('id')->first();

    expect($admin->last_login_at->toDateTimeString())->toBe(now()->toDateTimeString())
        ->and($admin->last_login_ip)->not->toBeNull()
        ->and($log->user_id)->toBe($admin->id)
        ->and($log->subject_id)->toBe($admin->id)
        ->and($log->changes)->toMatchArray(['ip' => $admin->last_login_ip, 'via' => 'form']);
});

it('stores the client IP and browser of the login request', function () {
    $this->app->instance('request', Request::create('/admin/login', 'POST', server: ['REMOTE_ADDR' => '203.0.113.7', 'HTTP_USER_AGENT' => 'Mozilla/5.0 UjiBrowser']));
    $admin = User::where('email', 'admin@supermaterial.test')->first();

    Auth::login($admin);

    expect($admin->fresh()->last_login_ip)->toBe('203.0.113.7')
        ->and(AuditLog::where('action', 'auth.login')->latest('id')->first()->changes)
        ->toMatchArray(['ip' => '203.0.113.7', 'user_agent' => 'Mozilla/5.0 UjiBrowser']);
});

it('does not record failed logins as successful', function () {
    Livewire::test(Login::class)
        ->fillForm(['email' => 'admin@supermaterial.test', 'password' => 'salah-sekali'])
        ->call('authenticate')->assertHasFormErrors();

    expect(AuditLog::where('action', 'auth.login')->exists())->toBeFalse()
        ->and(User::where('email', 'admin@supermaterial.test')->first()->last_login_at)->toBeNull();
});

it('keeps login history append-only', function () {
    $admin = User::where('email', 'admin@supermaterial.test')->first();
    $this->actingAs($admin);
    $log = AuditLog::record(request(), 'auth.login', $admin, ['ip' => '198.51.100.1'], actor: $admin);

    $log->changes = ['ip' => '127.0.0.1'];
    $log->save();
    $log->delete();

    expect(AuditLog::find($log->id)->changes['ip'])->toBe('198.51.100.1');
});

it('shows the last login and the login history to admins in the users table', function () {
    $admin = User::where('email', 'admin@supermaterial.test')->first();
    $admin->forceFill(['last_login_at' => now()->subHour(), 'last_login_ip' => '192.0.2.44'])->saveQuietly();
    AuditLog::record(request(), 'auth.login', $admin, ['ip' => '192.0.2.44', 'user_agent' => 'Mozilla/5.0 Riwayat', 'via' => 'form'], actor: $admin);
    $this->actingAs($admin);

    Livewire::test(ManageUsers::class)
        ->assertSee('192.0.2.44')->assertSee(now()->subHour()->locale(app()->getLocale())->diffForHumans())
        ->mountTableAction('loginHistory', $admin)
        ->assertMountedActionModalSee(['Mozilla/5.0 Riwayat', 'Form login', '192.0.2.44']);
});
