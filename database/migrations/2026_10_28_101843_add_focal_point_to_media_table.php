<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Titik fokus (persen dari kiri & atas) yang dijaga tetap terlihat saat gambar dipotong otomatis
     * ke rasio section. Default 50/50 = tengah, sama dengan perilaku sebelumnya.
     */
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->unsignedTinyInteger('focal_x')->default(50)->after('height');
            $table->unsignedTinyInteger('focal_y')->default(50)->after('focal_x');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn(['focal_x', 'focal_y']);
        });
    }
};
