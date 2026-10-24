<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken;

/**
 * Token API pribadi (Sanctum) yang terikat ke satu pengguna (PRD 8.12.3).
 * Disimpan dalam bentuk hash; teks token hanya ditampilkan sekali saat dibuat.
 */
class ApiToken extends PersonalAccessToken
{
    protected $table = 'personal_access_tokens';

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** @return array<int, string> */
    public function effectiveAbilities(): array
    {
        $user = $this->tokenable;
        if (! $user instanceof User || ! $user->is_active) {
            return [];
        }

        return array_values(array_filter($user->apiAbilities(), fn (string $ability) => $this->can($ability)));
    }
}
