<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['articles', 'products'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->longText('custom_html')->nullable();
                $table->longText('custom_css')->nullable();
                $table->longText('custom_js')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['articles', 'products'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['custom_html', 'custom_css', 'custom_js']);
            });
        }
    }
};
