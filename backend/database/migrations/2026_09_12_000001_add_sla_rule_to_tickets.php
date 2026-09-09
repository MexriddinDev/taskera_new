<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zayavka qaysi SLA qoidasi (xizmat turi) bo'yicha yaratilgani.
 *
 * Zayavka yaratishda tanlangan shablon aslida SLA qoidasi bo'ladi — uning
 * o'z muddatlari bor. Ilgari bu tanlov serverga umuman yetib bormasdi va
 * muddat guruh bo'yicha topilgan qoidalardan taxmin qilinardi. Endi tanlov
 * zayavkaga yoziladi; tanlanmasa "default holat" ishlaydi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tickets', 'sla_rule_id')) {
            return;
        }

        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('sla_rule_id')->nullable()->after('assigned_team_id')
                ->constrained('sla_rules')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('tickets', 'sla_rule_id')) {
            return;
        }

        Schema::table('tickets', function (Blueprint $table) {
            try {
                $table->dropForeign(['sla_rule_id']);
            } catch (Throwable $e) {
                // FK nomi muhitga qarab boshqacha bo'lishi mumkin.
            }
            $table->dropColumn('sla_rule_id');
        });
    }
};
