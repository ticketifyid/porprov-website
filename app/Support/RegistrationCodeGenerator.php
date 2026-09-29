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
}
