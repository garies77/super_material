<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Sinonim pencarian situs: kata yang dicari pengunjung (term) ikut dicari sebagai kata lain (synonyms). */
    public function up(): void
    {
        Schema::create('search_synonyms', function (Blueprint $table) {
            $table->id();
            $table->string('term', 100)->unique();
            $table->json('synonyms');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_synonyms');
    }
};
