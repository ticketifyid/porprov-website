<?php

namespace Database\Seeders;

use App\Models\Event;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    /**
     * Satu event: Opening Ceremony Porprov Jateng XVII 2026.
     * Jadwal (event_starts_at, venue, jendela registrasi) belum ditentukan;
     * diisi admin lewat halaman pengaturan event (Fase 8).
     */
    public function run(): void
    {
        Event::updateOrCreate(
            ['code_prefix' => 'PJT26'],
            [
                'name' => 'Opening Ceremony Porprov Jateng XVII 2026',
                'quota' => 3600,
                'is_open' => false,
            ],
        );
    }
}
