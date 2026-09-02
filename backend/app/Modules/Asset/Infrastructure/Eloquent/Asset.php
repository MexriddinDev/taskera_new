<?php

namespace App\Modules\Asset\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asset extends Model
{
    use SoftDeletes, HasUuids;

    protected $guarded = ['id'];

    /**
     * DIQQAT: `attributes` ustuni bu yerda MAXSUS cast qilinmaydi.
     *
     * `Model::$attributes` — Eloquent'ning ichki xom atributlar massivi. Shu nomli
     * ustunni cast qilish model/trait ichidagi har qanday `$this->attributes`
     * murojaatini ikki ma'noli qiladi (HasAttributes ichki mantiqi, fill(),
     * setAttribute() yo'llari). Shuning uchun jsonb ustun quyidagi
     * `customAttributes` accessor/mutator orqali ochiladi.
     */
    protected function casts(): array
    {
        return [
            'ip_addresses' => 'array',
            'mac_addresses' => 'array',
        ];
    }

    /**
     * `attributes` jsonb ustuni — nomi to'qnashmaydigan xavfsiz kirish nuqtasi.
     *
     * O'qish:  $asset->custom_attributes  → array
     * Yozish:  $asset->custom_attributes = ['cpu' => 8]
     */
    protected function customAttributes(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: function () {
                $raw = $this->getAttributeFromArray('attributes');

                if (is_array($raw)) {
                    return $raw;
                }

                if (! is_string($raw) || $raw === '') {
                    return [];
                }

                $decoded = json_decode($raw, true);

                return is_array($decoded) ? $decoded : [];
            },
            set: fn ($value) => ['attributes' => json_encode($value ?? [])],
        );
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(AssetModel::class, 'model_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Organization\Infrastructure\Eloquent\Employee::class, 'owner_employee_id');
    }

    public function custodian(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Organization\Infrastructure\Eloquent\Employee::class, 'custodian_employee_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Organization\Infrastructure\Eloquent\Department::class, 'department_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Organization\Infrastructure\Eloquent\Branch::class, 'branch_id');
    }
}
