<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menambah dua nilai enum notification_logs.status untuk outbox WhatsApp
 * (docs/notifikasi.md): 'sending' (klaim atomik oleh wa:send-next, dikembalikan
 * ke 'pending' kalau menggantung >10 menit) dan 'skipped' (wa_consent = false,
 * tidak pernah dikirim). Laravel 13 bisa mengubah kolom enum lewat ->change()
 * tanpa doctrine/dbal (dependency itu sudah dihapus sejak Laravel 11).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->enum('status', ['pending', 'sending', 'sent', 'failed', 'skipped'])
                ->default('pending')
                ->change();
        });
    }

    /**
     * Reverse the migrations. Baris 'sending'/'skipped' dipetakan dulu ke
     * 'pending' SEBELUM enum dipersempit — tanpa ini, MySQL/MariaDB dalam mode
     * non-strict diam-diam mengganti nilai yang tidak ada di enum baru menjadi
     * string kosong, merusak kolom status baris itu.
     */
    public function down(): void
    {
        DB::table('notification_logs')
            ->whereIn('status', ['sending', 'skipped'])
            ->update(['status' => 'pending']);

        Schema::table('notification_logs', function (Blueprint $table) {
            $table->enum('status', ['pending', 'sent', 'failed'])
                ->default('pending')
                ->change();
        });
    }
};
