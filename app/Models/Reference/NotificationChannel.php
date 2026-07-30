<?php

namespace App\Models\Reference;

use Illuminate\Database\Eloquent\Model;

class NotificationChannel extends Model
{
    public $timestamps = false;

    protected $table = 'notification_channels';

    protected $fillable = [
        'code',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
