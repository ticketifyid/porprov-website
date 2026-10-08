<?php

namespace App\Support;

use Throwable;

/**
 * Menyaring pesan exception sebelum disimpan ke notification_logs.last_error
 * (dibaca admin di halaman detail peserta). Exception SMTP/HTTP bisa memuat
 * kredensial, alamat email, nomor HP, atau token tiket.
 *
 * Diekstrak dari App\Jobs\SendTicketNotification supaya bisa dipakai ulang
 * oleh command WhatsApp (app/Console/Commands/WaSendNext.php) tanpa duplikasi
 * (docs/notifikasi.md "Penyaringan last_error").
 */
class NotificationErrorSanitizer
{
    public static function sanitize(Throwable $e): string
    {
        $message = preg_replace(
            [
                // smtp://user:pass@host → smtp://[disaring]@host
                '~(\b[a-z][a-z0-9+.\-]*://)[^\s/@]+@~i',
                '~\bAuthorization\s*[:=]\s*(?:(?:Bearer|Basic)\s+)?[^\s"\',;]+~i',
                '~\b(Bearer|Basic)\s+[^\s"\',;]+~i',
                // password=..., token: ...
                '~\b(password|passwd|pwd|pass|secret|token|api[_-]?key|apikey|access[_-]?key|key|username|user)\s*[=:]\s*("[^"]*"|\'[^\']*\'|[^\s&"\',;]+)~i',
                // with username "..." (pesan autentikasi Symfony Mailer)
                '~\b(password|username|user)\s+("[^"]*"|\'[^\']*\')~i',
                '~[a-z0-9._%+\-]+@[a-z0-9\-]+(?:\.[a-z0-9\-]+)+~i',
                // token tiket (48 karakter), API key, dsb.
                '~\b[A-Za-z0-9_\-]{32,}\b~',
                '~(?<![\w\[])\+?\d(?:[\s\-.()]*\d){8,}~',
            ],
            [
                '$1[disaring]@',
                'Authorization: [disaring]',
                '$1 [disaring]',
                '$1=[disaring]',
                '$1 "[disaring]"',
                '[email]',
                '[token]',
                '[nomor]',
            ],
            $e->getMessage(),
        );

        return mb_substr($e::class.': '.($message ?? '[pesan tidak bisa dibaca]'), 0, 500);
    }
}
