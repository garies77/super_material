<?php

use App\Models\HomeSection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_sections', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('type', 30);
            $table->string('title')->nullable();
            $table->integer('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        // Beranda langsung tampil seperti sebelumnya tanpa perlu menjalankan seeder
        HomeSection::seedDefaults();
    }

    public function down(): void
    {
        Schema::dropIfExists('home_sections');
    }
};
