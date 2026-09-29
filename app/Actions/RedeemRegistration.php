<?php

namespace App\Actions;

use App\Enums\ScanResult;
use App\Models\Registration;
use App\Models\ScanLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya tempat registrations.redeemed_at diubah dan scan_logs ditulis
 * (aturan 9 CLAUDE.md, docs/struktur.md). Dipakai ketiga jalur scan: hardware
 * (langsung redeem), konfirmasi kamera, dan pencarian manual.
 */
class RedeemRegistration
{
    /**
     * Panjang kolom scan_logs.scanned_value (docs/erd.md).
     */
    private const MAX_SCANNED_VALUE = 255;

    /**
     * Tukarkan gelang lewat update bersyarat. Affected rows 0 berarti baris
     * ini sudah ditukar lebih dulu (oleh petugas lain, atau oleh kiriman ganda
     * dari alat scanner yang sama) — diperlakukan already_redeemed, tidak
     * pernah menimpa redeemed_at yang sudah ada.
     */
    public function handle(
        Registration $registration,
        User $officer,
        string $method,
        string $scannedValue,
        ?string $ip = null,
    ): ScanResult {
        return DB::transaction(function () use ($registration, $officer, $method, $scannedValue, $ip) {
            $affected = Registration::query()
                ->whereKey($registration->getKey())
                ->whereNull('redeemed_at')
                ->update([
                    'redeemed_at' => now(),
                    'redeemed_by' => $officer->getKey(),
                ]);

            $result = $affected === 1 ? ScanResult::Success : ScanResult::AlreadyRedeemed;

            $this->log($registration, $officer, $method, $result, $scannedValue, $ip);

            return $result;
        });
    }

    /**
     * QR/input tidak cocok dengan registrasi mana pun.
     */
    public function logMiss(User $officer, string $method, string $scannedValue, ?string $ip = null): ScanLog
    {
        return $this->log(null, $officer, $method, ScanResult::NotFound, $scannedValue, $ip);
    }

    /**
     * Langkah pertama kamera/manual atas tiket yang sudah tertukar: tidak ada
     * yang perlu diupdate, tapi percobaannya tetap tercatat.
     */
    public function logAlreadyRedeemed(
        Registration $registration,
        User $officer,
        string $method,
        string $scannedValue,
        ?string $ip = null,
    ): ScanLog {
        return $this->log($registration, $officer, $method, ScanResult::AlreadyRedeemed, $scannedValue, $ip);
    }

    private function log(
        ?Registration $registration,
        User $officer,
        string $method,
        ScanResult $result,
        string $scannedValue,
        ?string $ip,
    ): ScanLog {
        return ScanLog::create([
            'registration_id' => $registration?->getKey(),
            'user_id' => $officer->getKey(),
            'scanned_value' => mb_substr($scannedValue, 0, self::MAX_SCANNED_VALUE),
            'method' => $method,
            'result' => $result->value,
            'ip_address' => $ip,
            'scanned_at' => now(),
        ]);
    }
}
