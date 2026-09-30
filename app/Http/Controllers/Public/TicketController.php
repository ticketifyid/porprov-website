<?php

namespace App\Http\Controllers\Public;

use App\Actions\ResendTicketNotifications;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\SearchTicketRequest;
use App\Models\Registration;
use App\Support\ContactMasker;
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
        abort_if($registration->cancelled_at !== null, 404);

        $registration->loadMissing(['event', 'regency']);

        $event = $registration->event;

        return response()->view('public.tiket', [
            'name' => $registration->name,
            'regencyName' => $registration->regency->name,
            'code' => $registration->code,
            'ticketQty' => $registration->ticket_qty,
            'maskedEmail' => ContactMasker::email($registration->email),
            'maskedPhone' => ContactMasker::phone($registration->phone),
            'isRedeemed' => $registration->redeemed_at !== null,
            'redeemedAtLabel' => $registration->redeemed_at?->locale('id')->translatedFormat('H.i'),
            'eventStartsAt' => $this->formatDateTime($event->event_starts_at),
            'venue' => $event->venue,
            'qrSvg' => $this->qrSvg($registration->token),
        ], 200, [
            'X-Robots-Tag' => 'noindex',
            'Referrer-Policy' => 'no-referrer',
        ]);
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
     * atau tidak (docs/arsitektur.md Fase 3). Throttle IP (route, 120/menit)
     * menjaga dari banjir permintaan; throttle per-kontak di bawah ini
     * mencegah spam resend ke satu peserta tanpa membocorkan lewat respons
     * yang berbeda kalau limitnya kena.
     */
    public function search(SearchTicketRequest $request, ResendTicketNotifications $resend): View
    {
        $throttleKey = 'cari-tiket:'.$request->canonicalContact();

        if (! RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS_PER_CONTACT)) {
            RateLimiter::hit($throttleKey, self::DECAY_SECONDS_PER_CONTACT);

            foreach ($this->matchingRegistrations($request) as $registration) {
                $resend->handle($registration);
            }
        }

        return view('public.cari-tiket', ['sent' => true]);
    }

    /**
     * Registrasi yang dibatalkan diperlakukan seolah tidak terdaftar
     * (cancelled_at mengosongkan email_canonical, dan HP tetap difilter di
     * sini), supaya pesan tetap netral (aturan 11 CLAUDE.md).
     *
     * @return Collection<int, Registration>
     */
    private function matchingRegistrations(SearchTicketRequest $request): Collection
    {
        return Registration::query()
            ->whereNull('cancelled_at')
            ->where($request->isEmailContact() ? 'email_canonical' : 'phone', $request->canonicalContact())
            ->get();
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

    private function formatDateTime(?CarbonInterface $dateTime): ?string
    {
        return $dateTime?->locale('id')->translatedFormat('j F Y, H.i');
    }
}
