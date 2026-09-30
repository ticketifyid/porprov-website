<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            // Jalur verifikasi anti-bot yang diloloskan saat mendaftar. NULL
            // untuk data lama dan saat TURNSTILE_ENABLED=false.
            $table->enum('verified_via', ['turnstile', 'captcha'])->nullable()->index()->after('ip_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropIndex(['verified_via']);
            $table->dropColumn('verified_via');
        });
    }
};
