<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Support;

/**
 * Botdagi zayavka holatining belgisi.
 *
 * Har bir belgi IKKI narsani aytadi — nima bo'lganini va qanday rangda:
 *
 *   ochiq      ⚪  kulrang   (hali hech kim ishlamagan)
 *   jarayonda  ⏳  sariq     (ish ketyapti / kutilmoqda)
 *   bajarildi  ✅  yashil    (ptichka — ish yopilgani ko'rinib turadi)
 *   baholandi  🔷  ko'k      (so'rovchi yakunini tasdiqlagan)
 *   rad etildi ❌  qizil
 *
 * Ilgari bu yerda rangli kvadratlar turardi: rang to'g'ri bo'lsa ham, 🟩 va 🟥
 * o'zi hech narsa demasdi — ro'yxatda faqat rang bo'yicha farqlash kerak edi.
 *
 * "Baholandi" — alohida `status_id` EMAS: u bajarilgan zayavkaga so'rovchi
 * baho qo'ygan holat. Shuning uchun bu oddiy massiv emas, funksiya.
 */
final class TicketStatusEmoji
{
    /** ticket_statuses id -> belgi. */
    private const BY_STATUS = [
        1 => '⚪', 2 => '⚪', 3 => '⚪',      // Yangi / Ochiq / Biriktirilgan
        4 => '⏳', 5 => '⏳', 6 => '⏳',      // Jarayonda / kutilmoqda
        7 => '✅', 8 => '✅',                // Hal qilindi / Yopildi
        9 => '❌',                           // Rad etildi
        10 => '⚫',                          // Bekor qilindi
    ];

    /** Bajarilgan holatlar — faqat shular baholanadi. */
    private const RESOLVED = [7, 8];

    /** Baholangan zayavka. */
    private const RATED = '🔷';

    public static function for(mixed $statusId, mixed $clientRating = null): string
    {
        $id = (int) $statusId;

        if (in_array($id, self::RESOLVED, true) && (int) $clientRating > 0) {
            return self::RATED;
        }

        return self::BY_STATUS[$id] ?? '▪️';
    }
}
