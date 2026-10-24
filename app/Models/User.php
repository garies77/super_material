<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'is_active', 'can_manage_custom_html'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLES = ['admin' => 'Admin', 'editor' => 'Editor', 'author' => 'Penulis'];

    protected $attributes = ['is_active' => true];

    protected static function booted(): void
    {
        // Izin Custom HTML hanya boleh diubah Admin, apa pun jalurnya (form, API, mass assignment)
        static::saving(function (User $user) {
            if ($user->isDirty('can_manage_custom_html') && auth()->check() && ! auth()->user()->isAdmin()) {
                throw new AuthorizationException('Hanya Admin yang dapat mengubah izin Custom HTML.');
            }
        });

        static::updated(function (User $user) {
            $changes = collect(['role', 'is_active', 'can_manage_custom_html'])->filter(fn ($f) => $user->wasChanged($f))
                ->mapWithKeys(fn ($f) => [$f => ['from' => $user->getOriginal($f), 'to' => $user->{$f}]])->all();
            if ($changes) {
                AuditLog::record(request(), 'user.access_changed', $user, $changes);
            }
        });
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && in_array($this->role, array_keys(self::ROLES), true);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /** Custom HTML/CSS/JS tampil tanpa sandbox di halaman publik: Admin, atau Editor/Penulis yang diberi izin. */
    public function canManageCustomHtml(): bool
    {
        return $this->is_active && ($this->isAdmin() || ($this->can_manage_custom_html && isset(self::ROLES[$this->role])));
    }

    public function author(): HasOne
    {
        return $this->hasOne(Author::class);
    }

    /** Profil penulis milik pengguna; dibuat otomatis saat pertama kali menulis artikel. */
    public function authorProfile(): Author
    {
        if ($author = $this->author()->first()) {
            return $author;
        }

        $base = Str::slug($this->name) ?: 'penulis';
        $slug = $base;
        for ($n = 2; Author::where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $this->author()->create(['name' => $this->name, 'slug' => $slug, 'is_active' => true]);
    }

    /**
     * Ability API yang diizinkan oleh peran pengguna (PRD 8.12.3).
     *
     * @return array<int, string>
     */
    public function apiAbilities(): array
    {
        return config('api.role_abilities.'.$this->role, []);
    }

    /** Hak efektif = ability token ∩ peran; pengguna nonaktif tidak punya hak apa pun. */
    public function canUseApiAbility(string $ability): bool
    {
        return $this->is_active
            && in_array($ability, $this->apiAbilities(), true)
            && $this->tokenCan($ability);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'can_manage_custom_html' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }
}
