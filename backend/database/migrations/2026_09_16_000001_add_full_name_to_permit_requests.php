<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F.I.Sh bitta maydonga birlashtiriladi.
 *
 * Uch alohida katak (familiya / ism / otasining ismi) qorovul postida foyda
 * bermasdi: xodim baribir hammasini bir joydan ko'chirib qo'yardi, qo'sh
 * familiya esa noto'g'ri katakka tushardi. Endi forma bitta `full_name`
 * yuboradi.
 *
 * Eski ustunlar O'CHIRILMAYDI: allaqachon yozilgan so'rovlar shu uchtasida
 * turibdi va ular ko'chirib olinadi. `full_name` NULL bo'lishi mumkin — eski
 * yozuv uchun model uchta ustundan yig'ib beradi, yangi so'rov uchun esa
 * kontroller uni majburiy qiladi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permit_requests', function (Blueprint $table) {
            $table->string('full_name', 255)->nullable()->after('requester_user_id');
        });

        // Mavjud so'rovlarning F.I.Sh i yangi ustunga ko'chadi. Bo'sh
        // qismlardan ortiqcha bo'shliq qolmasligi uchun natija TRIM qilinadi.
        DB::table('permit_requests')->whereNull('full_name')->update([
            'full_name' => DB::raw("TRIM(COALESCE(last_name, '') || ' ' || COALESCE(first_name, '') || ' ' || COALESCE(middle_name, ''))"),
        ]);
    }

    public function down(): void
    {
        Schema::table('permit_requests', function (Blueprint $table) {
            $table->dropColumn('full_name');
        });
    }
};
