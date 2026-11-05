<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('videos', function (Blueprint $table) {
            $table->id();
            $table->morphs('videoable');
            $table->string('provider', 20)->default('youtube'); // youtube, upload
            $table->string('url', 500);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('thumbnail_path', 500)->nullable();
            $table->timestamp('upload_date')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->json('clips')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
