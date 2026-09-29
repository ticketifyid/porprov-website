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
     *
     * firstOrCreate, bukan updateOrCreate: seeder yang dijalankan ulang di
     * produksi tidak boleh menimpa kuota, is_open, atau jadwal yang sudah
     * diubah admin.
     */
    public function run(): void
    {
        Event::firstOrCreate(
            ['code_prefix' => 'PJT26'],
            [
                'name' => 'Opening Ceremony Porprov Jateng XVII 2026',
                'quota' => 3600,
                'is_open' => false,
            ],
        );
    }
}
