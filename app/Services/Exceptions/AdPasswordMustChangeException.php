<?php

declare(strict_types=1);

namespace App\Services\Exceptions;

use RuntimeException;

/**
 * AD foydalanuvchi paroli o'zgartirilishi shart (pwdLastSet=0, "must change").
 *
 * Bunday akkaunt bilan LDAP bind "data 773" xatosi bilan rad etiladi —
 * parolning o'zi to'g'ri bo'lsa ham. OWA/Windows'da parolni o'zgartirgach
 * kirish mumkin bo'ladi.
 */
class AdPasswordMustChangeException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Foydalanuvchi parolini o\'zgartirishi shart (pwdLastSet=0).');
    }
}