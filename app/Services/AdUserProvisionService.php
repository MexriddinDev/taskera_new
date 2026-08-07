<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * AD dan kelgan ma'lumotlar asosida DB da Employee va User yaratadi/yangilaydi.
 *
 * Birinchi kirish: employee + user avtomatik yaratiladi (auth_source='AD').
 * Keyingi kirishlar: employee ma'lumotlari AD dan yangilanadi.
 * AD guruhlari (memberOf) asosida rol (ad_group_roles mappingi orqali) sinxronlanadi.
 */
class AdUserProvisionService
{
    /**
     * AD atributlari asosida mavjud User ni topadi yoki yangi yaratadi.
     * Employee kartochkasi ham yaratiladi/yangilanadi.
     */
    public function findOrProvision(array $ad): User
    {
        // ── Employee: objectGUID → email tartibida qidirish ──────────────────
        $employee = null;

        if (! empty($ad['object_guid'])) {
            $employee = DB::table('employees')
                ->where('ad_object_guid', $ad['object_guid'])
                ->first();
        }

        if (! $employee && ! empty($ad['email'])) {
            $employee = DB::table('employees')
                ->where('email', $ad['email'])
                ->first();
        }

        // Bo'lim va lavozimni nom bo'yicha topish
        $deptId = $this->resolveDepartmentId($ad['department'] ?? null);

        // department atributi bo'lmasa yoki mos kelmasa — AD guruhlaridan (memberOf) aniqlaymiz
        if (! $deptId) {
            $deptId = $this->resolveDepartmentFromGroups($ad['member_of'] ?? []);
        }

        $positionId = $this->resolvePositionId($ad['title'] ?? null);

        $firstName = $ad['first_name'] ?? ($ad['display_name'] ?? $ad['username']);
        $lastName = $ad['last_name'] ?? '';

        $empFields = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'middle_name' => $ad['middle_name'] ?? null,
            'email' => $ad['email'] ?? null,
            'phone' => $ad['phone'] ?? null,
            'ad_object_guid' => $ad['object_guid'] ?? null,
            'last_hr_sync_at' => now(),
            'updated_at' => now(),
        ];

        if ($employee) {
            // Mavjud employee — AD dan yangilash
            DB::table('employees')->where('id', $employee->id)->update(
                array_merge($empFields, [
                    'department_id' => $deptId ?? $employee->department_id,
                    'position_id' => $positionId ?? $employee->position_id,
                ])
            );
            $employeeId = $employee->id;
        } else {
            // Yangi employee — yaratish
            $employeeId = DB::table('employees')->insertGetId(
                array_merge($empFields, [
                    'public_id' => (string) Str::uuid(),
                    'organization_id' => 1,
                    'employee_no' => 'AD-'.strtoupper($ad['username']),
                    'department_id' => $deptId ?? 1,
                    'branch_id' => 1,
                    'position_id' => $positionId ?? 1,
                    'employment_status_id' => 1,
                    'created_at' => now(),
                ])
            );
        }

        // ── User: username bo'yicha qidirish ─────────────────────────────────
        $user = User::where('username', $ad['username'])->first();

        $userFields = [
            'employee_id' => $employeeId,
            'email' => $ad['email'] ?? null,
            'auth_source' => 'AD',
            'status' => ($ad['enabled'] ?? true) ? 'ACTIVE' : 'DISABLED',
        ];

        if (! $user) {
            // Yangi user — auth_source='AD', parol saqlanmaydi
            $user = User::create(array_merge($userFields, [
                'public_id' => (string) Str::uuid(),
                'organization_id' => 1,
                'username' => $ad['username'],
                'password' => null, // AD foydalanuvchilarda lokal parol yo'q
            ]));
        } else {
            // Mavjud user — ma'lumotlarni yangilash
            $user->update($userFields);
        }

        // ── Rolni AD guruhlariga qarab sinxronlash ───────────────────────────
        $this->syncRolesFromAdGroups($user, $ad);

        return $user->fresh(['employee.department']);
    }

    /**
     * AD memberOf guruhlarini ad_group_roles mappingi bilan solishtirib,
     * foydalanuvchi ro'llarini sinxronlaydi.
     *
     * - Mappingda bor guruhlar → rol qo'shiladi (agar yo'q bo'lsa)
     * - Avval berilgan, lekin endi guruhda bo'lmagan AD-rollar olib tashlanadi
     * - Qo'lda berilgan rollar (mappingda yo'q) o'zgartirilmaydi
     */
    private function syncRolesFromAdGroups(User $user, array $ad): void
    {
        $groupNames = collect($ad['member_of'] ?? [])
            ->map(fn ($dn) => $this->extractGroupName((string) $dn))
            ->filter()
            ->map(fn ($name) => strtolower($name))
            ->unique();

        $orgId = (int) ($user->organization_id ?? 1);
        $modelType = User::class;

        // Mapping bo'yicha hozirgi guruhlardan kelgan rol idlari
        $mappedRoleIds = DB::table('ad_group_roles')
            ->where('organization_id', $orgId)
            ->when($groupNames->isEmpty(), fn ($q) => $q->whereRaw('1 = 0'))
            ->whereIn(DB::raw('LOWER(ad_group_name)'), $groupNames->all())
            ->pluck('role_id')
            ->unique();

        // AD orqali boshqariladigan barcha rol idlari (nazorat uchun)
        $adManagedRoleIds = DB::table('ad_group_roles')
            ->where('organization_id', $orgId)
            ->pluck('role_id')
            ->unique();

        // Eski ro'llarni yangilash: endi qo'llanilmaydigan AD-rollarni olib tashlash
        DB::table('model_has_roles')
            ->where('model_type', $modelType)
            ->where('model_id', $user->id)
            ->where('organization_id', $orgId)
            ->whereIn('role_id', $adManagedRoleIds)
            ->when($mappedRoleIds->isNotEmpty(), fn ($q) => $q->whereNotIn('role_id', $mappedRoleIds))
            ->delete();

        // Yangi ro'llarni qo'shish
        foreach ($mappedRoleIds as $roleId) {
            DB::table('model_has_roles')->updateOrInsert(
                [
                    'role_id' => $roleId,
                    'model_type' => $modelType,
                    'model_id' => $user->id,
                    'organization_id' => $orgId,
                ],
                []
            );
        }
    }

    /**
     * LDAP DN dan guruh nomini ajratib olish: "CN=user,CN=Users,DC=adatum,DC=com" → "user"
     */
    private function extractGroupName(string $dn): ?string
    {
        foreach (explode(',', $dn) as $part) {
            $part = trim($part);
            if (str_starts_with(strtolower($part), 'cn=')) {
                return substr($part, 3);
            }
        }

        return $dn === '' ? null : $dn;
    }

    // ── Yordamchi metodlar ────────────────────────────────────────────────────

    /**
     * AD guruhlaridan (memberOf) bo'lim NOMINI aniqlaydi — faqat o'qish uchun,
     * hech narsa yaratmaydi. "x" prefiksli guruh nomi to'g'ridan-to'g'ri qaytariladi
     * ("xGitlab" → "Gitlab"), aks holda departments jadvalidagi nom bilan solishtiriladi.
     */
    public function resolveDepartmentNameFromAdGroups(array $memberOf): ?string
    {
        foreach ($memberOf as $dn) {
            $groupName = $this->extractGroupName((string) $dn);
            if (empty($groupName)) {
                continue;
            }

            $candidate = preg_replace('/^x/i', '', trim($groupName));
            if (empty($candidate) || in_array(strtolower($candidate), ['users', 'domain users'], true)) {
                continue;
            }

            $deptName = DB::table('departments')
                ->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($candidate).'%'])
                ->value('name');

            if ($deptName) {
                return (string) $deptName;
            }

            // "x" prefiksli guruh bo'lsa — prefikssiz nom bo'lim sifatida ko'rsatiladi
            if (preg_match('/^x/i', trim($groupName))) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Bo'lim nomini DB dagi departments jadvalidan topish.
     * AD dagi department nomi va DB dagi nom mos kelmasligi mumkin,
     * shu sababli LIKE qidiruvi ishlatiladi.
     */
    private function resolveDepartmentId(?string $name): ?int
    {
        if (empty($name)) {
            return null;
        }

        return DB::table('departments')
            ->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($name).'%'])
            ->value('id');
    }

    /**
     * AD memberOf guruhlaridan bo'limni aniqlash.
     * Guruh nomlari "x" prefiksi bilan keladi ("xIT" → "IT"),
     * shuning uchun prefiks olib tashlanib, departments jadvali bilan solishtiriladi.
     * Mos keladigan bo'lim topilmasa va guruh "x" prefiksli bo'lsa — yangi bo'lim yaratiladi
     * (builtin guruhlar: Administrators, Enterprise Admins va h.k. bo'lim emas).
     */
    private function resolveDepartmentFromGroups(array $memberOf): ?int
    {
        foreach ($memberOf as $dn) {
            $groupName = $this->extractGroupName((string) $dn);
            if (empty($groupName)) {
                continue;
            }

            $candidate = preg_replace('/^x/i', '', trim($groupName));
            if (empty($candidate) || in_array(strtolower($candidate), ['users', 'domain users'], true)) {
                continue;
            }

            $deptId = $this->resolveDepartmentId($candidate);
            if (! $deptId && preg_match('/^x/i', trim($groupName))) {
                $deptId = $this->createDepartment($candidate);
            }
            if ($deptId) {
                return $deptId;
            }
        }

        return null;
    }

    /**
     * departments jadvalida yo'q bo'limni AD guruh nomi bilan yaratadi.
     */
    private function createDepartment(string $name): ?int
    {
        $slug = strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '', $name));
        $base = $slug === '' ? 'AD' : 'AD-'.$slug;

        $code = $base;
        $i = 1;
        while (DB::table('departments')->where('organization_id', 1)->where('code', $code)->exists()) {
            $code = $base.'-'.(++$i);
        }

        return DB::table('departments')->insertGetId([
            'public_id' => (string) Str::uuid(),
            'organization_id' => 1,
            'branch_id' => 1,
            'code' => Str::limit($code, 32),
            'name' => $name,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Lavozim nomini DB dagi positions jadvalidan topish.
     */
    private function resolvePositionId(?string $title): ?int
    {
        if (empty($title)) {
            return null;
        }

        return DB::table('positions')
            ->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($title).'%'])
            ->value('id');
    }
}
