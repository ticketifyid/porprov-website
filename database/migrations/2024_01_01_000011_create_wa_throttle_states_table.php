<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baris tunggal (id = 1) penyimpan state pacing/circuit-breaker wa:send-next
 * (docs/notifikasi.md). Disimpan di DB, bukan cache, karena cache bisa
 * terhapus kapan saja oleh cache:clear.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wa_throttle_states', function (Blueprint $table) {
            $table->id();
            $table->dateTime('next_allowed_at')->nullable();
            $table->dateTime('paused_until')->nullable();
            $table->string('pause_reason')->nullable();
            $table->unsignedTinyInteger('consecutive_failures')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wa_throttle_states');
    }
};
