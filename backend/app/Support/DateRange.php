<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Ro'yxat filtrlarining "dan — gacha" chegarasi.
 *
 * Brauzerdagi `datetime-local` maydoni `2026-09-16T08:30` ko'rinishida
 * yuboradi, `date` esa `2026-09-16`. Ikkalasi ham shu yerda o'qiladi, chunki
 * bo'limlarning biri sana bilan, ikkinchisi sana-vaqt bilan ishlaydi.
 */
final class DateRange
{
    /**
     * Chegarani o'qiydi. Bo'sh qiymat — chegara yo'q degani.
     *
     * Noto'g'ri sana jimgina `null` ga aylanmaydi: shunda filtr ishlamay,
     * foydalanuvchi esa buni bilmay butun ro'yxatni ko'rardi.
     *
     * @throws InvalidArgumentException
     */
    public static function parse(mixed $value): ?CarbonImmutable
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($raw);
        } catch (\Throwable) {
            throw new InvalidArgumentException("Sana noto'g'ri. Namuna: 2026-09-16 yoki 2026-09-16T08:30.");
        }
    }
}
