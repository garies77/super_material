<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_media', function (Blueprint $table) {
            $table->text('description')->nullable()->after('alt_text');
            $table->timestamp('upload_date')->nullable()->after('description');
            $table->unsignedInteger('duration_seconds')->nullable()->after('upload_date');
            $table->json('clips')->nullable()->after('duration_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('product_media', function (Blueprint $table) {
            $table->dropColumn(['description', 'upload_date', 'duration_seconds', 'clips']);
        });
    }
};
