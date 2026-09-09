<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mas'ul xodim almashganda oldingi xodim qancha ishlagani.
 *
 * Zayavka boshqa xodimga o'tkazilganda uning taymeri NOLDAN boshlanadi
 * (`tickets.started_at` yangilanadi) — yangi ijrochi o'zidan oldingi vaqt
 * uchun javob bermaydi. Ammo bajarilgan ish yo'qolib ketmasligi kerak: shu
 * ustunda oldingi ijrochi sarflagan daqiqalar saqlanadi va zayavka
 * kartochkasidagi tarixda ko'rinadi.
 */
return new class extends Migration
{
    /** Jadval => vaqt ustuni qo'shiladigan joy. */
    private const TABLES = ['ticket_assignment_history', 'ticket_reassignments'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasColumn($table, 'spent_minutes')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) {
                $t->unsignedInteger('spent_minutes')->nullable()->after('reason');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasColumn($table, 'spent_minutes')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('spent_minutes');
            });
        }
    }
};
