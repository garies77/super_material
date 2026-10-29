<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Potret harian kesehatan katalog (jumlah kekurangan data & sebaran skor SEO) untuk tren mingguan. */
    public function up(): void
    {
        Schema::create('catalog_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->json('metrics');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_snapshots');
    }
};
