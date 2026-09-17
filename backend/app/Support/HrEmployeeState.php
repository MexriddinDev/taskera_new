<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Bank kadrlar tizimi (IABS / HR) bo'yicha xodim holatlari (states & conditions).
 * Manba: Doniyor_aka (1).xlsx (Oracle IABS HR_EMPS holatlar ma'lumotnomasi).
 */
final class HrEmployeeState
{
    // Hujjat holatlari (CONDITION_TYPE = 'S')
    public const DOC_APPROVED = 'SA';                 // Утвержден
    public const DOC_ENTERED = 'SV';                  // Введен
    public const DOC_PENDING_APPROVAL = 'SU';         // На утверждения
    public const DOC_REJECTED = 'SO';                 // Отклонен

    // Xodim ish holatlari (CONDITION_TYPE = 'A', 'K', 'F')
    public const WORKING = 'A';                       // Рабочие (asosiy shtat)
    public const PART_TIME = 'AP';                    // Совместители (o'rindoshlar)
    public const DISMISSED = 'P';                     // Уволенные
    public const DISMISSED_BRANCH_TRANSFER = 'PF';     // Увол. (пер. филиалу)
    public const SUSPENDED = 'PO';                    // Отстранен
    public const MATERNITY_SICK = 'DB';               // Декрет больничный
    public const MATERNITY_CHILD_2 = 'OD';            // Декрет_2 (2 yoshgacha)
    public const MATERNITY_CHILD_3 = 'OF';            // Декрет_3 (3 yoshgacha)
    public const STUDY_LEAVE = 'OU';                  // Ученический отп.
    public const ACADEMIC_LEAVE = 'AO';               // Академик отп.
    public const MILITARY_SERVICE = 'I';              // Воинская служба
    public const ANNUAL_LEAVE = 'OT';                 // Трудовой отп.
    public const UNPAID_LEAVE = 'OB';                 // Без содержания
    public const PAID_LEAVE = 'OS';                   // С содержания
    public const NON_STAFF_WORKING = 'KA';            // Рабочие (не штат.)
    public const NON_STAFF_DISMISSED = 'KP';          // Уволенные (не штат.)
    public const NON_STAFF_SUSPENDED = 'KO';          // Отстраненные (не штат.)
    public const BRANCH_TRANSFER = 'SF';              // Перевод другой филиал
    public const BUSINESS_TRIP = 'K';                 // Командировка
    public const SICK_LEAVE = 'B';                    // Болничные
    public const INTERN = 'AS';                       // Стажеры
    public const BANK_BOARD = 'AK';                   // Совет банка
    public const AUDIT_COMMISSION = 'AT';             // Ревизионная комиссия
    public const OTHER = 'PR';                        // Прочие

    /**
     * Barcha 29 ta holatning to'liq lug'ati.
     *
     * @var array<string, array{id: int, name_ru: string, name_uz: string, type: string, is_working: bool, can_open_ad: bool, notes: string}>
     */
    private const REGISTRY = [
        'SA' => [
            'id' => 1,
            'name_ru' => 'Утвержден',
            'name_uz' => 'Tasdiqlangan',
            'type' => 'S',
            'is_working' => false,
            'can_open_ad' => false,
            'notes' => 'Hujjat holati: Tasdiqlangan',
        ],
        'SV' => [
            'id' => 2,
            'name_ru' => 'Введен',
            'name_uz' => 'Kiritilgan',
            'type' => 'S',
            'is_working' => false,
            'can_open_ad' => false,
            'notes' => 'Hujjat holati: Kiritilgan',
        ],
        'SU' => [
            'id' => 3,
            'name_ru' => 'На утверждения',
            'name_uz' => 'Tasdiqlashda',
            'type' => 'S',
            'is_working' => false,
            'can_open_ad' => false,
            'notes' => 'Hujjat holati: Tasdiqlash kutilmoqda',
        ],
        'SO' => [
            'id' => 4,
            'name_ru' => 'Отклонен',
            'name_uz' => 'Rad etilgan',
            'type' => 'S',
            'is_working' => false,
            'can_open_ad' => false,
            'notes' => 'Hujjat holati: Rad etilgan',
        ],
        'A' => [
            'id' => 5,
            'name_ru' => 'Рабочие',
            'name_uz' => 'Ishlayotgan xodimlar',
            'type' => 'A',
            'is_working' => true,
            'can_open_ad' => true,
            'notes' => 'Asosiy shtatdagi faol ishlayotgan xodimlar',
        ],
        'AP' => [
            'id' => 7,
            'name_ru' => 'Совместители',
            'name_uz' => "O'rindoshlar (qo'shimcha ishlovchilar)",
            'type' => 'A',
            'is_working' => true,
            'can_open_ad' => true,
            'notes' => "Bir necha lavozimda o'rindoshlik asosida ishlovchilar",
        ],
        'P' => [
            'id' => 8,
            'name_ru' => 'Уволенные',
            'name_uz' => "Ishdan bo'shatilganlar",
            'type' => 'A',
            'is_working' => false,
            'can_open_ad' => false,
            'notes' => 'Mehnat shartnomasi bekor qilingan xodimlar',
        ],
        'PF' => [
            'id' => 9,
            'name_ru' => 'Увол. (пер. филиалу)',
            'name_uz' => "Bo'shatilgan (boshqa filialga o'tgan)",
            'type' => 'A',
            'is_working' => false,
            'can_open_ad' => false,
            'notes' => "Boshqa filialga o'tishi sababli bo'shatilgan",
        ],
        'PO' => [
            'id' => 10,
            'name_ru' => 'Отстранен',
            'name_uz' => 'Vazifasidan chetlashtirilgan',
            'type' => 'A',
            'is_working' => false,
            'can_open_ad' => false,
            'notes' => 'Vaqtinchalik ishdan chetlatilgan xodim',
        ],
        'DB' => [
            'id' => 11,
            'name_ru' => 'Декрет больничный',
            'name_uz' => "Homiladorlik va tug'ish ta'tili",
            'type' => 'A',
            'is_working' => false,
            'can_open_ad' => false,
            'notes' => "Dekret kasallik varaqasi asosida ta'tilda",
        ],
        'OD' => [
            'id' => 12,
            'name_ru' => 'Декрет_2',
            'name_uz' => "Bola parvarishlash ta'tili (2 yoshgacha)",
            'type' => 'A',
            'is_working' => false,
            'can_open_ad' => false,
            'notes' => "2 yoshgacha bola parvarishi ta'tili",
        ],
        'OF' => [
            'id' => 13,
            'name_ru' => 'Декрет_3',
            'name_uz' => "Bola parvarishlash ta'tili (3 yoshgacha)",
            'type' => 'A',
            'is_working' => false,
            'can_open_ad' => false,
            'notes' => "3 yoshgacha bola parvarishi ta'tili",
        ],
        'OU' => [
            'id' => 14,
            'name_ru' => 'Ученический отп.',
            'name_uz' => "O'quv ta'tili",
            'type' => 'A',
            'is_working' => false,
            'can_open_ad' => false,
            'notes' => "Malaka oshirish yoki ta'lim muassasasida o'qish ta'tili",
        ],
        'AO' => [
            'id' => 15,
            'name_ru' => 'Академик отп.',
            'name_uz' => "Akademik ta'til",
            'type' => 'A',
            'is_working' => false,
            'can_open_ad' => false,
            'notes' => "Akademik ta'tildagi xodim",
        ],
        'I' => [
            'id' => 16,
            'name_ru' => 'Воинская служба',
            'name_uz' => 'Harbiy xizmat',
            'type' => 'A',
            'is_working' => false,
            'can_open_ad' => false,
            'notes' => 'Harbiy xizmatga chaqirilgan xodim',
        ],
        'OT' => [
            'id' => 17,
            'name_ru' => 'Трудовой отп.',
            'name_uz' => "Mehnat ta'tili",
            'type' => 'A',
            'is_working' => true,
            'can_open_ad' => true,
            'notes' => "Yillik asosiy yoki qo'shimcha mehnat ta'tilida",
        ],
        'OB' => [
            'id' => 18,
            'name_ru' => 'Без содержания',
            'name_uz' => "Ish haqi saqlanmagan ta'tilda",
            'type' => 'A',
            'is_working' => false,
            'can_open_ad' => false,
            'notes' => "O'z hisobidan ta'tilda (ish haqi saqlanmaydi)",
        ],
        'OS' => [
            'id' => 19,
            'name_ru' => 'С содержания',
            'name_uz' => "Ish haqi saqlangan holda ta'tilda",
            'type' => 'A',
            'is_working' => true,
            'can_open_ad' => true,
            'notes' => "Kafolatlangan ta'minot bilan ta'tilda",
        ],
        'KA' => [
            'id' => 20,
            'name_ru' => 'Рабочие (не штат.)',
            'name_uz' => 'Shtatdan tashqari ishlovchilar',
            'type' => 'K',
            'is_working' => true,
            'can_open_ad' => false,
            'notes' => 'Fuqarolik-huquqiy shartnoma asosida shtatdan tashqari ishlovchi',
        ],
        'KP' => [
            'id' => 21,
            'name_ru' => 'Уволенные (не штат.)',
            'name_uz' => "Bo'shatilgan (shtatdan tashqari)",
            'type' => 'K',
            'is_working' => false,
            'can_open_ad' => false,
            'notes' => 'Shartnomasi yakunlangan shtatdan tashqari xodim',
        ],
        'KO' => [
            'id' => 22,
            'name_ru' => 'Отстраненные (не штат.)',
            'name_uz' => 'Chetlatilgan (shtatdan tashqari)',
            'type' => 'K',
            'is_working' => false,
            'can_open_ad' => false,
            'notes' => 'Chetlashtirilgan shtatdan tashqari xodim',
        ],
        'SF' => [
            'id' => 23,
            'name_ru' => 'Перевод другой филиал',
            'name_uz' => "Boshqa filialga o'tkazish jarayonida",
            'type' => 'F',
            'is_working' => true,
            'can_open_ad' => false,
            'notes' => 'Filiallararo rotatsiya jarayonidagi xodim',
        ],
        'K' => [
            'id' => 24,
            'name_ru' => 'Командировка',
            'name_uz' => 'Xizmat safari (komandirovka)',
            'type' => 'K',
            'is_working' => true,
            'can_open_ad' => true,
            'notes' => "Xizmat safari munosabati bilan safarda bo'lgan xodim",
        ],
        'B' => [
            'id' => 25,
            'name_ru' => 'Болничные',
            'name_uz' => "Kasallik ta'tilida (bolnichniy)",
            'type' => 'K',
            'is_working' => true,
            'can_open_ad' => true,
            'notes' => 'Vaqtinchalik mehnatga layoqatsizlik varaqasida',
        ],
        'AS' => [
            'id' => 26,
            'name_ru' => 'Стажеры',
            'name_uz' => 'Stajyorlar',
            'type' => 'A',
            'is_working' => true,
            'can_open_ad' => true,
            'notes' => "Bankda stajirovka o'tayotgan xodimlar",
        ],
        'AK' => [
            'id' => 27,
            'name_ru' => 'Совет банка',
            'name_uz' => "Bank kengashi a'zosi",
            'type' => 'A',
            'is_working' => true,
            'can_open_ad' => true,
            'notes' => "Bank kengashi rahbariyat a'zosi",
        ],
        'AT' => [
            'id' => 28,
            'name_ru' => 'Ревизионная комиссия',
            'name_uz' => "Taftish komissiyasi a'zosi",
            'type' => 'A',
            'is_working' => true,
            'can_open_ad' => true,
            'notes' => "Taftish komissiyasi a'zosi",
        ],
        'PR' => [
            'id' => 29,
            'name_ru' => 'Прочие',
            'name_uz' => 'Boshqa toifalar',
            'type' => 'A',
            'is_working' => false,
            'can_open_ad' => false,
            'notes' => 'Boshqa maxsus toifadagi holatlar',
        ],
    ];

    /**
     * Barcha ro'yxatni olish.
     */
    public static function all(): array
    {
        return self::REGISTRY;
    }

    /**
     * Kod bo'yicha holat ma'lumotlarini qidirish.
     */
    public static function find(string $code): ?array
    {
        $normalized = strtoupper(trim($code));

        return self::REGISTRY[$normalized] ?? null;
    }

    /**
     * Kod bo'yicha o'zbekcha nomini qaytaradi.
     */
    public static function nameUz(string $code): string
    {
        $item = self::find($code);

        return $item['name_uz'] ?? $code;
    }

    /**
     * Kod bo'yicha ruscha nomini qaytaradi.
     */
    public static function nameRu(string $code): string
    {
        $item = self::find($code);

        return $item['name_ru'] ?? $code;
    }

    /**
     * Xodim hozir amalda ishlayaptimi (ta'til/xizmat safari ham hisobga olinadi).
     */
    public static function isWorking(string $code): bool
    {
        $item = self::find($code);

        return $item['is_working'] ?? false;
    }

    /**
     * Ushbu holatdagi xodimga AD akkaunt ochish mumkinmi.
     */
    public static function canOpenAd(string $code): bool
    {
        $item = self::find($code);

        return $item['can_open_ad'] ?? false;
    }

    /**
     * Faol ishlayotgan xodimlar kodlari ro'yxati.
     *
     * @return list<string>
     */
    public static function workingCodes(): array
    {
        return array_keys(array_filter(self::REGISTRY, fn ($item) => $item['is_working']));
    }

    /**
     * Hujjat holatlari (S: SA, SV, SU, SO).
     *
     * @return list<string>
     */
    public static function docStateCodes(): array
    {
        return array_keys(array_filter(self::REGISTRY, fn ($item) => $item['type'] === 'S'));
    }
}