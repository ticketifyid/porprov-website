<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreRegistrationRequest;
use App\Models\Event;
use App\Models\Regency;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class HomeController extends Controller
{
    /**
     * GET / — beranda (artboard Main / DesktopMain). Jika pendaftaran belum
     * dibuka, sudah ditutup, atau kuota penuh, halaman Status yang tampil.
     */
    public function index(): View
    {
        $event = $this->event();

        if ($condition = $this->statusCondition($event)) {
            return $this->statusView($event, $condition);
        }

        // Hanya nilai yang memang tampil yang dikirim ke view — angka kuota
        // tidak pernah ikut (aturan 2 CLAUDE.md).
        return view('public.home', [
            'eventName' => $event->name,
            'venue' => $event->venue,
            'eventStartsAt' => $this->formatDateTime($event->event_starts_at),
        ]);
    }

    /**
     * GET /daftar — form pendaftaran (artboard Form / DesktopForm).
     * Frontend hanya menerima maxQty, tidak pernah angka sisa kuota (aturan 2).
     */
    public function form(): View|Response
    {
        $event = $this->event();

        if ($condition = $this->statusCondition($event)) {
            return $this->statusView($event, $condition);
        }

        if ($this->turnstileMisconfigured()) {
            return response($this->statusView($event, 'perbaikan'), 503);
        }

        // Waktu form pertama kali dirender, untuk waktu isi minimum di
        // StoreRegistrationRequest. Tidak diperbarui saat form dibuka ulang
        // (mis. kembali karena error), supaya peserta tidak harus menunggu lagi.
        if (! session()->has(StoreRegistrationRequest::RENDERED_AT_KEY)) {
            session()->put(StoreRegistrationRequest::RENDERED_AT_KEY, now()->getTimestamp());
        }

        $maxQty = max(0, min(4, $event->quota - $event->tickets_taken));

        return view('public.form', [
            'eventName' => $event->name,
            'venue' => $event->venue,
            'eventStartsAt' => $this->formatDateTime($event->event_starts_at),
            'maxQty' => $maxQty,
            'regencies' => Regency::query()->orderBy('sort_order')->get(),
        ]);
    }

    /**
     * Di produksi, Turnstile aktif tanpa site key atau secret key membuat form
     * tidak bisa dipakai (tombol Daftar tidak pernah aktif / token selalu
     * ditolak). Tampilkan halaman perbaikan dan catat error, maksimal sekali
     * per 10 menit supaya log tidak banjir.
     */
    private function turnstileMisconfigured(): bool
    {
        if (! app()->isProduction() || ! config('services.turnstile.enabled')) {
            return false;
        }

        if (filled(config('services.turnstile.site_key')) && filled(config('services.turnstile.secret_key'))) {
            return false;
        }

        if (Cache::add('turnstile-misconfigured-logged', true, 600)) {
            Log::error('Pendaftaran ditutup sementara: TURNSTILE_ENABLED=true tetapi TURNSTILE_SITE_KEY atau TURNSTILE_SECRET_KEY kosong. Isi kedua kunci di .env lalu jalankan `php artisan config:clear` (lihat docs/deploy.md).');
        }

        return true;
    }

    /**
     * Event tunggal proyek ini (satu baris di tabel events).
     */
    private function event(): Event
    {
        return Event::query()->orderBy('id')->firstOrFail();
    }

    /**
     * Kondisi halaman Status, atau null jika pendaftaran bisa dilanjutkan.
     * "Ditutup" jika registration_close_at sudah lewat, ATAU jika saklar manual
     * is_open dimatikan setelah pendaftaran sempat dibuka (registration_open_at
     * sudah lewat). Selain itu "belum dibuka".
     */
    private function statusCondition(Event $event): ?string
    {
        if (! $event->isOpen()) {
            $closedAt = $event->registration_close_at;
            $openedAt = $event->registration_open_at;

            $closed = ($closedAt !== null && now()->gte($closedAt))
                || (! $event->is_open && $openedAt !== null && now()->gte($openedAt));

            return $closed ? 'ditutup' : 'belum dibuka';
        }

        return ($event->quota - $event->tickets_taken) <= 0 ? 'kuota penuh' : null;
    }

    private function statusView(Event $event, string $condition): View
    {
        $opensAt = $this->formatDateTime($event->registration_open_at);

        $copy = [
            'belum dibuka' => [
                'title' => 'Pendaftaran belum dibuka',
                'body' => $opensAt === null
                    ? 'Simpan halaman ini dan kembali lagi saat pendaftaran dibuka.'
                    : "Pendaftaran dibuka pada {$opensAt}. Simpan halaman ini dan kembali lagi saat pendaftaran dibuka.",
                'icon' => 'clock',
            ],
            'ditutup' => [
                'title' => 'Pendaftaran sudah ditutup',
                'body' => 'Terima kasih atas antusiasme Anda. Jika sudah mendaftar, e-ticket Anda tetap berlaku untuk registrasi ulang.',
                'icon' => 'lock',
            ],
            'kuota penuh' => [
                'title' => 'Kuota pendaftaran sudah penuh',
                'body' => 'Seluruh tiket Opening Ceremony sudah terdaftar. Jika sudah mendaftar, e-ticket Anda tetap berlaku untuk registrasi ulang.',
                'icon' => 'crowd',
            ],
            'perbaikan' => [
                'title' => 'Pendaftaran sedang dalam perbaikan',
                'body' => 'Mohon maaf, form pendaftaran sedang kami perbaiki. Silakan coba lagi beberapa saat lagi. Jika sudah mendaftar, e-ticket Anda tetap berlaku.',
                'icon' => 'warning',
            ],
        ][$condition];

        return view('public.status', [
            'title' => $copy['title'],
            'body' => $copy['body'],
            'icon' => $copy['icon'],
            // Tombol "Cari tiket saya" tidak tampil saat pendaftaran belum dibuka.
            'showFind' => $condition !== 'belum dibuka',
        ]);
    }

    private function formatDateTime(?CarbonInterface $dateTime): ?string
    {
        return $dateTime?->locale('id')->translatedFormat('j F Y, H.i');
    }
}
