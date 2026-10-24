<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * articles.author_id berisi id tabel authors (relasi Article::author), tetapi FK lamanya ke users.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('articles')->whereNotNull('author_id')
            ->whereNotIn('author_id', DB::table('authors')->select('id'))
            ->update(['author_id' => null]);

        Schema::table('articles', function (Blueprint $table) {
            $table->dropForeign(['author_id']);
            $table->foreign('author_id')->references('id')->on('authors')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropForeign(['author_id']);
            $table->foreign('author_id')->references('id')->on('users')->nullOnDelete();
        });
    }
};
