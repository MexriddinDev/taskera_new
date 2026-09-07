<?php

namespace App\Modules\Identity\Infrastructure\Eloquent;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class User extends Authenticatable
{
    use SoftDeletes, HasUuids;

    protected $guarded = ['id'];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function telegramAccount()
    {
        return $this->hasOne(TelegramAccount::class);
    }

    public function employee()
    {
        return $this->belongsTo(\App\Modules\Organization\Infrastructure\Eloquent\Employee::class);
    }
}
