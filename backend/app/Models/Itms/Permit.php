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
        'full_name',
        'document_type',
        'document_number',
        'visit_purpose',
        'visit_at',
        'visitor_organization',
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

    public function uniqueIds(): array
    {
        return ['public_id'];
    }
}
