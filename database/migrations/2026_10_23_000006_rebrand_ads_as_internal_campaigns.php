<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Kampanye internal artikel: label netral dan gambar dipindah dari storage/promo ke storage/kampanye,
 * agar URL dan teks materi tidak cocok dengan pola daftar filter ad blocker.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ads', fn (Blueprint $table) => $table->string('label', 16)->default('Sorotan')->change());
        DB::table('ads')->where('label', 'Iklan')->update(['label' => 'Sorotan']);

        $disk = Storage::disk('public');
        foreach (DB::table('ads')->get(['id', 'image', 'image_mobile']) as $ad) {
            $changes = [];
            foreach (['image', 'image_mobile'] as $column) {
                $path = $ad->{$column};
                if ($path && str_starts_with($path, 'promo/')) {
                    $new = 'kampanye/'.substr($path, strlen('promo/'));
                    if ($disk->exists($path) && ! $disk->exists($new)) {
                        $disk->move($path, $new);
                    }
                    if ($disk->exists($new)) {
                        $changes[$column] = $new;
                    }
                }
            }
            if ($changes) {
                DB::table('ads')->where('id', $ad->id)->update($changes);
            }
        }
    }

    public function down(): void
    {
        Schema::table('ads', fn (Blueprint $table) => $table->string('label', 16)->default('Iklan')->change());
    }
};
