<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

/**
 * Shablon va SLA izohidagi "Xodim F.I.Sh:" kabi bo'sh qatorlarni so'rovchining
 * ma'lumoti bilan to'ldiradi.
 *
 * Matnning o'zi BAZADA O'ZGARMAYDI — to'ldirish faqat javob yuborilayotganda
 * bo'ladi. Shuning uchun admin kiritgan shablon o'z holicha qoladi va uni
 * tahrirlash oynasi (`?raw=1`) xom matnni oladi.
 *
 * Token (`{{fullName}}`) usuli ataylab tanlanmadi: bazadagi mavjud matnlar
 * qo'lda yozilgan va ularni qayta yozish admin mehnatini yo'qotardi.
 */
final class RequesterPrefill
{
    /**
     * Tanish qator nomlari. Kalit — qiymat manbai, qiymat — qator nomida
     * uchraydigan bo'laklar (kichik harf, faqat harf va raqam qoldirilgan).
     *
     * Ro'yxat ataylab bitta joyda: qator nomlari turlicha yoziladi
     * ("Xodim F.I.Sh", "F.I.O", "Tarkibiy bo'linma / Departament").
     */
    private const LABELS = [
        'fullName' => ['fish', 'fio', 'ismsharif', 'xodim'],
        'department' => ['departament', 'bolim', 'bolinma'],
        'phone' => ['telefon'],
        'position' => ['lavozim'],
    ];

    public static function apply(?string $text, ?User $user): ?string
    {
        if ($text === null || $text === '' || $user === null) {
            return $text;
        }

        $values = self::values($user);
        if ($values === []) {
            return $text;
        }

        // Qator ajratgichi saqlanadi: matn Windows'da yozilgan bo'lishi mumkin.
        $lines = preg_split("/(\r\n|\n|\r)/", $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];

        foreach ($lines as $index => $line) {
            if ($index % 2 === 1) {
                continue; // ajratgichning o'zi
            }

            $colon = mb_strpos($line, ':');
            if ($colon === false) {
                continue;
            }

            // Ikki nuqtadan keyin allaqachon nimadir yozilgan bo'lsa tegilmaydi.
            if (trim(mb_substr($line, $colon + 1)) !== '') {
                continue;
            }

            $field = self::fieldFor(mb_substr($line, 0, $colon));
            if ($field === null || ($values[$field] ?? '') === '') {
                continue;
            }

            $lines[$index] = mb_substr($line, 0, $colon + 1).' '.$values[$field];
        }

        return implode('', $lines);
    }

    /** Qator nomi qaysi maydonga tegishli — mos kelmasa `null`. */
    private static function fieldFor(string $label): ?string
    {
        $normalized = preg_replace('/[^a-z0-9]/', '', mb_strtolower($label)) ?? '';
        if ($normalized === '') {
            return null;
        }

        foreach (self::LABELS as $field => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($normalized, $needle)) {
                    return $field;
                }
            }
        }

        return null;
    }

    /**
     * So'rovchining ma'lumoti. Manba `UserResource` bilan bir xil bo'lishi
     * shart: zayavka matnidagi ism sahifadagi ismdan farq qilmasin.
     *
     * @return array<string, string>
     */
    private static function values(User $user): array
    {
        $user->loadMissing(['employee.department', 'employee.position']);
        $employee = $user->employee;

        $fullName = trim(implode(' ', array_filter([
            $employee?->first_name ?? $user->username,
            $employee?->last_name,
            $employee?->middle_name,
        ])));

        return array_filter([
            'fullName' => $fullName !== '' ? $fullName : (string) $user->username,
            'department' => (string) ($employee?->department?->name ?? ''),
            'phone' => (string) ($employee?->phone ?? ''),
            'position' => (string) ($employee?->position?->name ?? ''),
        ], static fn (string $value): bool => $value !== '');
    }
}
