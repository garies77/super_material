<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Warna sendiri per tombol Link Bio (menimpa warna tema); NULL = ikut tema. */
    public function up(): void
    {
        Schema::table('link_items', function (Blueprint $table) {
            $table->string('button_color', 7)->nullable()->after('is_highlighted');
            $table->string('text_color', 7)->nullable()->after('button_color');
        });
    }

    public function down(): void
    {
        Schema::table('link_items', function (Blueprint $table) {
            $table->dropColumn(['button_color', 'text_color']);
        });
    }
};
