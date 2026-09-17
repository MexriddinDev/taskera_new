<?php

declare(strict_types=1);

namespace App\Models\Itms;

use App\Modules\Organization\Infrastructure\Eloquent\Team;
use App\Modules\Ticketing\Domain\Services\TicketSlaService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class SlaRule extends Model
{
    use HasUuids, SoftDeletes;

    /** Default qoidaning nomi — zayavka kartochkasida shu nom ko'rinadi. */
    public const DEFAULT_NAME = 'Default holat';

    protected $fillable = [
        'organization_id',
        'team_id',
        'priority_id',
        'name',
        'description',
        'accept_minutes',
        'work_minutes',
        'accept_grace_minutes',
        'accept_penalty',
        'work_grace_minutes',
        'work_penalty',
        'reject_penalty',
        'is_active',
        'is_default',
        'region_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'priority_id' => 'integer',
            'region_id' => 'integer',
            'accept_minutes' => 'integer',
            'work_minutes' => 'integer',
            'accept_grace_minutes' => 'integer',
            'accept_penalty' => 'float',
            'work_grace_minutes' => 'integer',
            'work_penalty' => 'float',
            'reject_penalty' => 'float',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    /**
     * Guruhning "Default holat" qoidasi — shablon tanlanmagan zayavka shu
     * muddatni oladi. Har guruh va hudud juftligida aynan bittasi bo'ladi va
     * u o'chirilmaydi: admin faqat ikkita vaqtini o'zgartiradi.
     *
     * `$regionId` NULL — respublika qoidasi, ya'ni hududi aniqlanmagan yoki
     * o'z qoidasi yo'q zayavkalar uchun zaxira.
     */
    public static function ensureDefaultFor(int $organizationId, int $teamId, ?int $regionId = null): self
    {
        // Qidiruv sharti ham tashkilot bo'yicha skoplanadi — repo'da har bir
        // so'rov shunday (`CurrentOrg::id($request)`).
        return static::firstOrCreate(
            [
                'organization_id' => $organizationId,
                'team_id' => $teamId,
                'region_id' => $regionId,
                'is_default' => true,
            ],
            [
                'priority_id' => null,
                'name' => self::DEFAULT_NAME,
                'accept_minutes' => TicketSlaService::DEFAULT_ACCEPT_MINUTES,
                'work_minutes' => TicketSlaService::DEFAULT_WORK_MINUTES,
                'is_active' => true,
            ]
        );
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Qoida qaysi muhimlikka tegishli. `null` — guruhning umumiy qoidasi:
     * muhimligi bo'yicha alohida qoida topilmagan zayavkalarga qo'llanadi.
     */
    public function priority()
    {
        return $this->belongsTo(\App\Models\Reference\TicketPriority::class, 'priority_id');
    }
}
