<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Delegasi Custom HTML untuk Editor/Penulis tertentu; Admin selalu boleh
            $table->boolean('can_manage_custom_html')->default(false)->after('role');
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['can_manage_custom_html', 'last_login_at', 'last_login_ip']);
        });
    }
};
