<?php

namespace App\Support;

class RegistrationCodeGenerator
{
    /**
     * Alfabet Crockford Base32 (aturan 6 CLAUDE.md) — tanpa I, L, O, U
     * supaya tidak rancu saat dibaca manusia.
     */
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    /**
     * Buat kode registrasi "{prefix}-XXXXXX". Fungsi ini murni: pemanggil
     * (App\Actions\RegisterAttendee) bertanggung jawab retry saat bentrok
     * unique di database.
     */
    public static function make(string $prefix): string
    {
        $suffix = '';

        for ($i = 0; $i < 6; $i++) {
            $suffix .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $prefix.'-'.$suffix;
    }

    /**
     * Bentuk padat sebuah kode untuk dibandingkan saat pencarian manual
     * (docs/arsitektur.md Fase 4): uppercase, buang semua karakter selain
     * 0-9/A-Z (termasuk spasi dan tanda hubung), lalu O -> 0 dan I/L -> 1
     * karena huruf-huruf itu memang tidak ada di alfabet Crockford Base32 —
     * petugas yang mengetik "O" pasti memaksudkan angka nol.
     *
     * Hasilnya BUKAN kode untuk ditampilkan (tanda hubungnya hilang); hanya
     * untuk dicocokkan dengan REPLACE(code, '-', '') di sisi database.
     */
    public static function normalizeForSearch(string $input): string
    {
        $compact = preg_replace('/[^0-9A-Z]/', '', strtoupper($input)) ?? '';

        return strtr($compact, ['O' => '0', 'I' => '1', 'L' => '1']);
    }
}
