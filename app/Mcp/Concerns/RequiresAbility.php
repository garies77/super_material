<?php

namespace App\Mcp\Concerns;

use App\Models\User;
use Laravel\Mcp\Request;

/**
 * Untuk resource dan prompt MCP: hanya terdaftar bila token boleh memakai salah satu ability di $abilities
 * (ability token ∩ peran pengguna), sama dengan AbilityTool untuk tool.
 */
trait RequiresAbility
{
    public function shouldRegister(Request $request): bool
    {
        $user = $request->user();

        return $user instanceof User
            && collect($this->abilities)->contains(fn (string $ability) => $user->canUseApiAbility($ability));
    }
}
