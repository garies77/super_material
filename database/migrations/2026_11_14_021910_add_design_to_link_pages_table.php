<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Kustomisasi tampilan Link Bio (tata letak header, latar, tombol, warna, font) dan gambar banner. */
    public function up(): void
    {
        Schema::table('link_pages', function (Blueprint $table) {
            $table->string('banner_path')->nullable()->after('avatar_path');
            // Lihat App\Support\LinkBioDesign; NULL = pakai preset dari kolom theme
            $table->json('design')->nullable()->after('theme');
        });
    }

    public function down(): void
    {
        Schema::table('link_pages', function (Blueprint $table) {
            $table->dropColumn(['banner_path', 'design']);
        });
    }
};
