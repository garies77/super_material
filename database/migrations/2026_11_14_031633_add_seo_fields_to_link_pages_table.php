<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Gambar Open Graph khusus (1200×630) dan pilihan noindex untuk halaman Link Bio. */
    public function up(): void
    {
        Schema::table('link_pages', function (Blueprint $table) {
            $table->string('og_image_path')->nullable()->after('meta_description');
            $table->boolean('noindex')->default(false)->after('og_image_path');
        });
    }

    public function down(): void
    {
        Schema::table('link_pages', function (Blueprint $table) {
            $table->dropColumn(['og_image_path', 'noindex']);
        });
    }
};
