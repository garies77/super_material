<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status', 16)->default('draft');
            $table->string('layout', 16)->default('image_text');
            $table->string('image')->nullable();
            $table->string('image_mobile')->nullable();
            $table->string('image_alt')->nullable();
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->string('cta_label', 60)->nullable();
            $table->string('target_url', 2000)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->integer('priority')->default(0);
            $table->string('targeting', 16)->default('all');
            $table->json('target_category_ids')->nullable();
            $table->string('device', 16)->default('all');
            $table->string('trigger', 16)->default('delay');
            $table->unsignedSmallInteger('delay_seconds')->default(5);
            $table->unsignedTinyInteger('scroll_percent')->default(30);
            $table->string('frequency', 16)->default('days');
            $table->unsignedSmallInteger('frequency_days')->default(7);
            $table->boolean('allow_never_again')->default(false);
            $table->timestamps();

            $table->index(['status', 'priority']);
        });

        Schema::create('campaign_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->string('device', 16)->nullable();
            $table->string('page_path', 500)->nullable();
            $table->string('user_agent', 120)->nullable();
            $table->boolean('is_bot')->default(false);
            $table->timestamp('created_at')->nullable();

            $table->index(['campaign_id', 'type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_events');
        Schema::dropIfExists('campaigns');
    }
};
