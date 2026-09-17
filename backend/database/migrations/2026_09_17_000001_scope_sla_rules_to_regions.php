<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Viloyat SLAsi guruhga emas, HUDUDGA bog'lanadi.
 *
 * Ilgari har viloyat uchun alohida IT guruhi yaratilardi, chunki SLA qoidasi
 * faqat `team_id` bo'yicha topilardi. Natijada zayavka yaratganda "Guruh"
 * ro'yxatida asosiy guruhlar (Texnik guruh, NOC) o'rniga viloyat guruhlari
 * chiqib qolardi — foydalanuvchi qaysi XIZMATNI so'rayotganini tanlay olmasdi.
 *
 * Endi qoidada `region_id` bor: bitta "Texnik guruh" uchun har viloyat o'z
 * muddatini oladi. Zayavka avval (guruh + o'z viloyati) qoidasini qidiradi,
 * topmasa (guruh + NULL) — respublika qoidasiga tushadi.
 */
return new class extends Migration
{
    /** Seeder avtomatik yaratgan viloyat guruhlarining kod shakli. */
    private const SEEDED_TEAM_CODE = 'UZ-%-IT-1';

    public function up(): void
    {
        if (! Schema::hasColumn('sla_rules', 'region_id')) {
            Schema::table('sla_rules', function (Blueprint $table) {
                $table->foreignId('region_id')->nullable()->after('team_id')
                    ->constrained('regions')->restrictOnDelete();
                // Qoida tanlash har doim (tashkilot + guruh + hudud) bo'yicha
                // boradi — TicketSlaService shu uchtasi bilan qidiradi.
                $table->index(['organization_id', 'team_id', 'region_id'], 'sla_rules_org_team_region_idx');
            });
        }

        // Seeder yaratgan, lekin HECH QAYERDA ishlatilmagan viloyat guruhlari
        // olib tashlanadi. Faqat bo'shlari: a'zosi yoki zayavkasi bo'lgan
        // guruhga tegilmaydi — kimdir uni qo'lda to'ldirgan bo'lishi mumkin.
        $orphans = DB::table('teams')
            ->whereNotNull('region_id')
            ->where('code', 'like', self::SEEDED_TEAM_CODE)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('team_members')
                ->whereColumn('team_members.team_id', 'teams.id')->whereNull('left_at'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('tickets')
                ->whereColumn('tickets.assigned_team_id', 'teams.id'))
            ->pluck('id');

        if ($orphans->isEmpty()) {
            return;
        }

        // Qoidalar guruhga tashqi kalit bilan bog'langan — avval ular ketadi.
        DB::table('sla_rules')->whereIn('team_id', $orphans)->delete();
        DB::table('office_support_routes')->whereIn('team_id', $orphans)->delete();
        DB::table('teams')->whereIn('id', $orphans)->delete();
    }

    public function down(): void
    {
        // O'chirilgan guruhlar qaytarilmaydi: ular seeder mahsuloti edi va
        // `RegionalSupportSeeder` ni qayta ishga tushirish bilan tiklanadi.
        Schema::table('sla_rules', function (Blueprint $table) {
            $table->dropIndex('sla_rules_org_team_region_idx');
            $table->dropConstrainedForeignId('region_id');
        });
    }
};
