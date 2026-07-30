<?php

declare(strict_types=1);

namespace App\Modules\Notification\Infrastructure\Eloquent;

use App\Modules\Organization\Infrastructure\Eloquent\Organization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class NotificationDelivery extends Model
{
    use HasUuids;

    protected $table = 'notification_deliveries';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'provider_response' => 'array',
            'attempt_count' => 'integer',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
            'next_attempt_at' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function notification()
    {
        return $this->belongsTo(Notification::class);
    }
}
