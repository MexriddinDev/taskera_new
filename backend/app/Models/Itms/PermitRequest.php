<?php

declare(strict_types=1);

namespace App\Models\Itms;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class PermitRequest extends Model
{
    use HasUuids, SoftDeletes;

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_APPROVED = 'APPROVED';

    public const STATUS_REJECTED = 'REJECTED';

    /** Guvohnoma turlari. */
    public const DOCUMENT_TYPES = ['ID_CARD', 'PASSPORT', 'DRIVER_LICENSE'];

    /**
     * Har bir guvohnoma raqamining shakli.
     *
     * O'zbekistonda uchala hujjat ham bir xil ko'rinishda: 2 ta katta lotin
     * harfi va 7 ta raqam (masalan `AA1234567`). Bitta turning qoidasi
     * o'zgarsa — faqat shu massivdagi qatorni almashtirish kifoya.
     */
    public const DOCUMENT_PATTERNS = [
        'ID_CARD' => '/^[A-Z]{2}[0-9]{7}$/',
        'PASSPORT' => '/^[A-Z]{2}[0-9]{7}$/',
        'DRIVER_LICENSE' => '/^[A-Z]{2}[0-9]{7}$/',
    ];

    /**
     * Faqat so'rovchi formadan to'ldiradigan maydonlar.
     *
     * Tegishlilik (`organization_id`, `requester_user_id`), holat (`status`),
     * qaror va kirish/chiqish belgilari ATAYLAB tashqarida qoldirilgan: ularni
     * kontroller o'zi yozadi. Aks holda kelajakda kimdir
     * `create($request->validated())` deb yozsa, so'rovchi o'z so'rovini
     * `APPROVED` qilib yoki kirish vaqtini soxtalashtirib yubora olardi.
     */
    protected $fillable = [
        'full_name',
        'document_type',
        'document_number',
        'host_department',
        'visit_purpose',
        'visit_at',
    ];

    protected function casts(): array
    {
        return [
            'visit_at' => 'datetime',
            'decided_at' => 'datetime',
            'entered_at' => 'datetime',
            'exited_at' => 'datetime',
        ];
    }

    /**
     * Ro'yxatlarda ko'rsatiladigan to'liq F.I.Sh.
     *
     * Yangi so'rovlar `full_name` bilan keladi. Uch alohida ustun faqat eski
     * yozuvlarda to'la — o'shalar uchun yig'ib beriladi.
     */
    public function fullName(): string
    {
        $fullName = trim((string) $this->full_name);

        if ($fullName !== '') {
            return $fullName;
        }

        return trim(implode(' ', array_filter([
            $this->last_name,
            $this->first_name,
            $this->middle_name,
        ])));
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }
}
