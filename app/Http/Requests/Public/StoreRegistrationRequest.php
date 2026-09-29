<?php

namespace App\Http\Requests\Public;

use App\Models\Event;
use App\Support\EmailCanonicalizer;
use App\Support\PhoneNormalizer;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class StoreRegistrationRequest extends FormRequest
{
    /**
     * Domain email yang tersedia di dropdown (docs/design/DESIGN.md).
     *
     * @var list<string>
     */
    public const DOMAINS = ['gmail.com', 'yahoo.com', 'yahoo.co.id', 'outlook.com', 'icloud.com'];

    /**
     * Pesan tunggal untuk Turnstile gagal (koneksi gagal maupun token ditolak).
     */
    private const TURNSTILE_FAILED = 'Verifikasi keamanan gagal, silakan coba lagi.';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Rakit email dari tiga field (docs/arsitektur.md Fase 1 langkah 3) dan
     * hitung bentuk kanoniknya. Nomor HP dinormalisasi terpisah supaya nilai
     * yang diketik peserta tetap kembali lewat old() saat validasi gagal.
     */
    protected function prepareForValidation(): void
    {
        $local = trim((string) $this->input('email_local'));
        $domainOther = mb_strtolower(trim((string) $this->input('email_domain_other')));
        $domainChoice = mb_strtolower(trim((string) $this->input('email_domain')));

        // "Lainnya…" yang ternyata berisi domain dari daftar diperlakukan sama
        // dengan memilih domain itu langsung di dropdown.
        if ($domainChoice === 'lainnya' && in_array($domainOther, self::DOMAINS, true)) {
            $domainChoice = $domainOther;
            $domainOther = '';
        }

        $domain = $domainChoice === 'lainnya' ? $domainOther : $domainChoice;

        $email = ($local !== '' && $domain !== '') ? mb_strtolower($local.'@'.$domain) : null;

        $qty = $this->input('ticket_qty');

        // Karakter kontrol/format/tak-terlihat (\p{C}: null byte, newline, RTL
        // override, zero-width, dst.) dibuang dari nama sebelum divalidasi.
        $name = $this->input('name');
        $name = is_string($name) ? trim((string) preg_replace('/\p{C}+/u', '', $name)) : $name;

        $this->merge([
            'name' => $name,
            'email_local' => $local,
            'email_domain' => $domainChoice,
            'email_domain_other' => $domainOther,
            'email' => $email,
            'email_canonical' => $email === null ? null : EmailCanonicalizer::canonicalize($email),
            // Hanya string bilangan bulat murni yang dijadikan int. "1.5" dibiarkan
            // apa adanya supaya ditolak rule integer, bukan dibulatkan diam-diam.
            'ticket_qty' => is_string($qty) && preg_match('/^\s*\d+\s*$/', $qty) ? (int) $qty : $qty,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $eventId = Event::query()->orderBy('id')->value('id');

        return [
            'name' => ['required', 'string', 'max:150'],
            'regency_id' => ['required', 'integer', Rule::exists('regencies', 'id')],
            'email_local' => ['required', 'string', 'max:100', 'regex:/^[^@\s]+$/u'],
            'email_domain' => ['required', 'string', Rule::in([...self::DOMAINS, 'lainnya'])],
            'email_domain_other' => [
                'nullable',
                'required_if:email_domain,lainnya',
                'string',
                'max:100',
                'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/',
            ],
            'email' => ['required', 'string', 'email:rfc', 'max:191'],
            'email_canonical' => [
                'required',
                'string',
                'max:191',
                Rule::unique('registrations', 'email_canonical')->where('event_id', $eventId),
            ],
            'phone' => ['required', 'string', 'max:20', function (string $attribute, mixed $value, callable $fail) {
                if (! preg_match('/^62\d{8,13}$/', PhoneNormalizer::normalize((string) $value))) {
                    $fail('Nomor WhatsApp tidak valid. Contoh: 081234567890.');
                }
            }],
            'ticket_qty' => ['required', 'integer', 'between:1,4'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama lengkap wajib diisi.',
            'regency_id.required' => 'Domisili wajib dipilih.',
            'regency_id.exists' => 'Domisili tidak dikenal.',
            'email_local.required' => 'Email wajib diisi.',
            'email_local.regex' => 'Nama email tidak boleh memuat spasi atau tanda @.',
            'email_domain.required' => 'Domain email wajib dipilih.',
            'email_domain.in' => 'Domain email tidak dikenal.',
            'email_domain_other.required_if' => 'Domain email wajib diisi.',
            'email_domain_other.regex' => 'Domain email tidak valid. Contoh: kantor.co.id.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Email tidak valid.',
            'email_canonical.unique' => 'Email ini sudah dipakai untuk mendaftar. Gunakan menu Cari tiket saya untuk mengirim ulang e-ticket.',
            'phone.required' => 'Nomor WhatsApp wajib diisi.',
            'ticket_qty.required' => 'Jumlah tiket wajib diisi.',
            'ticket_qty.integer' => 'Jumlah tiket tidak valid.',
            'ticket_qty.between' => 'Jumlah tiket minimal 1 dan maksimal 4.',
        ];
    }

    /**
     * Error email dari field rakitan ditampilkan di field email (email_local),
     * sesuai docs/arsitektur.md Fase 1 langkah 3.
     */
    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->verifyTurnstile($validator);

            foreach (['email', 'email_canonical'] as $key) {
                foreach ($validator->errors()->get($key) as $message) {
                    $validator->errors()->add('email_local', $message);
                }
            }
        });
    }

    /**
     * Validasi yang gagal selalu kembali ke form, bukan ke beranda, meski
     * browser tidak mengirim header Referer.
     */
    protected function getRedirectUrl(): string
    {
        return $this->redirector->getUrlGenerator()->previous(route('daftar'));
    }

    /**
     * Nomor HP yang sudah dinormalisasi ke 62xxx (aturan 5 CLAUDE.md).
     */
    public function normalizedPhone(): string
    {
        return PhoneNormalizer::normalize((string) $this->input('phone'));
    }

    /**
     * Verifikasi Cloudflare Turnstile. Di environment testing (dan saat
     * TURNSTILE_ENABLED=false) verifikasi dilewati lewat config.
     */
    private function verifyTurnstile(Validator $validator): void
    {
        if (! config('services.turnstile.enabled')) {
            return;
        }

        $token = $this->input('cf-turnstile-response');

        if (! is_string($token) || $token === '') {
            $validator->errors()->add('cf-turnstile-response', 'Verifikasi keamanan belum selesai. Coba lagi.');

            return;
        }

        // Cloudflare tidak terjangkau / timeout (ConnectionException, termasuk
        // cURL error 28) menjadi error validasi biasa, bukan 500. Timeout 5
        // detik supaya worker PHP shared hosting tidak tertahan lama.
        try {
            $response = Http::asForm()
                ->timeout(5)
                ->connectTimeout(5)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => config('services.turnstile.secret_key'),
                    'response' => $token,
                    'remoteip' => $this->ip(),
                ]);
        } catch (ConnectionException $e) {
            Log::warning('Turnstile tidak terjangkau', [
                'reason' => 'connection',
                'exception' => $e::class,
                'message' => mb_substr($e->getMessage(), 0, 300),
            ]);

            $validator->errors()->add('cf-turnstile-response', self::TURNSTILE_FAILED);

            return;
        }

        // Pesan ke pengguna sama untuk koneksi gagal dan token ditolak;
        // penyebabnya hanya dibedakan di log. Token dan secret tidak ditulis.
        if (! $response->successful() || $response->json('success') !== true) {
            Log::warning('Turnstile menolak token', [
                'reason' => 'rejected',
                'status' => $response->status(),
                'error_codes' => (array) $response->json('error-codes', []),
            ]);

            $validator->errors()->add('cf-turnstile-response', self::TURNSTILE_FAILED);
        }
    }
}
