<?php

declare(strict_types=1);

namespace App\Models\Itms;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Permit extends Model
{
    use HasUuids, SoftDeletes;

    /** Guvohnoma turlari — formadagi tanlov ham shu ro'yxatdan quriladi. */
    public const DOCUMENT_TYPES = ['PASSPORT', 'DRIVER_LICENSE'];

    protected $fillable = [
        'organization_id',
        'last_name',
        'first_name',
        'middle_name',
        'document_type',
        'document_number',
        'visit_purpose',
        'visit_at',
        'photo_path',
        'host_department',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'visit_at' => 'datetime',
        ];
    }

    /** Ro'yxatda va tasdiq oynalarida ko'rsatiladigan to'liq F.I.Sh. */
    public function fullName(): string
    {
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
