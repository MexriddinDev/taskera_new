<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * sms_codes jadvali uchun performance indeksi:
 * verifyCode va sendCode so'rovlari (phone + verified_at) tezlashadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_codes', function (Blueprint $table) {
            $table->index(['phone', 'verified_at', 'id'], 'sms_codes_phone_verified_idx');
            $table->index('expires_at', 'sms_codes_expires_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('sms_codes', function (Blueprint $table) {
            $table->dropIndex('sms_codes_phone_verified_idx');
            $table->dropIndex('sms_codes_expires_at_idx');
        });
    }
};
