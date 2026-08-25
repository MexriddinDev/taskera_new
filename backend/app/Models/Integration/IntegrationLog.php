<?php

namespace App\Models\Integration;

use Illuminate\Database\Eloquent\Model;

class IntegrationLog extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'request_meta' => 'array',
            'response_meta' => 'array',
            'duration_ms' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function integration()
    {
        return $this->belongsTo(Integration::class);
    }
}
