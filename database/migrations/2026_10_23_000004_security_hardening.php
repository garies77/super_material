<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PRD Keamanan: status pada audit log, penghapusan token terbaca (hanya hash SHA-256 yang disimpan),
 * dan kolom 2FA aplikasi authenticator (TOTP) untuk Filament.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', fn (Blueprint $table) => $table->string('status', 10)->default('success')->after('action'));

        Schema::table('personal_access_tokens', fn (Blueprint $table) => $table->dropColumn('plain_token'));

        Schema::table('users', function (Blueprint $table) {
            $table->text('app_authentication_secret')->nullable();
            $table->text('app_authentication_recovery_codes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['app_authentication_secret', 'app_authentication_recovery_codes']));
        Schema::table('personal_access_tokens', fn (Blueprint $table) => $table->text('plain_token')->nullable()->after('token'));
        Schema::table('audit_logs', fn (Blueprint $table) => $table->dropColumn('status'));
    }
};
