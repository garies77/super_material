<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Path thumbnail (WebP 320 px) dibuat sekali saat berkas didaftarkan, agar grid pustaka
     * tidak membaca berkas asli dari disk pada setiap render.
     */
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->string('thumbnail_path')->nullable()->after('path');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn('thumbnail_path');
        });
    }
};
