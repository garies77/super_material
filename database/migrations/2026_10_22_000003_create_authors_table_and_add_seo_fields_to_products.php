<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('authors')) {
            Schema::create('authors', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('name', 150);
                $table->string('slug', 150)->unique();
                $table->string('honorific_prefix', 50)->nullable();
                $table->string('honorific_suffix', 50)->nullable();
                $table->string('job_title', 100)->nullable();
                $table->json('same_as')->nullable();
                $table->text('bio')->nullable();
                $table->string('avatar')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            // Seed initial author from existing user(s) to preserve author relations
            $firstUser = DB::table('users')->first();
            if ($firstUser) {
                DB::table('authors')->insert([
                    'id' => $firstUser->id,
                    'user_id' => $firstUser->id,
                    'name' => $firstUser->name,
                    'slug' => \Illuminate\Support\Str::slug($firstUser->name) ?: 'admin',
                    'job_title' => 'Technical Specialist & Editor',
                    'bio' => 'Spesialis teknis bahan bangunan dan editor ulasan material konstruksi Supermaterial.',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'sku')) {
                $table->string('sku')->nullable()->after('slug');
            }
            if (! Schema::hasColumn('products', 'mpn')) {
                $table->string('mpn')->nullable()->after('sku');
            }
            if (! Schema::hasColumn('products', 'availability')) {
                $table->string('availability')->nullable()->after('price_display');
            }
            if (! Schema::hasColumn('products', 'price_valid_until')) {
                $table->dateTime('price_valid_until')->nullable()->after('availability');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['sku', 'mpn', 'availability', 'price_valid_until']);
        });

        Schema::dropIfExists('authors');
    }
};
