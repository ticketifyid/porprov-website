<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CancelRegistration;
use App\Actions\ResendTicketNotifications;
use App\Exceptions\RegistrationCannotBeCancelledException;
use App\Http\Controllers\Controller;
use App\Models\Registration;
use App\Support\PhoneNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegistrationAdminController extends Controller
{
    private const PER_PAGE = 25;

    public function index(Request $request): View
    {
        $keyword = trim((string) $request->query('q', ''));

        $registrations = Registration::query()
            ->with('regency')
            ->when($keyword !== '', function ($query) use ($keyword) {
                $escaped = addcslashes($keyword, '%_\\');

                $query->where(function ($query) use ($keyword, $escaped) {
                    $query->where('name', 'like', '%'.$escaped.'%')
                        ->orWhere('code', 'like', '%'.$escaped.'%')
                        ->orWhere('phone', PhoneNormalizer::normalize($keyword));
                });
            })
            ->orderByDesc('created_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.registrations.index', ['registrations' => $registrations, 'keyword' => $keyword]);
    }

    public function show(Registration $registration): View
    {
        $registration->loadMissing(['regency', 'redeemer', 'canceller', 'notificationLogs']);

        return view('admin.registrations.show', ['registration' => $registration]);
    }

    public function cancel(Request $request, Registration $registration, CancelRegistration $action): RedirectResponse
    {
        try {
            $action->handle($registration, $request->user());
        } catch (RegistrationCannotBeCancelledException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Registrasi berhasil dibatalkan. Kuota sudah dikembalikan.');
    }

    public function resend(Registration $registration, ResendTicketNotifications $action): RedirectResponse
    {
        $action->handle($registration);

        return back()->with('status', 'Notifikasi sedang dikirim ulang.');
    }
}
