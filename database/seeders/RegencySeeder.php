<?php

namespace Database\Seeders;

use App\Models\Regency;
use Illuminate\Database\Seeder;

class RegencySeeder extends Seeder
{
    /**
     * 35 kabupaten/kota Jawa Tengah, diurutkan alfabetis, ditutup dengan
     * "Luar Jawa Tengah" pada sort_order terbesar (docs/erd.md).
     */
    public function run(): void
    {
        $names = [
            'Kab. Banjarnegara',
            'Kab. Banyumas',
            'Kab. Batang',
            'Kab. Blora',
            'Kab. Boyolali',
            'Kab. Brebes',
            'Kab. Cilacap',
            'Kab. Demak',
            'Kab. Grobogan',
            'Kab. Jepara',
            'Kab. Karanganyar',
            'Kab. Kebumen',
            'Kab. Kendal',
            'Kab. Klaten',
            'Kab. Kudus',
            'Kab. Magelang',
            'Kota Magelang',
            'Kab. Pati',
            'Kab. Pekalongan',
            'Kota Pekalongan',
            'Kab. Pemalang',
            'Kab. Purbalingga',
            'Kab. Purworejo',
            'Kab. Rembang',
            'Kota Salatiga',
            'Kab. Semarang',
            'Kota Semarang',
            'Kab. Sragen',
            'Kab. Sukoharjo',
            'Kota Surakarta',
            'Kab. Tegal',
            'Kota Tegal',
            'Kab. Temanggung',
            'Kab. Wonogiri',
            'Kab. Wonosobo',
        ];

        foreach ($names as $index => $name) {
            Regency::updateOrCreate(
                ['name' => $name],
                ['sort_order' => $index + 1],
            );
        }

        Regency::updateOrCreate(
            ['name' => 'Luar Jawa Tengah'],
            ['sort_order' => 99],
        );
    }
}
