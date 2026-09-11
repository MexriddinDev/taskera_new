<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Har guruhning "Default holat" SLA qoidasi.
 *
 * Ilgari shablonsiz zayavkaga guruhning umumiy qoidalaridan eng qisqasi
 * qo'llanardi — bu tanlov yashirin edi: admin qaysi muddat ishlayotganini
 * hech qayerda ko'ra olmasdi va yangi qoida qo'shilishi bilan eski
 * zayavkalarning muddati jimgina qisqarib ketardi.
 *
 * Endi har guruhda aniq bitta default yozuvi bor. Mavjud guruhlarda muddat
 * o'zgarib ketmasligi uchun u hozir amalda bo'lgan qiymatdan — eng qisqa
 * umumiy qoidadan — ko'chiriladi, umumiy qoida bo'lmasa tizim standarti
 * (15/30) olinadi.
 */
return new class extends Migration
{
    private const DEFAULT_ACCEPT_MINUTES = 15;

    private const DEFAULT_WORK_MINUTES = 30;

    public function up(): void
    {
        Schema::table('sla_rules', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('is_active');
            $table->index(['organization_id', 'team_id', 'is_default']);
        });

        $teams = DB::table('teams')->whereNull('deleted_at')->get(['id', 'organization_id']);

        foreach ($teams as $team) {
            $exists = DB::table('sla_rules')
                ->where('team_id', $team->id)
                ->where('is_default', true)
                ->whereNull('deleted_at')
                ->exists();

            if ($exists) {
                continue;
            }

            // Hozir amalda bo'lgan muddat: umumiy qoidalardan eng qattig'i.
            $current = DB::table('sla_rules')
                ->where('team_id', $team->id)
                ->whereNull('priority_id')
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->orderBy('accept_minutes')
                ->orderBy('work_minutes')
                ->orderBy('id')
                ->first(['accept_minutes', 'work_minutes']);

            DB::table('sla_rules')->insert([
                'public_id' => (string) Str::uuid(),
                'organization_id' => $team->organization_id,
                'team_id' => $team->id,
                'priority_id' => null,
                'name' => 'Default holat',
                'description' => null,
                'accept_minutes' => $current->accept_minutes ?? self::DEFAULT_ACCEPT_MINUTES,
                'work_minutes' => $current->work_minutes ?? self::DEFAULT_WORK_MINUTES,
                'is_active' => true,
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('sla_rules')->where('is_default', true)->delete();

        Schema::table('sla_rules', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'team_id', 'is_default']);
            $table->dropColumn('is_default');
        });
    }
};
