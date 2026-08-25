<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Eloquent;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserNotificationPreference extends Model
{
    protected $table = 'user_notification_preferences';

    public $timestamps = false;

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'quiet_hours' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
