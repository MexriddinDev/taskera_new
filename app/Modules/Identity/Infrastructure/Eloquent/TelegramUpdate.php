<?php

namespace App\Modules\Identity\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;

class TelegramUpdate extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];

    public function account()
    {
        return $this->belongsTo(TelegramAccount::class, 'telegram_account_id');
    }
}
