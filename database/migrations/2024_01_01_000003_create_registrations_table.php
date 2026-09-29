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
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->string('code', 20)->unique();
            $table->string('token', 64)->unique();
            $table->string('name', 150);
            $table->unsignedSmallInteger('regency_id');
            $table->string('email', 191);
            $table->string('email_canonical', 191);
            $table->string('phone', 20);
            $table->unsignedTinyInteger('ticket_qty');
            $table->dateTime('redeemed_at')->nullable()->index();
            $table->foreignId('redeemed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->foreign('regency_id')->references('id')->on('regencies');
            $table->unique(['event_id', 'email_canonical']);
            $table->index('phone');
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};
