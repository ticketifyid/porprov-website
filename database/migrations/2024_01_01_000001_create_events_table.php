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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('code_prefix', 10)->unique();
            $table->unsignedInteger('quota')->default(3600);
            $table->unsignedInteger('tickets_taken')->default(0);
            $table->dateTime('event_starts_at')->nullable();
            $table->string('venue', 200)->nullable();
            $table->dateTime('registration_open_at')->nullable();
            $table->dateTime('registration_close_at')->nullable();
            $table->boolean('is_open')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
