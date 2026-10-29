<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Agregat pencarian pengunjung per hari (WIB) per kata kunci; tanpa IP atau data pribadi. */
    public function up(): void
    {
        Schema::create('search_stats', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('term', 100);
            $table->unsignedInteger('searches')->default(0);
            $table->unsignedInteger('results')->default(0);
            $table->unique(['date', 'term']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_stats');
    }
};
