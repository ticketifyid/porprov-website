<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ResendTicketNotifications;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\NotificationLog;
use App\Models\Registration;
use App\Models\ScanLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', $this->metrics());
    }

    /**
     * JSON yang sama dipakai untuk render awal (metrics()) dan untuk
     * penyegaran otomatis tiap 60 detik (public/js/admin-dashboard.js).
     */
    public function data(): JsonResponse
    {
        return response()->json($this->metrics());
    }

    /**
     * "Kirim ulang semua yang gagal": ResendTicketNotifications untuk setiap
     * registrasi yang notifikasi email-nya failed dan belum dibatalkan.
     */
    public function resendFailed(ResendTicketNotifications $action): RedirectResponse
    {
        $registrations = $this->emailFailedRegistrationsQuery()->get();

        foreach ($registrations as $registration) {
            $action->handle($registration);
        }

        $count = $registrations->count();

        return back()->with('status', $count > 0
            ? "Notifikasi sedang dikirim ulang untuk {$count} peserta."
            : 'Tidak ada notifikasi gagal yang perlu dikirim ulang.');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Registration>
     */
    private function emailFailedRegistrationsQuery()
    {
        return Registration::query()
            ->whereNull('cancelled_at')
            ->whereHas('notificationLogs', function ($query) {
                $query->where('channel', 'email')->where('status', 'failed');
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function metrics(): array
    {
        $event = Event::query()->first();
        $quota = $event?->quota ?? 0;
        $ticketsTaken = $event?->tickets_taken ?? 0;
        $ticketsRemaining = $event ? max(0, $quota - $ticketsTaken) : 0;
        $quotaPercent = $quota > 0 ? round(min(100, ($ticketsTaken / $quota) * 100), 1) : 0.0;

        $registrantCount = Registration::query()->whereNull('cancelled_at')->count();

        $totalTickets = (int) Registration::query()->whereNull('cancelled_at')->sum('ticket_qty');
        $checkedInTickets = (int) Registration::query()
            ->whereNull('cancelled_at')
            ->whereNotNull('redeemed_at')
            ->sum('ticket_qty');
        $checkinPercent = $totalTickets > 0 ? round(min(100, ($checkedInTickets / $totalTickets) * 100), 1) : 0.0;

        $recentScans = ScanLog::query()
            ->where('result', 'success')
            ->where('scanned_at', '>=', now()->subMinutes(10))
            ->count();

        $notifCounts = NotificationLog::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        // Dipakai untuk tautan "Gagal" ke daftar peserta (?notif=failed):
        // registrasi berbeda yang punya minimal satu log gagal (kanal apa pun).
        $notifFailedRegistrations = Registration::query()
            ->whereNull('cancelled_at')
            ->whereHas('notificationLogs', fn ($query) => $query->where('status', 'failed'))
            ->count();

        // Dipakai untuk tombol "Kirim ulang semua yang gagal": hanya kanal
        // email, sama persis dengan kriteria yang dipakai resendFailed().
        $emailFailedCount = $this->emailFailedRegistrationsQuery()->count();

        $verifiedTurnstile = Registration::query()->whereNull('cancelled_at')->where('verified_via', 'turnstile')->count();
        $verifiedCaptcha = Registration::query()->whereNull('cancelled_at')->where('verified_via', 'captcha')->count();
        $verifiedNone = max(0, $registrantCount - $verifiedTurnstile - $verifiedCaptcha);

        $recent = Registration::query()
            ->with('regency')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn (Registration $registration) => [
                'id' => $registration->id,
                'code' => $registration->code,
                'name' => $registration->name,
                'regency' => $registration->regency?->name,
                'ticket_qty' => $registration->ticket_qty,
                'url' => route('admin.registrations.show', $registration),
            ])
            ->values()
            ->all();

        return [
            'ticketsTaken' => $ticketsTaken,
            'ticketsRemaining' => $ticketsRemaining,
            'quota' => $quota,
            'quotaPercent' => $quotaPercent,
            'registrantCount' => $registrantCount,
            'checkedInTickets' => $checkedInTickets,
            'totalTickets' => $totalTickets,
            'checkinPercent' => $checkinPercent,
            'recentScans' => $recentScans,
            'notifSent' => (int) ($notifCounts['sent'] ?? 0),
            'notifPending' => (int) ($notifCounts['pending'] ?? 0),
            'notifFailed' => $notifFailedRegistrations,
            'emailFailedCount' => $emailFailedCount,
            'verifiedTurnstile' => $verifiedTurnstile,
            'verifiedCaptcha' => $verifiedCaptcha,
            'verifiedNone' => $verifiedNone,
            'recent' => $recent,
            'updatedAt' => now()->locale('id')->translatedFormat('H.i'),
        ];
    }
}
