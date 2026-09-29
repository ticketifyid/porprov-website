<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Dilempar dari App\Actions\CancelRegistration saat registrasi sudah ditukar
 * (redeemed_at terisi) atau sudah dibatalkan sebelumnya. Pesannya sudah
 * berupa kalimat siap tampil (aturan 3 CLAUDE.md).
 */
class RegistrationCannotBeCancelledException extends RuntimeException {}
