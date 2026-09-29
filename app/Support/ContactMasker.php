<?php

namespace App\Support;

/**
 * Penyamaran email dan nomor HP untuk ditampilkan (halaman tiket) atau
 * ditulis ke log (LogTicketNotifier). Satu tempat supaya formatnya sama.
 */
class ContactMasker
{
    /**
     * Dua karakter pertama local-part + "***" + "@domain".
     *
     * Contoh artboard: bu***@email.com
     */
    public static function email(string $email): string
    {
        $atPos = strrpos($email, '@');

        if ($atPos === false) {
            return $email;
        }

        $local = substr($email, 0, $atPos);
        $domain = substr($email, $atPos + 1);
        $visible = mb_substr($local, 0, 2);

        return $visible.'***@'.$domain;
    }

    /**
     * 62xxxxxxxxxx -> 0xxxxxxxxxx -> {4 digit awal}-****-{4 digit akhir}
     * (contoh artboard: 0812-****-7890).
     */
    public static function phone(string $phone): string
    {
        $local = str_starts_with($phone, '62') ? '0'.substr($phone, 2) : $phone;

        if (mb_strlen($local) < 8) {
            return $local;
        }

        return mb_substr($local, 0, 4).'-****-'.mb_substr($local, -4);
    }
}
