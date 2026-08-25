<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Joriy organizatsiya ID'sini xavfsiz aniqlaydi.
 *
 * XAVFSIZLIK: organization_id faqat AUTENTIFIKATSIYA QILINGAN foydalanuvchidan
 * olinadi — client tomonidan yuboriladigan X-Organization-Id headeriga
 * ishonilmaydi (spoofing vektorining oldini oladi).
 */
final class CurrentOrg
{
    public static function id(?Request $request = null): int
    {
        $user = $request?->user() ?? auth()->user();

        $orgId = $user->organization_id ?? null;

        return ($orgId !== null && (int) $orgId > 0) ? (int) $orgId : 1;
    }
}
