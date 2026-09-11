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
        'is_active',
        'is_default',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'priority_id' => 'integer',
            'accept_minutes' => 'integer',
            'work_minutes' => 'integer',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    /**
     * Guruhning "Default holat" qoidasi — shablon tanlanmagan zayavka shu
     * muddatni oladi. Har guruhda aynan bittasi bo'ladi va u o'chirilmaydi:
     * admin faqat ikkita vaqtini o'zgartiradi.
     */
    public static function ensureDefaultFor(int $organizationId, int $teamId): self
    {
        return static::firstOrCreate(
            ['team_id' => $teamId, 'is_default' => true],
            [
                'organization_id' => $organizationId,
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
