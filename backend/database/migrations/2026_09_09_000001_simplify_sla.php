<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * SLA ni soddalashtirish.
 *
 * Ilgari SLA alohida "korxona darajasidagi" quyi tizim edi: siyosatlar,
 * ularning versiyalari, maqsadlar, ish kalendarlari, bayramlar, eskalatsiya
 * navbati, muddat uzaytirish so'rovlari va har bir zayavka uchun bir nechta
 * "instance" yozuvi. Amalda undan foydalanilmadi va tushunish qiyin edi.
 *
 * Endi SLA — zayavka KATEGORIYASINING uch maydoni:
 *   qabul qilish → ishlash → yopish.
 * Kategoriya yaratilganda SLA ham o'sha yerda beriladi, alohida jadval va
 * alohida hisoblagich kerak emas: muddatlar zayavka vaqtlaridan hisoblanadi.
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
        Schema::table('categories', function (Blueprint $t) {
            // Uch bosqich, daqiqalarda. Standart: 30 daqiqa / 4 soat / 2 soat.
            $t->unsignedInteger('sla_accept_minutes')->default(30)->after('default_priority_id');
            $t->unsignedInteger('sla_work_minutes')->default(240)->after('sla_accept_minutes');
            $t->unsignedInteger('sla_close_minutes')->default(120)->after('sla_work_minutes');
        });

        // Eski siyosatga ishora qiluvchi ustunlar. Ular qolsa, sla_policies
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

        $this->seedCategoriesFromTeams();

        // Jadvallar orasida o'zaro FK bor, shuning uchun tekshiruv vaqtincha
        // o'chiriladi — aks holda tartib qanday bo'lmasin, biri xato beradi.
        Schema::disableForeignKeyConstraints();
        foreach (self::LEGACY_TABLES as $table) {
            Schema::dropIfExists($table);
        }
        Schema::enableForeignKeyConstraints();
    }

    /**
     * Har bir guruh uchun kategoriya yaratadi.
     *
     * Zayavkaning `category` maydoniga guruh nomi yoziladi (CreateTaskModal),
     * SLA esa kategoriya nomiga qarab topiladi. Shuning uchun mavjud guruhlar
     * uchun kategoriya bo'lmasa, SLA ekrani bo'sh turar va hamma zayavka
     * standart muddatlarda ishlardi. Nomi bir xil kategoriya bor bo'lsa,
     * qaytadan yaratilmaydi.
     */
    private function seedCategoriesFromTeams(): void
    {
        if (! Schema::hasTable('teams')) {
            return;
        }

        $teams = DB::table('teams')->whereNull('deleted_at')->get(['id', 'organization_id', 'name']);

        foreach ($teams as $team) {
            $exists = DB::table('categories')->whereNull('deleted_at')
                ->where('organization_id', $team->organization_id)
                ->where('name', $team->name)
                ->exists();

            if ($exists) {
                continue;
            }

            $code = strtoupper(Str::slug((string) $team->name, '_')) ?: 'TEAM_'.$team->id;
            if (DB::table('categories')->where('code', $code)->exists()) {
                $code = $code.'_'.$team->id;
            }

            DB::table('categories')->insert([
                'public_id' => (string) Str::uuid(),
                'organization_id' => $team->organization_id,
                'code' => substr($code, 0, 64),
                'name' => $team->name,
                'default_team_id' => $team->id,
                'sla_accept_minutes' => 30,
                'sla_work_minutes' => 240,
                'sla_close_minutes' => 120,
                'is_active' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Eski SLA quyi tizimi ATAYLAB tiklanmaydi: u o'nlab jadval va
        // yuzlab satr kod edi, uni migratsiyada qayta yaratish ma'nosiz.
        // Kerak bo'lsa git tarixidagi eski migratsiyalarga qaytiladi.
        Schema::table('categories', function (Blueprint $t) {
            $t->dropColumn(['sla_accept_minutes', 'sla_work_minutes', 'sla_close_minutes']);
        });
    }
};
