<?php

namespace App\Actions;

use App\Exceptions\RegistrationCannotBeCancelledException;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya tempat registrations.cancelled_at diubah dan tickets_taken
 * dikembalikan (aturan 1 CLAUDE.md: kuota selalu berubah di dalam transaksi
 * dengan lock baris event).
 */
class CancelRegistration
{
    /**
     * @throws RegistrationCannotBeCancelledException jika registrasi sudah
     *         ditukar (redeemed_at terisi) atau sudah dibatalkan sebelumnya.
     */
    public function handle(Registration $registration, User $admin): Registration
    {
        return DB::transaction(function () use ($registration, $admin) {
            $event = Event::query()->whereKey($registration->event_id)->lockForUpdate()->firstOrFail();

            $affected = Registration::query()
                ->whereKey($registration->getKey())
                ->whereNull('redeemed_at')
                ->whereNull('cancelled_at')
                ->update([
                    'cancelled_at' => now(),
                    'cancelled_by' => $admin->getKey(),
                    // Dikosongkan supaya peserta bisa mendaftar ulang dengan
                    // email yang sama (unique index mengizinkan banyak NULL).
                    'email_canonical' => null,
                ]);

            if ($affected !== 1) {
                $fresh = $registration->fresh();

                throw new RegistrationCannotBeCancelledException(
                    $fresh->redeemed_at !== null
                        ? 'Registrasi ini sudah ditukar dan tidak bisa dibatalkan.'
                        : 'Registrasi ini sudah dibatalkan sebelumnya.'
                );
            }

            $event->decrement('tickets_taken', $registration->ticket_qty);

            return $registration->refresh();
        });
    }
}
