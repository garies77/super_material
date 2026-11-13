<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Halaman link-in-bio (seperti Linktree) di /l/{slug}. */
    public function up(): void
    {
        Schema::create('link_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('bio')->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('theme', 20)->default('light');
            // [{platform, url}]
            $table->json('socials')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('link_pages');
    }
};
