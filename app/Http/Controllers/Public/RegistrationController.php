<?php

namespace App\Http\Controllers\Public;

use App\Actions\RegisterAttendee;
use App\Exceptions\QuotaException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreRegistrationRequest;
use App\Jobs\SendTicketNotification;
use App\Models\Event;
use App\Models\NotificationLog;
use App\Models\Registration;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;

class RegistrationController extends Controller
{
    /**
     * Kanal notifikasi yang dicatat dan diantre setelah pendaftaran berhasil.
     *
     * @var list<string>
     */
    private const CHANNELS = ['email', 'whatsapp'];

    /**
     * Kode error MySQL yang berarti server sedang sibuk, bukan data salah:
     * 1205 lock wait timeout, 1213 deadlock.
     *
     * @var list<int>
     */
    private const BUSY_ERROR_CODES = [1205, 1213];

    /**
     * POST /daftar — kuota dikunci dan dikurangi di RegisterAttendee.
     */
    public function store(StoreRegistrationRequest $request, RegisterAttendee $action): RedirectResponse
    {
        $event = Event::query()->orderBy('id')->firstOrFail();
        $data = $request->validated();

        try {
            $registration = $action->handle($event, [
                'name' => $data['name'],
                'regency_id' => (int) $data['regency_id'],
                'email' => $data['email'],
                'email_canonical' => $data['email_canonical'],
                'phone' => $request->normalizedPhone(),
                'ticket_qty' => (int) $data['ticket_qty'],
            ], $request->ip());
        } catch (QuotaException $e) {
            return back(fallback: route('daftar'))
                ->withInput()
                ->withErrors(['ticket_qty' => $e->getMessage()]);
        } catch (UniqueConstraintViolationException $e) {
            // Dua submit dengan email_canonical sama lolos validasi lalu
            // menabrak unique di DB (docs/arsitektur.md Fase 1 langkah 7):
            // balas ramah, bukan 500.
            return back(fallback: route('daftar'))
                ->withInput()
                ->withErrors(['email_local' => 'Email ini sudah terdaftar. Gunakan menu Cari tiket saya untuk menerima ulang e-ticket.']);
        } catch (QueryException $e) {
            // Saat war kuota, lockForUpdate bisa berakhir lock wait timeout
            // (1205) atau deadlock (1213). Itu bukan bug: minta peserta
            // menekan Daftar sekali lagi. QueryException lain sengaja tidak
            // ditangkap supaya muncul sebagai 500 dan terlihat di log.
            if (! in_array((int) ($e->errorInfo[1] ?? 0), self::BUSY_ERROR_CODES, true)) {
                throw $e;
            }

            report($e);

            return back(fallback: route('daftar'))
                ->withInput()
                ->withErrors(['form' => 'Server sedang sibuk karena banyak pendaftar. Silakan tekan Daftar sekali lagi.']);
        }

        $this->queueNotifications($registration);

        return redirect()->route('daftar.sukses', $registration->token);
    }

    /**
     * GET /daftar/sukses/{token} — artboard Sukses / DesktopSukses.
     */
    public function success(string $token): View
    {
        $registration = Registration::query()->where('token', $token)->firstOrFail();

        return view('public.sukses', ['registration' => $registration]);
    }

    /**
     * Dijalankan SETELAH transaksi commit (aturan 8 CLAUDE.md): catat dua baris
     * notification_logs berstatus pending, lalu antre job-nya.
     */
    private function queueNotifications(Registration $registration): void
    {
        foreach (self::CHANNELS as $channel) {
            NotificationLog::create([
                'registration_id' => $registration->getKey(),
                'channel' => $channel,
                'status' => 'pending',
                'attempts' => 0,
            ]);

            SendTicketNotification::dispatch($registration->getKey(), $channel);
        }
    }
}
