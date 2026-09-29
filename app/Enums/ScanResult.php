<?php

namespace App\Enums;

/**
 * Hasil satu percobaan scan, sama persis dengan enum kolom
 * scan_logs.result di docs/erd.md.
 */
enum ScanResult: string
{
    case Success = 'success';
    case AlreadyRedeemed = 'already_redeemed';
    case NotFound = 'not_found';
    case Cancelled = 'cancelled';
}
