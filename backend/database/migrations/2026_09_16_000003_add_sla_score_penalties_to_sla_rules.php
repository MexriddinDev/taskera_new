<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SLA bahosi uchun jarima sozlamalari.
 *
 * Qoidada ikkita mustaqil baho bor:
 *   - guruh bahosi — zayavka QABUL QILISH kechikishi bo'yicha (`accept_*`);
 *   - xodim bahosi — zayavka ustida ISHLASH kechikishi bo'yicha (`work_*`).
 *
 * `*_grace_minutes` — muddat o'tgandan keyin yana qancha kechikishga yo'l
 * qo'yiladi. Shundan oshsa `*_penalty` ball ayiriladi. Rad etilgan zayavka
 * uchun `reject_penalty` alohida ayiriladi (muhimlikdan qat'i nazar).
 *
 * Standart qiymatlar muhimlikka qarab to'ldiriladi: muhimligi yuqori
 * zayavkani kechiktirish qimmatroq turadi.
 */
return new class extends Migration
{
    /** Muhimlik kodi => [yo'l qo'yiladigan kechikish (daqiqa), jarima]. */
    private const DEFAULTS = [
        'LOW' => [15, 0.25],
        'MEDIUM' => [10, 0.50],
        'HIGH' => [5, 1.00],
        'CRITICAL' => [5, 1.00],
    ];

    /** Muhimligi ko'rsatilmagan qoida (guruhning umumiy qoidasi). */
    private const FALLBACK = [10, 0.50];

    public function up(): void
    {
        Schema::table('sla_rules', function (Blueprint $table) {
            $table->unsignedInteger('accept_grace_minutes')->default(10)->after('work_minutes');
            $table->decimal('accept_penalty', 3, 2)->default(0.50)->after('accept_grace_minutes');
            $table->unsignedInteger('work_grace_minutes')->default(10)->after('accept_penalty');
            $table->decimal('work_penalty', 3, 2)->default(0.50)->after('work_grace_minutes');
            $table->decimal('reject_penalty', 3, 2)->default(1.00)->after('work_penalty');
        });

        // Mavjud qoidalar muhimligiga mos qiymat oladi — hammasi bir xil
        // standartda qolib ketmasin.
        $priorityIds = DB::table('ticket_priorities')->pluck('id', 'code');

        foreach (self::DEFAULTS as $code => [$grace, $penalty]) {
            $priorityId = $priorityIds[$code] ?? null;
            if ($priorityId === null) {
                continue;
            }

            DB::table('sla_rules')->where('priority_id', $priorityId)->update([
                'accept_grace_minutes' => $grace,
                'accept_penalty' => $penalty,
                'work_grace_minutes' => $grace,
                'work_penalty' => $penalty,
            ]);
        }

        [$grace, $penalty] = self::FALLBACK;
        DB::table('sla_rules')->whereNull('priority_id')->update([
            'accept_grace_minutes' => $grace,
            'accept_penalty' => $penalty,
            'work_grace_minutes' => $grace,
            'work_penalty' => $penalty,
        ]);
    }

    public function down(): void
    {
        Schema::table('sla_rules', function (Blueprint $table) {
            $table->dropColumn([
                'accept_grace_minutes',
                'accept_penalty',
                'work_grace_minutes',
                'work_penalty',
                'reject_penalty',
            ]);
        });
    }
};
