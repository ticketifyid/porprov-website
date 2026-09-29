<?php

namespace Tests\Feature;

use App\Models\Regency;
use Database\Seeders\RegencySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegencySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_36_regencies_with_luar_jawa_tengah_last(): void
    {
        (new RegencySeeder)->run();

        $this->assertSame(36, Regency::count());

        $last = Regency::orderByDesc('sort_order')->first();

        $this->assertSame('Luar Jawa Tengah', $last->name);
    }

    public function test_seeding_twice_stays_idempotent(): void
    {
        (new RegencySeeder)->run();
        (new RegencySeeder)->run();

        $this->assertSame(36, Regency::count());
    }
}
