<?php

namespace App\Support;

class EmailCanonicalizer
{
    /**
     * Hitung bentuk kanonik email untuk cek duplikat (aturan 13 CLAUDE.md).
     * Hanya dipakai untuk cek unique (event_id, email_canonical), tidak pernah
     * untuk mengirim notifikasi.
     */
    public static function canonicalize(string $email): string
    {
        $email = mb_strtolower(trim($email));

        $atPos = strrpos($email, '@');

        if ($atPos === false) {
            return $email;
        }

        $local = substr($email, 0, $atPos);
        $domain = substr($email, $atPos + 1);

        if ($domain === 'googlemail.com') {
            $domain = 'gmail.com';
        }

        if ($domain === 'gmail.com') {
            $plusPos = strpos($local, '+');
            if ($plusPos !== false) {
                $local = substr($local, 0, $plusPos);
            }

            $local = str_replace('.', '', $local);
        }

        return $local.'@'.$domain;
    }
}
