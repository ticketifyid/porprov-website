<?php

namespace App\Http\Requests\Public;

use App\Support\EmailCanonicalizer;
use App\Support\PhoneNormalizer;
use Illuminate\Foundation\Http\FormRequest;

class SearchTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'contact' => ['required', 'string', 'max:191'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contact.required' => 'Nomor WhatsApp atau email wajib diisi.',
        ];
    }

    /**
     * Apakah kontak yang diketik berbentuk email (mengandung "@") atau nomor HP.
     */
    public function isEmailContact(): bool
    {
        return str_contains((string) $this->input('contact'), '@');
    }

    /**
     * Bentuk kanonik kontak, dipakai untuk pencarian DAN sebagai kunci
     * throttle per-kontak: email_canonical (aturan 13 CLAUDE.md) atau
     * nomor HP dinormalisasi 62xxx (aturan 5 CLAUDE.md).
     */
    public function canonicalContact(): string
    {
        $contact = (string) $this->input('contact');

        return $this->isEmailContact()
            ? EmailCanonicalizer::canonicalize($contact)
            : PhoneNormalizer::normalize($contact);
    }
}
