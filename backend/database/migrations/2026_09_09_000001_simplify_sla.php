<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eski SLA quyi tizimini olib tashlash.
 *
 * Ilgari SLA alohida "korxona darajasidagi" quyi tizim edi: siyosatlar,
 * ularning versiyalari, maqsad metrikalari, ish kalendarlari, bayramlar,
 * eskalatsiya navbati, muddat uzaytirish so'rovlari va har bir zayavka uchun
 * bir nechta "instance" yozuvi. Amalda undan foydalanilmadi va tushunish qiyin
 * edi.
 *
 * O'rniga sodda qoida keladi (keyingi migratsiya — `sla_rules`): guruhga
 * biriktirilgan bitta yozuv, ichida ikkita muddat — qabul qilish va ishlash.
 */
return new class extends Migration
{
    /** Eski SLA quyi tizimining jadvallari — bog'liqlik tartibida (avval bolalari). */
    private const LEGACY_TABLES = [
        'sla_escalation_outbox',
        'sla_instance_events',
        'ticket_sla_extensions',
        'ticket_sla_instances',
        'ticket_sla_runs',
        'ticket_sla_events',
        'ticket_slas',
        'sla_policy_versions',
        'sla_targets',
        'sla_policies',
        'business_hours',
        'calendar_holidays',
        'business_calendars',
    ];

    public function up(): void
    {
        // Eski siyosatga ishora qiluvchi ustunlar. Ular qolsa, `sla_policies`
        // o'chirilgach osilib qolgan tashqi kalit qoladi.
        foreach ([['tickets', 'sla_policy_id'], ['service_offerings', 'default_sla_policy_id']] as [$table, $column]) {
            if (! Schema::hasColumn($table, $column)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($column) {
                // FK nomi muhitga qarab har xil bo'lishi mumkin — bo'lmasa ham
                // ustunni o'chirish davom etadi.
                try {
                    $t->dropForeign([$column]);
                } catch (Throwable $e) {
                    // FK yo'q ekan — davom etamiz.
                }
                $t->dropColumn($column);
            });
        }

        // Jadvallar orasida o'zaro FK bor, shuning uchun tekshiruv vaqtincha
        // o'chiriladi — aks holda tartib qanday bo'lmasin, biri xato beradi.
        Schema::disableForeignKeyConstraints();
        foreach (self::LEGACY_TABLES as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Eski SLA quyi tizimi ATAYLAB tiklanmaydi: u o'nlab jadval va
        // yuzlab satr kod edi, uni migratsiyada qayta yaratish ma'nosiz.
        // Kerak bo'lsa git tarixidagi eski migratsiyalarga qaytiladi.
    }
};
