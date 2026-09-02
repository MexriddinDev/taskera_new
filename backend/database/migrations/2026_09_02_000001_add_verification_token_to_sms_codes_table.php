<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SMS tasdiqlash tokeni uchun alohida ustunlar.
 *
 * Ilgari token `request_id` ustuniga yozilar edi — lekin u SMS gateway'ning
 * so'rov identifikatori (sendCode -> SmsGatewayService::send) bo'lib, yetkazib
 * berishni kuzatish uchun ishlatiladi. Uni qayta yozish log'lardagi bog'lanishni
 * yo'q qilardi. Endi token va "iste'mol qilingan" belgisi mustaqil ustunlarda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_codes', function (Blueprint $table) {
            if (! Schema::hasColumn('sms_codes', 'verification_token')) {
                $table->string('verification_token', 64)->nullable()->after('verified_at');
            }
            if (! Schema::hasColumn('sms_codes', 'verification_consumed_at')) {
                $table->timestamp('verification_consumed_at')->nullable()->after('verification_token');
            }
        });

        Schema::table('sms_codes', function (Blueprint $table) {
            $table->index(['verification_token'], 'sms_codes_verification_token_idx');
        });
    }

    public function down(): void
    {
        Schema::table('sms_codes', function (Blueprint $table) {
            $table->dropIndex('sms_codes_verification_token_idx');
        });

        Schema::table('sms_codes', function (Blueprint $table) {
            $table->dropColumn(['verification_token', 'verification_consumed_at']);
        });
    }
};
