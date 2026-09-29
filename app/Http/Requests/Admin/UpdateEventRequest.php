<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Checkbox tidak mengirim apa pun kalau tidak dicentang.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['is_open' => $this->boolean('is_open')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'venue' => ['nullable', 'string', 'max:200'],
            'event_starts_at' => ['nullable', 'date'],
            'quota' => ['required', 'integer', 'min:0'],
            'is_open' => ['required', 'boolean'],
            'registration_open_at' => ['nullable', 'date'],
            'registration_close_at' => ['nullable', 'date', 'after:registration_open_at'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quota.required' => 'Kuota wajib diisi.',
            'quota.integer' => 'Kuota harus berupa angka.',
            'quota.min' => 'Kuota tidak boleh negatif.',
            'registration_close_at.after' => 'Waktu tutup pendaftaran harus setelah waktu buka.',
        ];
    }
}
