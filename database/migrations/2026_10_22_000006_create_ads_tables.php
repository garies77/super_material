<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ads', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slot', 32);
            $table->string('format', 16);
            $table->string('label', 16)->default('Iklan');
            $table->string('target_url', 2000)->nullable();
            $table->string('image')->nullable();
            $table->string('image_mobile')->nullable();
            $table->string('image_alt')->nullable();
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->string('cta_label', 60)->nullable();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->text('html')->nullable();
            $table->unsignedSmallInteger('paragraph_after')->nullable();
            $table->boolean('show_scroll_hint')->default(false);
            $table->string('targeting', 16)->default('all');
            $table->json('target_category_ids')->nullable();
            $table->json('target_tag_ids')->nullable();
            $table->json('target_article_ids')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0);
            $table->timestamps();

            $table->index(['slot', 'is_active']);
        });

        Schema::create('ad_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->string('slot', 32);
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();
            $table->string('page_url', 2000)->nullable();
            $table->string('user_agent', 120)->nullable();
            $table->boolean('is_bot')->default(false);
            $table->timestamp('created_at')->nullable();

            $table->index(['ad_id', 'type', 'created_at']);
            $table->index(['slot', 'type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_events');
        Schema::dropIfExists('ads');
    }
};
