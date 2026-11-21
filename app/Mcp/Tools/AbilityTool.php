<?php

namespace App\Mcp\Tools;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
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

    /**
     * Validasi argumen tool. Argumen kosong ("" atau null) dianggap tidak dikirim: aturan validasi Laravel
     * melewati string kosong, sehingga tanpa ini filter seperti status="" diam-diam ikut terpakai.
     *
     * @param  array<string, array<int, mixed>>  $rules
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    protected function validated(Request $request, array $rules): array
    {
        $arguments = array_filter($request->all(), fn (mixed $value) => $value !== null && $value !== '');

        return Validator::validate($arguments, $rules);
    }
}
