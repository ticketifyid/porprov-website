<?php

namespace App\Actions;

use App\Exceptions\QuotaException;
use App\Models\Event;
use App\Models\Registration;
use App\Support\RegistrationCodeGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Satu-satunya tempat kuota dikurangi (aturan 1 CLAUDE.md, docs/struktur.md).
 * Controller tidak boleh menyentuh tickets_taken sendiri.
 */
class RegisterAttendee
{
    /**
     * Jumlah percobaan ulang saat kode/token yang diacak bentrok (aturan 6 & 7).
     */
    private const MAX_ATTEMPTS = 10;

    /**
     * @param  array{name: string, regency_id: int, email: string, email_canonical: string, phone: string, ticket_qty: int, verified_via?: string|null}  $data
     *
     * @throws QuotaException
     */
    public function handle(Event $event, array $data, ?string $ip = null): Registration
    {
        $qty = (int) $data['ticket_qty'];

        return DB::transaction(function () use ($event, $data, $qty, $ip) {
            $locked = Event::query()->whereKey($event->getKey())->lockForUpdate()->firstOrFail();

            abort_unless($locked->isOpen(), 403);

            $sisa = $locked->quota - $locked->tickets_taken;

            if ($sisa <= 0) {
                throw new QuotaException('Mohon maaf, kuota pendaftaran sudah penuh.');
            }

            if ($qty > $sisa) {
                throw new QuotaException("Sisa kuota tinggal {$sisa} tiket. Silakan kurangi jumlah tiket.");
            }

            $locked->increment('tickets_taken', $qty);

            $registration = new Registration([
                'event_id' => $locked->getKey(),
                'name' => $data['name'],
                'regency_id' => $data['regency_id'],
                'email' => $data['email'],
                'email_canonical' => $data['email_canonical'],
                'phone' => $data['phone'],
                'ticket_qty' => $qty,
                'ip_address' => $ip,
            ]);

            $registration->forceFill([
                'code' => $this->uniqueCode($locked->code_prefix),
                'token' => $this->uniqueToken(),
                'verified_via' => $data['verified_via'] ?? null,
            ])->save();

            return $registration;
        });
    }

    private function uniqueCode(string $prefix): string
    {
        for ($i = 0; $i < self::MAX_ATTEMPTS; $i++) {
            $code = RegistrationCodeGenerator::make($prefix);

            if (! Registration::query()->where('code', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Gagal membuat kode registrasi yang unik.');
    }

    private function uniqueToken(): string
    {
        for ($i = 0; $i < self::MAX_ATTEMPTS; $i++) {
            $token = Str::random(48);

            if (! Registration::query()->where('token', $token)->exists()) {
                return $token;
            }
        }

        throw new RuntimeException('Gagal membuat token tiket yang unik.');
    }
}
