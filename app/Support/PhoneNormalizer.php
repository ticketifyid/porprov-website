<?php

namespace App\Support;

class PhoneNormalizer
{
    /**
     * Normalisasi nomor HP ke bentuk 62xxx (aturan 5 CLAUDE.md):
     * buang semua karakter non-digit, lalu awalan 08 -> 628, +62/62 dibiarkan.
     */
    public static function normalize(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }

        if (str_starts_with($digits, '62')) {
            return $digits;
        }

        return $digits;
    }
}
