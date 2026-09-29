<?php

namespace App\Http\Controllers\Scanner;

use App\Actions\RedeemRegistration;
use App\Enums\ScanResult;
use App\Http\Controllers\Controller;
use App\Models\Registration;
use App\Models\User;
use App\Support\PhoneNormalizer;
use App\Support\RegistrationCodeGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ScanController extends Controller
{
    /**
     * Sebuah tiket yang baru saja ditukar petugas ini sendiri bukan kesalahan
     * (alat scanner bisa mengirim dua kali, atau petugas ragu lalu memindai
     * ulang). Di bawah ambang ini hasilnya ditampilkan netral, bukan merah.
     */
    private const RECENT_SELF_MINUTES = 2;

    /**
     * Batas kandidat pencarian manual supaya layar HP tetap terbaca.
     */
    private const MAX_CANDIDATES = 10;

    /**
     * Pencarian nama terlalu pendek akan menarik ratusan baris tanpa guna.
     */
    private const MIN_NAME_LENGTH = 3;

    /**
     * Minimal digit sebelum sebuah input dianggap nomor HP.
     */
    private const MIN_PHONE_DIGITS = 7;

    public function index(): View
    {
        return view('scanner.index');
    }

    /**
     * POST /scan — jalur kamera (dua langkah) dan alat scanner (sekali scan),
     * docs/arsitektur.md Fase 4. Isi QR adalah token, bukan kode registrasi.
     */
    public function scan(Request $request, RedeemRegistration $action): JsonResponse
    {
        $data = $request->validate([
            'value' => ['required', 'string', 'max:255'],
            'method' => ['required', Rule::in(['camera', 'hardware'])],
        ]);

        $officer = $request->user();
        $registration = Registration::query()->where('token', $data['value'])->first();

        if ($registration === null) {
            $action->logMiss($officer, $data['method'], $data['value'], $request->ip());

            return response()->json(['result' => ScanResult::NotFound->value]);
        }

        if ($data['method'] === 'hardware') {
            $result = $action->handle($registration, $officer, 'hardware', $data['value'], $request->ip());

            return $this->resultResponse($registration, $result, $officer);
        }

        // Kamera, langkah pertama: tidak mengubah status apa pun.
        if ($registration->cancelled_at !== null) {
            $action->logCancelled($registration, $officer, 'camera', $data['value'], $request->ip());

            return $this->resultResponse($registration, ScanResult::Cancelled, $officer);
        }

        if ($registration->redeemed_at !== null) {
            $action->logAlreadyRedeemed($registration, $officer, 'camera', $data['value'], $request->ip());

            return $this->resultResponse($registration, ScanResult::AlreadyRedeemed, $officer);
        }

        // Belum ditukar: tidak ada baris scan_logs di langkah ini, karena enum
        // result hanya mengenal success/already_redeemed/not_found dan
        // 'success' harus berarti gelang benar-benar diserahkan.
        return response()->json([
            'result' => 'pending_confirm',
            'registration' => $this->registrationPayload($registration),
        ]);
    }

    /**
     * POST /scan/{registration}/redeem — konfirmasi kamera dan pencarian manual.
     */
    public function redeem(Request $request, Registration $registration, RedeemRegistration $action): JsonResponse
    {
        $data = $request->validate([
            'method' => ['required', Rule::in(['camera', 'manual'])],
            'value' => ['nullable', 'string', 'max:255'],
        ]);

        $officer = $request->user();

        $result = $action->handle(
            $registration,
            $officer,
            $data['method'],
            $data['value'] ?? $registration->code,
            $request->ip(),
        );

        return $this->resultResponse($registration, $result, $officer);
    }

    /**
     * POST /scan/cari — langkah pertama pencarian manual (kode/nama/HP).
     * Belum mengubah apa pun; petugas memilih kandidat lalu menekan konfirmasi.
     */
    public function search(Request $request, RedeemRegistration $action): JsonResponse
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'max:100'],
        ]);

        $keyword = trim($data['query']);
        $compactCode = RegistrationCodeGenerator::normalizeForSearch($keyword);
        $digits = preg_replace('/\D+/', '', $keyword) ?? '';

        $byPhone = strlen($digits) >= self::MIN_PHONE_DIGITS;
        $byName = mb_strlen($keyword) >= self::MIN_NAME_LENGTH;
        $byCode = strlen($compactCode) >= 6;

        if (! $byPhone && ! $byName) {
            return response()->json([
                'result' => 'too_short',
                'message' => 'Ketik minimal 3 karakter nama.',
            ]);
        }

        $candidates = Registration::query()
            ->with(['regency', 'redeemer'])
            ->where(function ($query) use ($byCode, $byPhone, $byName, $compactCode, $keyword) {
                if ($byCode) {
                    // Kode dibandingkan dalam bentuk padat supaya "pjt26 7ok3m9"
                    // dan "PJT26-70K3M9" dianggap sama.
                    $query->orWhereRaw("REPLACE(code, '-', '') = ?", [$compactCode]);
                }

                if ($byPhone) {
                    $query->orWhere('phone', PhoneNormalizer::normalize($keyword));
                }

                if ($byName) {
                    $query->orWhere('name', 'like', '%'.addcslashes($keyword, '%_\\').'%');
                }
            })
            ->orderBy('name')
            ->limit(self::MAX_CANDIDATES)
            ->get();

        if ($candidates->isEmpty()) {
            $action->logMiss($request->user(), 'manual', $keyword, $request->ip());

            return response()->json(['result' => ScanResult::NotFound->value]);
        }

        return response()->json([
            'result' => 'candidates',
            'candidates' => $candidates
                ->map(fn (Registration $registration) => $this->registrationPayload($registration))
                ->all(),
        ]);
    }

    private function resultResponse(Registration $registration, ScanResult $result, User $officer): JsonResponse
    {
        $registration->refresh()->loadMissing(['regency', 'redeemer']);

        $payload = [
            'result' => $result->value,
            'registration' => $this->registrationPayload($registration),
        ];

        if ($result === ScanResult::AlreadyRedeemed) {
            $payload['recent_self'] = $this->isRecentSelfRedeem($registration, $officer);
        }

        return response()->json($payload);
    }

    private function isRecentSelfRedeem(Registration $registration, User $officer): bool
    {
        return $registration->redeemed_by === $officer->getKey()
            && $registration->redeemed_at !== null
            && $registration->redeemed_at->greaterThan(now()->subMinutes(self::RECENT_SELF_MINUTES));
    }

    /**
     * Hanya data yang perlu tampil di layar petugas. Token, email, dan nomor
     * HP utuh sengaja tidak pernah dikirim ke klien.
     *
     * @return array<string, mixed>
     */
    private function registrationPayload(Registration $registration): array
    {
        $registration->loadMissing(['regency', 'redeemer']);

        return [
            'id' => $registration->getKey(),
            'code' => $registration->code,
            'name' => $registration->name,
            'regency' => $registration->regency?->name,
            'ticket_qty' => $registration->ticket_qty,
            'is_redeemed' => $registration->redeemed_at !== null,
            'is_cancelled' => $registration->cancelled_at !== null,
            'redeemed_at_label' => $registration->redeemed_at?->locale('id')->translatedFormat('H.i'),
            'redeemed_by_name' => $registration->redeemer?->name,
        ];
    }
}
