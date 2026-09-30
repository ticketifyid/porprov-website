<?php

namespace App\Services;

use GdImage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Captcha gambar buatan server (ekstensi GD, tanpa layanan pihak ketiga):
 * jalur cadangan form pendaftaran saat Turnstile gagal di perangkat peserta.
 *
 * Jawaban disimpan di session (hash, bukan teks), sekali pakai, dan
 * kedaluwarsa 5 menit. Lihat docs/struktur.md "Perbaikan form pra-pembukaan".
 */
class ImageCaptcha
{
    /** Tanpa karakter yang mudah tertukar: 0/O, 1/I/L. */
    public const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public const LENGTH = 5;

    public const TTL_SECONDS = 300;

    public const WIDTH = 220;

    public const HEIGHT = 70;

    /** Percobaan jalur captcha: 5 per sesi dan 30 per IP (CGNAT) per 10 menit. */
    public const MAX_ATTEMPTS_PER_SESSION = 5;

    public const MAX_ATTEMPTS_PER_IP = 30;

    public const ATTEMPT_DECAY_SECONDS = 600;

    public const OK = 'ok';

    public const WRONG = 'wrong';

    public const EXPIRED = 'expired';

    private const SESSION_KEY = 'daftar_captcha';

    /**
     * Buat kode baru dan simpan jawabannya di session, menggantikan kode lama.
     */
    public function issue(): string
    {
        $code = '';
        $last = strlen(self::ALPHABET) - 1;

        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, $last)];
        }

        session()->put(self::SESSION_KEY, [
            'hash' => $this->hash($code),
            'expires_at' => now()->addSeconds(self::TTL_SECONDS)->getTimestamp(),
        ]);

        return $code;
    }

    /**
     * Cocokkan isian peserta. Jawaban di session selalu dihapus (sekali pakai),
     * apa pun hasilnya.
     *
     * @return self::OK|self::WRONG|self::EXPIRED
     */
    public function verify(string $input): string
    {
        $stored = session()->pull(self::SESSION_KEY);

        if (! is_array($stored) || now()->getTimestamp() > (int) ($stored['expires_at'] ?? 0)) {
            return self::EXPIRED;
        }

        $normalized = strtoupper((string) preg_replace('/\s+/', '', $input));

        return hash_equals((string) ($stored['hash'] ?? ''), $this->hash($normalized)) ? self::OK : self::WRONG;
    }

    /**
     * Hitung satu percobaan jalur captcha untuk IP dan sesi ini. true jika
     * salah satu batas sudah terlampaui (percobaan itu tidak dihitung lagi).
     */
    public function attemptsExceeded(string $ip, string $sessionId): bool
    {
        $limits = [
            'daftar-captcha:sesi:'.$sessionId => self::MAX_ATTEMPTS_PER_SESSION,
            'daftar-captcha:ip:'.$ip => self::MAX_ATTEMPTS_PER_IP,
        ];

        foreach ($limits as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                return true;
            }
        }

        foreach (array_keys($limits) as $key) {
            RateLimiter::hit($key, self::ATTEMPT_DECAY_SECONDS);
        }

        return false;
    }

    /**
     * Gambar PNG untuk kode. Pakai FreeType + DejaVu Sans Bold; jika tidak
     * tersedia, jatuh ke font bawaan GD (imagestring) dan catat warning.
     */
    public function renderPng(string $code): string
    {
        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagefilledrectangle($image, 0, 0, self::WIDTH - 1, self::HEIGHT - 1, imagecolorallocate($image, 255, 255, 255));

        // Bintik latar tipis.
        for ($i = 0; $i < 250; $i++) {
            $shade = random_int(150, 215);
            imagesetpixel($image, random_int(0, self::WIDTH - 1), random_int(0, self::HEIGHT - 1), imagecolorallocate($image, $shade, $shade, $shade + 20));
        }

        if ($this->freeTypeAvailable()) {
            $this->drawTrueType($image, $code);
        } else {
            Log::warning('Captcha: FreeType (imagettftext) atau font resources/fonts/DejaVuSans-Bold.ttf tidak tersedia; memakai imagestring bawaan GD yang lebih mudah dibaca bot.');
            $this->drawBuiltin($image, $code);
        }

        // Garis noise di atas teks.
        imagesetthickness($image, 2);

        for ($i = 0; $i < 5; $i++) {
            imageline(
                $image,
                random_int(0, 30), random_int(5, self::HEIGHT - 5),
                random_int(self::WIDTH - 30, self::WIDTH - 1), random_int(5, self::HEIGHT - 5),
                $this->inkColor($image),
            );
        }

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    public static function fontPath(): string
    {
        return resource_path('fonts/DejaVuSans-Bold.ttf');
    }

    protected function freeTypeAvailable(): bool
    {
        return function_exists('imagettftext') && is_file(self::fontPath());
    }

    private function drawTrueType(GdImage $image, string $code): void
    {
        $padding = 12;
        $slot = (int) floor((self::WIDTH - 2 * $padding) / self::LENGTH);

        foreach (str_split($code) as $i => $char) {
            $size = random_int(20, 25);

            imagettftext(
                $image,
                $size,
                random_int(-25, 25),
                $padding + $i * $slot + random_int(0, 6),
                (int) (self::HEIGHT / 2 + $size / 2) + 4 + random_int(-5, 5),
                $this->inkColor($image),
                self::fontPath(),
                $char,
            );
        }
    }

    /**
     * Font bawaan GD digambar di kanvas kecil lalu diperbesar, supaya tetap
     * terbaca di layar HP.
     */
    private function drawBuiltin(GdImage $image, string $code): void
    {
        $scale = 3;
        $small = imagecreatetruecolor((int) (self::WIDTH / $scale), (int) (self::HEIGHT / $scale));
        $white = imagecolorallocate($small, 255, 255, 255);
        imagefilledrectangle($small, 0, 0, imagesx($small) - 1, imagesy($small) - 1, $white);
        imagecolortransparent($small, $white);

        foreach (str_split($code) as $i => $char) {
            imagestring($small, 5, 4 + $i * 12 + random_int(0, 1), random_int(1, 6), $char, $this->inkColor($small));
        }

        imagecopyresampled($image, $small, 0, 0, 0, 0, self::WIDTH, self::HEIGHT, imagesx($small), imagesy($small));
    }

    private function inkColor(GdImage $image): int
    {
        return imagecolorallocate($image, random_int(10, 60), random_int(30, 80), random_int(90, 150));
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
