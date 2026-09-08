<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\SLA\Domain\Services\SlaConfiguration;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * SLA modulining boshlang'ich sozlamasi: har tashkilot uchun ikkita ish
 * kalendari va prioritet bo'yicha 5 ta nashr etilgan (published) SLA qoidasi.
 *
 * Muddatlar TaskFlow_SLA hujjatidagi 5-bo'lim matritsasidan olingan. Qoidalar
 * administrator UI orqali tahrirlashi mumkin bo'lgan boshlang'ich qiymat —
 * seeder mavjud kod (`code`) bo'yicha idempotent, ya'ni qayta ishga tushirilsa
 * allaqachon yaratilgan qoidalarga tegmaydi.
 *
 * Xabar oluvchi sifatida faqat mas'ul xodim belgilanadi: rahbarlar ro'yxati
 * tashkilotga bog'liq, uni kodga yozib qo'yish mumkin emas. Shu sababli
 * qo'shimcha vaqt (extension) ham o'chirilgan holda keladi — tasdiqlovchini
 * administrator SLA builderda tanlaydi va yoqadi.
 */
class SlaBaselineSeeder extends Seeder
{
    /** Har bir qoida uchun daqiqalarda: [metric => minutes]. */
    private const MATRIX = [
        'DEFAULT-SLA' => ['name' => 'Standart SLA (fallback)', 'priority' => null, 'calendar' => 'office', 'weight' => 0,
            'targets' => ['ASSIGNMENT' => 30, 'ACCEPTANCE' => 60, 'FIRST_RESPONSE' => 120, 'WORK_START' => 120, 'RESOLUTION' => 480, 'CLOSURE' => 240]],
        'SLA-P1' => ['name' => 'P1 — Kritik (24/7)', 'priority' => 'CRITICAL', 'calendar' => '24x7', 'weight' => 10,
            'targets' => ['ASSIGNMENT' => 5, 'ACCEPTANCE' => 5, 'FIRST_RESPONSE' => 10, 'WORK_START' => 15, 'RESOLUTION' => 120, 'CLOSURE' => 30]],
        'SLA-P2' => ['name' => 'P2 — Yuqori', 'priority' => 'HIGH', 'calendar' => 'office', 'weight' => 10,
            'targets' => ['ASSIGNMENT' => 10, 'ACCEPTANCE' => 10, 'FIRST_RESPONSE' => 20, 'WORK_START' => 30, 'RESOLUTION' => 240, 'CLOSURE' => 60]],
        'SLA-P3' => ['name' => "P3 — O'rta", 'priority' => 'MEDIUM', 'calendar' => 'office', 'weight' => 10,
            'targets' => ['ASSIGNMENT' => 15, 'ACCEPTANCE' => 30, 'FIRST_RESPONSE' => 60, 'WORK_START' => 60, 'RESOLUTION' => 480, 'CLOSURE' => 120]],
        'SLA-P4' => ['name' => 'P4 — Past', 'priority' => 'LOW', 'calendar' => 'office', 'weight' => 10,
            'targets' => ['ASSIGNMENT' => 30, 'ACCEPTANCE' => 120, 'FIRST_RESPONSE' => 240, 'WORK_START' => 120, 'RESOLUTION' => 960, 'CLOSURE' => 240]],
    ];

    /** Taymer qaysi hodisadan boshlanadi — SlaConfiguration dagi ruxsat etilgan juftliklar. */
    private const STARTS = ['ASSIGNMENT' => 'CREATED', 'ACCEPTANCE' => 'ASSIGNED', 'FIRST_RESPONSE' => 'CREATED',
        'WORK_START' => 'ACCEPTED', 'RESOLUTION' => 'CREATED', 'CLOSURE' => 'RESOLVED'];

    public function run(): void
    {
        $configuration = app(SlaConfiguration::class);
        $priorities = DB::table('ticket_priorities')->pluck('id', 'code');

        foreach (DB::table('organizations')->whereNull('deleted_at')->pluck('id') as $organizationId) {
            $calendars = [
                '24x7' => $this->calendar($organizationId, '24/7 — uzluksiz', true),
                'office' => $this->calendar($organizationId, 'Ish vaqti (Du–Ju 09:00–18:00)', false),
            ];

            foreach (self::MATRIX as $code => $rule) {
                if (DB::table('sla_policies')->where('organization_id', $organizationId)->where('code', $code)->exists()) {
                    continue;
                }
                if ($rule['priority'] && ! isset($priorities[$rule['priority']])) {
                    continue;
                }
                $this->publish($configuration, $organizationId, $code, $rule, $calendars[$rule['calendar']], $priorities);
            }
        }
    }

    /** Kalendarni nomi bo'yicha topadi yoki yaratadi; ish vaqti kalendariga Du–Ju 09:00–18:00 yoziladi. */
    private function calendar(int $organizationId, string $name, bool $is24x7): int
    {
        $existing = DB::table('business_calendars')->where('organization_id', $organizationId)
            ->where('name', $name)->whereNull('deleted_at')->value('id');
        if ($existing) {
            return (int) $existing;
        }

        $timezoneId = DB::table('timezones')->where('name', 'Asia/Tashkent')->value('id')
            ?? DB::table('timezones')->where('is_active', true)->min('id');

        $id = DB::table('business_calendars')->insertGetId(['public_id' => (string) Str::uuid(), 'organization_id' => $organizationId,
            'name' => $name, 'timezone_id' => $timezoneId, 'is_24x7' => $is24x7, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now()]);

        if (! $is24x7) {
            // weekday: 0 = yakshanba ... 6 = shanba (BusinessTimeCalculator shu tartibni kutadi).
            foreach ([1, 2, 3, 4, 5] as $weekday) {
                DB::table('business_hours')->insert(['calendar_id' => $id, 'weekday' => $weekday,
                    'start_time' => '09:00:00', 'end_time' => '18:00:00', 'is_working' => true]);
            }
        }

        return $id;
    }

    /** Qoidani validatsiyadan o'tkazib, o'zgarmas versiya bilan nashr qiladi. */
    private function publish(SlaConfiguration $configuration, int $organizationId, string $code, array $rule, int $calendarId, $priorities): void
    {
        $mode = $rule['calendar'] === '24x7' ? '24X7' : 'BUSINESS';
        $config = [
            'code' => $code,
            'name' => $rule['name'],
            'description' => 'Boshlang\'ich SLA qoidasi (SlaBaselineSeeder). Muddatlarni SLA builderda tahrirlash mumkin.',
            'effective_from' => now()->startOfDay()->toDateString(),
            'effective_to' => null,
            'calendar_id' => $calendarId,
            'policy_priority' => $rule['weight'],
            'scope' => ['priority_id' => $rule['priority'] ? (int) $priorities[$rule['priority']] : null],
            'targets' => array_values(array_map(fn ($metric, $minutes) => ['metric' => $metric, 'minutes' => $minutes,
                'start' => self::STARTS[$metric], 'calendar_mode' => $mode], array_keys($rule['targets']), $rule['targets'])),
            'pause_statuses' => ['WAITING_USER'],
            'extension' => ['enabled' => false, 'max_minutes' => 120, 'approver_ids' => []],
            'escalations' => [
                ['threshold' => 75, 'assignee' => true, 'user_ids' => [], 'channels' => ['IN_APP']],
                ['threshold' => 90, 'assignee' => true, 'user_ids' => [], 'channels' => ['IN_APP', 'EMAIL']],
                ['threshold' => 100, 'assignee' => true, 'user_ids' => [], 'channels' => ['IN_APP', 'EMAIL']],
                ['threshold' => 120, 'assignee' => true, 'user_ids' => [], 'channels' => ['IN_APP', 'EMAIL']],
            ],
        ];

        $validated = $configuration->validate($config, $organizationId, true);

        DB::transaction(function () use ($validated, $organizationId, $calendarId) {
            $policyId = DB::table('sla_policies')->insertGetId(['public_id' => (string) Str::uuid(), 'organization_id' => $organizationId,
                'code' => $validated['code'], 'name' => $validated['name'], 'calendar_id' => $calendarId,
                'draft_config' => json_encode($validated), 'publication_status' => 'ACTIVE', 'is_active' => true, 'version' => 1,
                'effective_from' => $validated['effective_from'], 'effective_to' => null, 'created_at' => now(), 'updated_at' => now()]);

            $versionId = DB::table('sla_policy_versions')->insertGetId(['sla_policy_id' => $policyId, 'organization_id' => $organizationId,
                'number' => 1, 'config' => json_encode($validated), 'published_at' => now()]);

            DB::table('sla_policies')->where('id', $policyId)->update(['published_version_id' => $versionId]);
        });
    }
}
