<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Agregat tayangan halaman produk/artikel per hari (WIB); tanpa IP atau data pribadi. */
    public function up(): void
    {
        Schema::create('page_view_stats', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('viewable_type', 20);
            $table->unsignedBigInteger('viewable_id');
            $table->unsignedInteger('views')->default(0);
            $table->unique(['date', 'viewable_type', 'viewable_id']);
            $table->index(['viewable_type', 'viewable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_view_stats');
    }
};
