<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Dilempar dari App\Actions\RegisterAttendee saat kuota tidak mencukupi.
 * Pesannya sudah berupa kalimat siap tampil (aturan 3 CLAUDE.md) dan
 * dikembalikan ke form lewat back()->withInput()->withErrors(['ticket_qty' => ...]).
 */
class QuotaException extends RuntimeException
{
}
