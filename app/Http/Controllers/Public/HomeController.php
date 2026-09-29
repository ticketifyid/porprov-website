<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Regency;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;

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
    public function form(): View
    {
        $event = $this->event();

        if ($condition = $this->statusCondition($event)) {
            return $this->statusView($event, $condition);
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
