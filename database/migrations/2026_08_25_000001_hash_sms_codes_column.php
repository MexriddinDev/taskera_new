<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

/**
 * SMS kodlari endi PLAINTEXT emas, bcrypt HASH ko'rinishida saqlanadi.
 *
 * - code ustuni string(10) → string(100) (bcrypt hash sig'ishi uchun)
 * - Mavjud yozuvlar (hali tasdiqlanmagan va muddati o'tmagan) o'chiriladi,
 *   chunki ularni hash'ga aylantirib bo'lmaydi — foydalanuvchi yangi SMS so'raydi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_codes', function (Blueprint $table) {
            $table->string('code', 100)->change();
        });

        // Eski plaintext kodlar hash'ga aylantirilmaydi — faol yozuvlar bekor qilinadi
        $deleted = DB::table('sms_codes')
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->delete();

        if ($deleted > 0) {
            Log::info("[MIGRATION] sms_codes: {$deleted} ta eski plaintext kod o'chirildi");
        }
    }

    public function down(): void
    {
        Schema::table('sms_codes', function (Blueprint $table) {
            $table->string('code', 10)->change();
        });
    }
};
