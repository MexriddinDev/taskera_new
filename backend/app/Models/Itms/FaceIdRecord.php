<?php

declare(strict_types=1);

namespace App\Models\Itms;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class FaceIdRecord extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'pinfl',
        'last_name',
        'first_name',
        'middle_name',
        'birth_date',
        'photo_path',
        'document_path',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
        ];
    }

    /** Ro'yxatda ko'rsatiladigan to'liq F.I.Sh. */
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
