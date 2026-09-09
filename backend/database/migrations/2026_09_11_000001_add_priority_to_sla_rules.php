<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitta guruhga bir nechta SLA qoidasi.
 *
 * Ilgari qoida guruhga BIRMA-BIR bog'langan edi va ikkinchisini qo'shib
 * bo'lmasdi. Endi qoidalar zayavka MUHIMLIGI bo'yicha ajratiladi: guruhda har
 * bir muhimlik uchun alohida muddat va bitta "umumiy" qoida (priority_id =
 * null) bo'lishi mumkin. Umumiy qoida muhimligi mos qoidasi yo'q zayavkalarga
 * qo'llanadi — ya'ni eski yozuvlar o'zgarishsiz ishlayveradi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sla_rules', function (Blueprint $table) {
            $table->unsignedSmallInteger('priority_id')->nullable()->after('team_id');
            $table->foreign('priority_id')->references('id')->on('ticket_priorities')->nullOnDelete();
            $table->index(['organization_id', 'team_id', 'priority_id'], 'sla_rules_org_team_priority_idx');
        });
    }

    public function down(): void
    {
        Schema::table('sla_rules', function (Blueprint $table) {
            $table->dropIndex('sla_rules_org_team_priority_idx');
            try {
                $table->dropForeign(['priority_id']);
            } catch (Throwable $e) {
                // FK nomi muhitga qarab boshqacha bo'lishi mumkin.
            }
            $table->dropColumn('priority_id');
        });
    }
};
