<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Zayavka qaysi qurilmadan yuborilganini User-Agent bo'yicha aniqlaydi.
 *
 * MUHIM: bu ma'lumot zayavka YARATILAYOTGAN paytda hisoblanib, tickets.metadata
 * ga saqlanadi. Ilgari TicketResource User-Agent'ni javob qaytarish paytida
 * o'qir edi — natijada zayavkani KO'RAYOTGAN odamning qurilmasi ko'rsatilardi,
 * yaratganiniki emas.
 */
final class DeviceInfo
{
    public const KIND_DESKTOP = 'desktop';

    public const KIND_MOBILE = 'mobile';

    public const KIND_TABLET = 'tablet';

    public const KIND_TELEGRAM = 'telegram';

    public const KIND_UNKNOWN = 'unknown';

    /**
     * @return array{kind: string, os: string|null, browser: string|null, label: string}
     */
    public static function fromUserAgent(?string $userAgent): array
    {
        $ua = trim((string) $userAgent);

        if ($ua === '') {
            return self::make(self::KIND_UNKNOWN, null, null);
        }

        $os = self::detectOs($ua);
        $browser = self::detectBrowser($ua);
        $kind = self::detectKind($ua);

        return self::make($kind, $os, $browser);
    }

    /**
     * Telegram bot orqali kelgan zayavkalar uchun. Telegram Bot API mijoz
     * qurilmasi haqida hech qanday ma'lumot bermaydi, shuning uchun bu yerda
     * qurilma emas, kanal belgilanadi.
     *
     * @return array{kind: string, os: string|null, browser: string|null, label: string}
     */
    public static function telegram(): array
    {
        return self::make(self::KIND_TELEGRAM, null, null);
    }

    /**
     * Saqlangan (yoki bo'sh) qiymatni har doim to'liq shaklga keltiradi —
     * eski zayavkalarda metadata.device umuman bo'lmaydi.
     *
     * @return array{kind: string, os: string|null, browser: string|null, label: string}
     */
    public static function normalize(mixed $stored): array
    {
        if (! is_array($stored) || ! isset($stored['kind'])) {
            return self::make(self::KIND_UNKNOWN, null, null);
        }

        return self::make(
            (string) $stored['kind'],
            isset($stored['os']) ? (string) $stored['os'] : null,
            isset($stored['browser']) ? (string) $stored['browser'] : null,
        );
    }

    /**
     * @return array{kind: string, os: string|null, browser: string|null, label: string}
     */
    private static function make(string $kind, ?string $os, ?string $browser): array
    {
        $names = [
            self::KIND_DESKTOP => 'Kompyuter',
            self::KIND_MOBILE => 'Telefon',
            self::KIND_TABLET => 'Planshet',
            self::KIND_TELEGRAM => 'Telegram',
            self::KIND_UNKNOWN => "Noma'lum qurilma",
        ];

        $label = $names[$kind] ?? $names[self::KIND_UNKNOWN];
        $details = array_values(array_filter([$os, $browser]));

        if ($details !== []) {
            $label .= ' ('.implode(' / ', $details).')';
        }

        return [
            'kind' => isset($names[$kind]) ? $kind : self::KIND_UNKNOWN,
            'os' => $os,
            'browser' => $browser,
            'label' => $label,
        ];
    }

    private static function detectKind(string $ua): string
    {
        // Planshetni telefondan OLDIN tekshiramiz: Android planshetlar ham
        // "Android" deb yozadi, farqi — "Mobile" so'zining yo'qligida.
        if (stripos($ua, 'iPad') !== false
            || stripos($ua, 'Tablet') !== false
            || (stripos($ua, 'Android') !== false && stripos($ua, 'Mobile') === false)) {
            return self::KIND_TABLET;
        }

        if (stripos($ua, 'Mobi') !== false
            || stripos($ua, 'iPhone') !== false
            || stripos($ua, 'iPod') !== false
            || stripos($ua, 'Android') !== false
            || stripos($ua, 'Windows Phone') !== false) {
            return self::KIND_MOBILE;
        }

        if (stripos($ua, 'Windows') !== false
            || stripos($ua, 'Macintosh') !== false
            || stripos($ua, 'Linux') !== false
            || stripos($ua, 'CrOS') !== false) {
            return self::KIND_DESKTOP;
        }

        return self::KIND_UNKNOWN;
    }

    private static function detectOs(string $ua): ?string
    {
        return match (true) {
            stripos($ua, 'Windows') !== false => 'Windows',
            stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false || stripos($ua, 'iPod') !== false => 'iOS',
            stripos($ua, 'Macintosh') !== false || stripos($ua, 'Mac OS') !== false => 'macOS',
            stripos($ua, 'Android') !== false => 'Android',
            stripos($ua, 'CrOS') !== false => 'ChromeOS',
            stripos($ua, 'Linux') !== false => 'Linux',
            default => null,
        };
    }

    private static function detectBrowser(string $ua): ?string
    {
        // Tartib muhim: Edge/Opera o'zini Chrome deb ham ataydi, Chrome esa Safari deb.
        return match (true) {
            stripos($ua, 'Edg') !== false => 'Edge',
            stripos($ua, 'OPR') !== false || stripos($ua, 'Opera') !== false => 'Opera',
            stripos($ua, 'Firefox') !== false => 'Firefox',
            stripos($ua, 'Chrome') !== false => 'Chrome',
            stripos($ua, 'Safari') !== false => 'Safari',
            default => null,
        };
    }
}
