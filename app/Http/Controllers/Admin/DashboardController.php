<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Registration;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $event = Event::query()->first();

        $registrantCount = Registration::query()->whereNull('cancelled_at')->count();

        $checkedInTickets = (int) Registration::query()
            ->whereNull('cancelled_at')
            ->whereNotNull('redeemed_at')
            ->sum('ticket_qty');

        return view('admin.dashboard', [
            'ticketsTaken' => $event?->tickets_taken ?? 0,
            'ticketsRemaining' => $event ? max(0, $event->quota - $event->tickets_taken) : 0,
            'registrantCount' => $registrantCount,
            'checkedInTickets' => $checkedInTickets,
        ]);
    }
}
