<?php

declare(strict_types=1);

namespace App\Models\Itms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

final class FinesseAccount extends Model
{
    protected $fillable = [
        'organization_id',
        'user_id',
        'login_id',
        'password_encrypted',
        'extension',
        'last_state',
        'last_checked_at',
    ];

    /** Parol hech qachon JSON javobga tushmasin. */
    protected $hidden = ['password_encrypted'];

    protected function casts(): array
    {
        return [
            'last_checked_at' => 'datetime',
        ];
    }

    /** Finesse Basic auth uchun ochiq parol — faqat so'rov yuborish paytida. */
    public function password(): string
    {
        return Crypt::decryptString($this->password_encrypted);
    }
}
