<?php

namespace App\Mcp\Tools;

use App\Models\User;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Tool;

/**
 * Tool MCP yang hanya terdaftar bila token boleh memakai ability tertentu: ability token DAN peran pengguna
 * harus sama-sama mengizinkan, sama dengan REST API admin (PRD 8.12.3). Tool yang tidak terdaftar
 * tidak muncul di daftar tool dan tidak dapat dipanggil.
 */
abstract class AbilityTool extends Tool
{
    /** Ability dari config('api.abilities') yang dibutuhkan tool ini. */
    protected string $ability;

    public function shouldRegister(Request $request): bool
    {
        $user = $request->user();

        return $user instanceof User && $user->canUseApiAbility($this->ability);
    }
}
