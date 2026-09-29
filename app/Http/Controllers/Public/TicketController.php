<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\SearchTicketRequest;
use App\Jobs\SendTicketNotification;
use App\Models\NotificationLog;
use App\Models\Registration;
use Carbon\CarbonInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;

class TicketController extends Controller
{
    /**
     * Kanal notifikasi yang di-dispatch ulang lewat /cari-tiket.
     *
     * @var list<string>
     */
    private const CHANNELS = ['email', 'whatsapp'];

    /**
     * Maksimal resend per kontak (bukan per IP): 3 kali per jam. Peserta di
     * balik CGNAT berbagi IP, jadi throttle utama HARUS per-kontak, bukan
     * per-IP (aturan dari pemilik proyek, lihat docs/struktur.md Fase 6).
     */
    private const MAX_ATTEMPTS_PER_CONTACT = 3;

    private const DECAY_SECONDS_PER_CONTACT = 3600;

    /**
     * GET /tiket/{registration:token} — artboard Tiket / DesktopTiket.
     * Halaman ini tidak boleh diindeks (token adalah rahasia bertaut, bukan
     * kredensial, tapi tetap tidak untuk konsumsi publik/mesin pencari).
     */
    public function show(Registration $registration): Response
    {
        $registration->loadMissing(['event', 'regency']);

        $event = $registration->event;

        return response()->view('public.tiket', [
            'name' => $registration->name,
            'regencyName' => $registration->regency->name,
            'code' => $registration->code,
            'ticketQty' => $registration->ticket_qty,
            'maskedEmail' => $this->maskEmail($registration->email),
            'maskedPhone' => $this->maskPhone($registration->phone),
            'isRedeemed' => $registration->redeemed_at !== null,
            'redeemedAtLabel' => $registration->redeemed_at?->locale('id')->translatedFormat('H.i'),
            'eventStartsAt' => $this->formatDateTime($event->event_starts_at),
            'venue' => $event->venue,
            'qrSvg' => $this->qrSvg($registration->token),
        ], 200, ['X-Robots-Tag' => 'noindex']);
    }

    /**
     * GET /cari-tiket — artboard LupaTiket / DesktopLupaTiket.
     */
    public function searchForm(): View
    {
        return view('public.cari-tiket', ['sent' => false]);
    }

    /**
     * POST /cari-tiket — respons SELALU pesan netral yang sama, terdaftar
     * atau tidak (docs/arsitektur.md Fase 3). Throttle IP (route, 20/menit)
     * menjaga dari banjir permintaan; throttle per-kontak di bawah ini
     * mencegah spam resend ke satu peserta tanpa membocorkan lewat respons
     * yang berbeda kalau limitnya kena.
     */
    public function search(SearchTicketRequest $request): View
    {
        $throttleKey = 'cari-tiket:'.$request->canonicalContact();

        if (! RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS_PER_CONTACT)) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS_PER_CONTACT);

            foreach ($this->matchingRegistrations($request) as $registration) {
                $this->resendNotifications($registration);
            }
        }

        return view('public.cari-tiket', ['sent' => true]);
    }

    /**
     * @return Collection<int, Registration>
     */
    private function matchingRegistrations(SearchTicketRequest $request): Collection
    {
        return Registration::query()
            ->where($request->isEmailContact() ? 'email_canonical' : 'phone', $request->canonicalContact())
            ->get();
    }

    /**
     * Reset notification_logs registrasi ini ke pending lalu dispatch ulang.
     * Job Fase 5 sengaja idempoten (skip kalau log sudah 'sent'), jadi tanpa
     * reset ini dispatch ulang tidak akan mengirim apa pun.
     */
    private function resendNotifications(Registration $registration): void
    {
        foreach (self::CHANNELS as $channel) {
            NotificationLog::query()->updateOrCreate(
                ['registration_id' => $registration->getKey(), 'channel' => $channel],
                ['status' => 'pending', 'attempts' => 0, 'last_error' => null],
            );

            SendTicketNotification::dispatch($registration->getKey(), $channel);
        }
    }

    private function qrSvg(string $token): string
    {
        // Output default v6 sudah SVG (QRMarkupSVG). Warna modul diatur lewat
        // CSS (.qr-frame .qr-svg .dark) supaya sama dengan token --ink; modul
        // terang tidak digambar (latar putih datang dari .qr-frame).
        $options = new QROptions([
            'eccLevel' => 'M',
            'addQuietzone' => false,
            'quietzoneSize' => 0,
            'outputBase64' => false,
            'svgAddXmlHeader' => false,
            'svgUseFillAttributes' => false,
            'drawLightModules' => false,
        ]);

        return (new QRCode($options))->render($token);
    }

    /**
     * Dua karakter pertama local-part + "***" + "@domain".
     *
     * Contoh artboard: bu***@email.com
     */
    private function maskEmail(string $email): string
    {
        $atPos = strrpos($email, '@');

        if ($atPos === false) {
            return $email;
        }

        $local = substr($email, 0, $atPos);
        $domain = substr($email, $atPos + 1);
        $visible = mb_substr($local, 0, 2);

        return $visible.'***@'.$domain;
    }

    /**
     * 62xxxxxxxxxx -> 0xxxxxxxxxx -> {4 digit awal}-****-{4 digit akhir}
     * (contoh artboard: 0812-****-7890).
     */
    private function maskPhone(string $phone): string
    {
        $local = str_starts_with($phone, '62') ? '0'.substr($phone, 2) : $phone;

        if (mb_strlen($local) < 8) {
            return $local;
        }

        return mb_substr($local, 0, 4).'-****-'.mb_substr($local, -4);
    }

    private function formatDateTime(?CarbonInterface $dateTime): ?string
    {
        return $dateTime?->locale('id')->translatedFormat('j F Y, H.i');
    }
}
