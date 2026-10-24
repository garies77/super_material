<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('authors', fn (Blueprint $table) => $table->string('motto', 160)->nullable()->after('job_title'));
    }

    public function down(): void
    {
        Schema::table('authors', fn (Blueprint $table) => $table->dropColumn('motto'));
    }
};
