<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code_prefix', 'quota', 'tickets_taken', 'event_starts_at', 'venue', 'registration_open_at', 'registration_close_at', 'is_open'])]
class Event extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quota' => 'integer',
            'tickets_taken' => 'integer',
            'event_starts_at' => 'datetime',
            'registration_open_at' => 'datetime',
            'registration_close_at' => 'datetime',
            'is_open' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Registration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    /**
     * Apakah pendaftaran sedang dibuka: saklar manual aktif dan berada
     * di dalam jendela waktu registrasi (jika diatur).
     */
    public function isOpen(): bool
    {
        if (! $this->is_open) {
            return false;
        }

        $now = now();

        if ($this->registration_open_at !== null && $now->lt($this->registration_open_at)) {
            return false;
        }

        if ($this->registration_close_at !== null && $now->gte($this->registration_close_at)) {
            return false;
        }

        return true;
    }
}
