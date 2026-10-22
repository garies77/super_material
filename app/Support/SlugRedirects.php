<?php

namespace App\Support;

use App\Models\Redirect;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Pencatatan redirect 301 saat slug berubah (PRD 9.1 & 9.16, SEO-07).
 * Rantai diringkas (A→B lalu B→C menjadi A→C) dan loop dihapus (kembali ke slug lama).
 */
class SlugRedirects
{
    public static function record(string $from, string $to): void
    {
        if ($from === $to) {
            return;
        }

        DB::transaction(function () use ($from, $to) {
            // URL baru kini hidup lagi: redirect lama yang berasal darinya harus dibuang agar tidak loop
            Redirect::where('from_path', $to)->delete();

            // Ringkas rantai: semua yang sebelumnya menuju URL lama langsung ke URL baru
            Redirect::where('to_path', $from)->update(['to_path' => $to, 'code' => 301]);

            Redirect::updateOrCreate(['from_path' => $from], ['to_path' => $to, 'code' => 301]);

            Redirect::whereColumn('from_path', 'to_path')->delete();
        });

        Cache::forget('redirects.map');
    }
}
