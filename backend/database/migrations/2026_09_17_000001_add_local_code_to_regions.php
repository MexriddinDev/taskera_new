<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Viloyatga bankning `local_code` i biriktiriladi.
 *
 * HR/AD dan keladigan `local_code` aynan VILOYATNI bildiradi (XM000 — Xorazm,
 * AV020 — Andijon, ...), filialni emas: filial `bxm_code` bilan ajraladi.
 * Shu bog'lanish bo'lgach zayavka so'rovchining local kodi bo'yicha to'g'ridan
 * to'g'ri o'z viloyatiga tushadi va har bir filial uchun alohida yo'nalish
 * sozlash shart bo'lmaydi.
 *
 * "00000" (Respublika AT xalq banki) ATAYLAB biriktirilmaydi: bosh boshqarma
 * zayavkalari hozirgidek respublika qamrovida qolishi kerak. Unga viloyat
 * berilsa, bosh boshqarma zayavkalari "regional" bo'lib qolardi va ularni
 * hozirgi respublika xodimlari ko'rmay qolardi.
 */
return new class extends Migration
{
    /** Bankning viloyat local kodi => `regions.code`. */
    private const LOCAL_CODES = [
        'AV020' => 'UZ-AN',
        'BV000' => 'UZ-BU',
        'D2016' => 'UZ-TK',
        'FV000' => 'UZ-FA',
        'JV040' => 'UZ-JI',
        'NM000' => 'UZ-NG',
        'NV000' => 'UZ-NW',
        'QR000' => 'UZ-QR',
        'QV000' => 'UZ-QA',
        'SM000' => 'UZ-SA',
        'SN000' => 'UZ-SU',
        'SR000' => 'UZ-SI',
        'TV000' => 'UZ-TO',
        'XM000' => 'UZ-XO',
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('regions', 'local_code')) {
            Schema::table('regions', function (Blueprint $table) {
                $table->string('local_code', 32)->nullable()->after('code');
                $table->index(['organization_id', 'local_code']);
            });
        }

        // Kod bo'yicha yangilanadi: `regions` har tashkilotda o'z yozuviga ega
        // bo'lishi mumkin, shuning uchun `code` bo'yicha hammasiga qo'yiladi.
        foreach (self::LOCAL_CODES as $localCode => $regionCode) {
            DB::table('regions')->where('code', $regionCode)->update(['local_code' => $localCode]);
        }
    }

    public function down(): void
    {
        Schema::table('regions', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'local_code']);
            $table->dropColumn('local_code');
        });
    }
};
