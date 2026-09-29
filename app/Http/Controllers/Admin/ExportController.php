<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Registration;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /**
     * Awalan sel yang bisa dibaca sebagai formula oleh Excel/Sheets (CSV
     * injection) dan wajib diberi prefix tanda kutip tunggal.
     *
     * @var list<string>
     */
    private const DANGEROUS_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Export CSV cadangan H-1 (docs/arsitektur.md): kode, nama, HP, jumlah
     * tiket. Registrasi yang sudah dibatalkan dikecualikan. Streaming lewat
     * cursor() supaya tidak memuat seluruh baris ke memori.
     */
    public function __invoke(): StreamedResponse
    {
        $callback = function (): void {
            $handle = fopen('php://output', 'w');

            // BOM UTF-8 supaya terbaca benar di Excel.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['Kode', 'Nama', 'No. HP', 'Jumlah Tiket']);

            Registration::query()
                ->whereNull('cancelled_at')
                ->orderBy('name')
                ->select(['code', 'name', 'phone', 'ticket_qty'])
                ->cursor()
                ->each(function (Registration $registration) use ($handle): void {
                    fputcsv($handle, [
                        $this->escapeCell($registration->code),
                        $this->escapeCell($registration->name),
                        $this->escapeCell($registration->phone),
                        $registration->ticket_qty,
                    ]);
                });

            fclose($handle);
        };

        return response()->streamDownload($callback, 'peserta-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function escapeCell(string $value): string
    {
        foreach (self::DANGEROUS_PREFIXES as $prefix) {
            if (str_starts_with($value, $prefix)) {
                return "'".$value;
            }
        }

        return $value;
    }
}
