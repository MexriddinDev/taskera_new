<?php

declare(strict_types=1);

namespace App\Support;

/**
 * PINFL (JShShIR) — 14 xonali shaxsiy raqam.
 *
 * Tug'ilgan sana raqamning o'zida kodlangan, shuning uchun uni alohida
 * so'rash shart emas:
 *
 *   1-xona    asr va jins:  1,2 -> 18xx   3,4 -> 19xx   5,6 -> 20xx
 *   2-3 xona  kun
 *   4-5 xona  oy
 *   6-7 xona  yilning oxirgi ikki raqami
 *
 * Masalan `32503890123456` -> 25.03.1989.
 */
final class Pinfl
{
    /** 1-xona -> asr boshi. Boshqa qiymat kutilmaydi. */
    private const CENTURY = [
        '1' => 1800, '2' => 1800,
        '3' => 1900, '4' => 1900,
        '5' => 2000, '6' => 2000,
    ];

    /**
     * PINFL dan tug'ilgan sanani ajratadi.
     *
     * Raqam noto'g'ri (uzunligi boshqa, raqam emas, asr belgisi tanilmagan
     * yoki sana mavjud emas — masalan 31-fevral) bo'lsa `null` qaytaradi:
     * chaqiruvchi tomon maydonni bo'sh qoldirib, qo'lda to'ldirishga ruxsat
     * beradi.
     */
    public static function birthDate(string $pinfl): ?string
    {
        if (preg_match('/^\d{14}$/', $pinfl) !== 1) {
            return null;
        }

        $centuryStart = self::CENTURY[$pinfl[0]] ?? null;

        if ($centuryStart === null) {
            return null;
        }

        $day = (int) substr($pinfl, 1, 2);
        $month = (int) substr($pinfl, 3, 2);
        $year = $centuryStart + (int) substr($pinfl, 5, 2);

        if (! checkdate($month, $day, $year)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }
}
