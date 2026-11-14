<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agregat harian (WIB) tayangan & klik halaman link-in-bio; tanpa IP atau data pribadi.
     * link_item_id = 0 berarti baris tayangan halaman (bukan NULL, agar indeks unik berlaku di semua driver).
     */
    public function up(): void
    {
        Schema::create('link_stats', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('link_page_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('link_item_id')->default(0);
            $table->string('source', 60);
            $table->string('device', 10);
            $table->unsignedInteger('count')->default(0);

            $table->unique(['date', 'link_page_id', 'link_item_id', 'source', 'device'], 'link_stats_unique');
            $table->index(['link_page_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('link_stats');
    }
};
