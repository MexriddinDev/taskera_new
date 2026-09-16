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
        // Ustun allaqachon bo'lishi mumkin: migratsiya ilgari ikki qadamga
        // bo'linib yiqilgan — ustun qo'shilgan, keyingi UPDATE esa xato bergan
        // va shu sababli migratsiya bajarilgan deb belgilanmagan. Shart
        // bo'lmasa qayta ishga tushirish "Duplicate column" bilan to'xtardi.
        if (! Schema::hasColumn('permit_requests', 'full_name')) {
            Schema::table('permit_requests', function (Blueprint $table) {
                $table->string('full_name', 255)->nullable()->after('requester_user_id');
            });
        }

        // Mavjud so'rovlarning F.I.Sh i yangi ustunga ko'chadi.
        //
        // Birlashtirish SQL'da emas, PHP'da qilinadi. Ilgari bu yerda
        // `COALESCE(...) || ' ' || COALESCE(...)` turardi: `||` SQLite va
        // PostgreSQL'da satr biriktiradi, MySQL'da esa MANTIQIY OR bo'ladi —
        // natijada MySQL ismni songa aylantirmoqchi bo'lib
        // "Truncated incorrect DOUBLE value" xatosi bilan yiqilardi.
        // Loyihada boshqa to'ldirish migratsiyalari ham aynan shu sababdan
        // PHP sikli bilan yozilgan (2026_09_14_000002).
        DB::table('permit_requests')
            ->whereNull('full_name')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $parts = array_filter(
                        [$row->last_name, $row->first_name, $row->middle_name],
                        static fn ($part) => trim((string) $part) !== ''
                    );

                    // Uchala katak ham bo'sh bo'lsa ustun NULL qoladi —
                    // bo'sh satr yozish uni "to'ldirilgan" ko'rsatib qo'yardi.
                    if ($parts === []) {
                        continue;
                    }

                    DB::table('permit_requests')
                        ->where('id', $row->id)
                        ->update(['full_name' => trim(implode(' ', $parts))]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('permit_requests', function (Blueprint $table) {
            $table->dropColumn('full_name');
        });
    }
};
