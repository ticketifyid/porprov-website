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
            // Dikosongkan saat registrasi dibatalkan (App\Actions\CancelRegistration)
            // supaya email yang sama bisa dipakai mendaftar ulang — unique index
            // (event_id, email_canonical) mengizinkan banyak NULL.
            $table->string('email_canonical', 191)->nullable()->change();

            $table->dateTime('cancelled_at')->nullable()->index()->after('redeemed_by');
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete()->after('cancelled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn('cancelled_at');
            $table->string('email_canonical', 191)->nullable(false)->change();
        });
    }
};
