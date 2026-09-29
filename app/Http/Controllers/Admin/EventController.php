<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateEventRequest;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EventController extends Controller
{
    public function edit(): View
    {
        return view('admin.events.edit', ['event' => Event::query()->firstOrFail()]);
    }

    /**
     * Kuota tidak boleh diturunkan sampai di bawah tickets_taken (aturan
     * tambahan pemilik proyek untuk Fase 8). Dicek di dalam transaksi dengan
     * lock baris event supaya tidak balapan dengan pendaftaran/pembatalan
     * yang sedang berjalan (aturan 1 CLAUDE.md).
     */
    public function update(UpdateEventRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $ok = DB::transaction(function () use ($data) {
            $event = Event::query()->lockForUpdate()->firstOrFail();

            if ((int) $data['quota'] < $event->tickets_taken) {
                return false;
            }

            $event->update($data);

            return true;
        });

        if (! $ok) {
            $ticketsTaken = Event::query()->value('tickets_taken');

            return back()->withInput()->withErrors([
                'quota' => "Kuota tidak boleh diturunkan sampai di bawah {$ticketsTaken} (jumlah tiket yang sudah terpakai).",
            ]);
        }

        return back()->with('status', 'Pengaturan event berhasil disimpan.');
    }
}
