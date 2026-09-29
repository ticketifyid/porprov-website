<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Scanner = 'scanner';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Scanner => 'Petugas Scan',
        };
    }
}
