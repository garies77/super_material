<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_forms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            // full = halaman /form/{slug}, embed = disematkan di halaman lain, both = keduanya
            $table->string('display_mode', 10)->default('both');
            $table->json('fields');
            $table->string('submit_label', 60)->nullable();
            $table->text('success_message')->nullable();
            $table->string('redirect_url', 2000)->nullable();
            $table->string('notify_email')->nullable();
            $table->boolean('send_confirmation')->default(false);
            $table->text('confirmation_message')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_forms');
    }
};
