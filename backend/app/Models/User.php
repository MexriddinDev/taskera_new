<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'public_id',
        'organization_id',
        'employee_id',
        'username',
        'email',
        'password',
        'auth_source',
        'status',
        'image',
        'locale_id',
        'timezone_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function employee()
    {
        return $this->belongsTo(\App\Modules\Organization\Infrastructure\Eloquent\Employee::class);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    private bool $_roleChecked = false;
    private ?object $_cachedRole = null;
    private ?array $_cachedRoles = null;
    private ?array $_cachedPermissions = null;

    /**
     * Bo'lim admini rolini aniqlash uchun kalit so'zlar (normallashtirilgan o'zak ko'rinishida).
     * Rol "bo'lim admini" hisoblanishi uchun nomida IKKALA ro'yxatdan ham so'z bo'lishi shart —
     * shunda "Department Viewer" (birlik bor, lavozim yo'q) yoki "Account Manager"
     * (lavozim bor, birlik yo'q) kabi nomlar noto'g'ri admin sanalmaydi.
     */
    private const DEPARTMENT_ADMIN_UNIT_WORDS = ['department', 'dept', 'bolim'];

    private const DEPARTMENT_ADMIN_TITLE_WORDS = ['admin', 'manager', 'boshlig', 'boshliq', 'rahbar', 'mudir'];

    /**
     * Foydalanuvchining BARCHA rollari (nomlari bilan).
     *
     * Bir foydalanuvchida bir nechta rol bo'lishi mumkin, shuning uchun rol nomiga
     * asoslangan tekshiruvlar shu ro'yxat bo'ylab yuritiladi — bitta "tasodifiy"
     * qator emas.
     */
    public function getRoles(): array
    {
        if ($this->_cachedRoles !== null) {
            return $this->_cachedRoles;
        }

        $roleIds = DB::table('model_has_roles')
            ->where('model_id', $this->id)
            ->pluck('role_id')
            ->all();

        $this->_cachedRoles = empty($roleIds)
            ? []
            : DB::table('roles')->whereIn('id', $roleIds)->orderBy('id')->get()->all();

        return $this->_cachedRoles;
    }

    /**
     * @return string[] normallashtirilgan rol nomlari
     */
    public function getRoleNames(): array
    {
        return array_map(
            fn ($role) => self::normalizeRoleName((string) ($role->name ?? '')),
            $this->getRoles()
        );
    }

    /**
     * Rol nomini solishtirish uchun bir ko'rinishga keltiradi:
     * kichik harf, apostroflar olib tashlanadi, ortiqcha bo'shliqlar siqiladi.
     */
    private static function normalizeRoleName(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = str_replace(["'", "‘", "’", "ʻ", "ʼ", '`'], '', $name);

        return (string) preg_replace('/\s+/u', ' ', $name);
    }

    /**
     * Ko'rsatish uchun asosiy rol (masalan UserResource dagi yorliq).
     *
     * Bir nechta rol bo'lsa eng kichik id — ya'ni eng imtiyozli rol (Super Admin = 1)
     * qaytariladi. Tartib aniq: avvalgi tartibsiz ->first() natijasi DB qator
     * tartibiga bog'liq bo'lib qolgan edi.
     */
    public function getRole()
    {
        if ($this->_roleChecked) {
            return $this->_cachedRole;
        }

        $roles = $this->getRoles();
        $this->_cachedRole = $roles[0] ?? null;
        $this->_roleChecked = true;

        return $this->_cachedRole;
    }

    /**
     * Check if user is Super Admin
     */
    public function isSuperAdmin(): bool
    {
        if (strtolower((string) $this->username) === 'superadmin') {
            return true;
        }

        return in_array('super admin', $this->getRoleNames(), true);
    }

    /**
     * Check if user is Department Admin / Manager.
     *
     * Rol nomida birlik so'zi (department/bo'lim) VA lavozim so'zi (admin/manager/...)
     * birga kelgan bo'lsa admin sanaladi. Ilgari xom str_contains ishlatilar edi —
     * "Department Viewer" yoki "Account Manager" ham admin bo'lib qolardi.
     */
    public function isDepartmentAdmin(): bool
    {
        $explicit = self::configuredDepartmentAdminRoleNames();

        foreach ($this->getRoleNames() as $name) {
            if ($name === '') {
                continue;
            }

            // Bazadagi rollar erkin nomlanadi ("IT Manager", "Menejer" — birlik
            // so'zisiz). Ular qoidaga tushmasa, config orqali aniq ko'rsatiladi,
            // shunda qoidani kuchaytirish jimgina huquqdan mahrum qilmaydi.
            if (in_array($name, $explicit, true)) {
                return true;
            }

            $hasUnit = false;
            foreach (self::DEPARTMENT_ADMIN_UNIT_WORDS as $word) {
                if (str_contains($name, $word)) {
                    $hasUnit = true;
                    break;
                }
            }

            if (! $hasUnit) {
                continue;
            }

            foreach (self::DEPARTMENT_ADMIN_TITLE_WORDS as $word) {
                if (str_contains($name, $word)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * config/rbac.php dagi "bo'lim admini" rol nomlari (normallashtirilgan).
     *
     * @return string[]
     */
    private static function configuredDepartmentAdminRoleNames(): array
    {
        $configured = config('rbac.department_admin_roles', []);

        if (! is_array($configured)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn ($name) => self::normalizeRoleName((string) $name), $configured),
            static fn (string $name) => $name !== ''
        ));
    }

    /**
     * Zayavkalar navbatini ko'ra oladigan / ular ustida ishlay oladigan xodimmi.
     *
     * Bu shart ilgari 6+ joyda so'zma-so'z takrorlangan edi (web controllerlar,
     * Kanban, Telegram bot, bildirishnomalar). Endi yagona manba shu metod.
     */
    public function isSupportStaff(): bool
    {
        return $this->isSuperAdmin()
            || $this->isDepartmentAdmin()
            || $this->hasPermission('tickets.view')
            || $this->hasPermission('tickets.assign')
            || $this->hasPermission('tickets.transition');
    }

    /**
     * Egasiz zayavkani O'ZIGA olish (navbatdan ish olish).
     *
     * Bu zayavka ustida ishlashning bir qismi, shuning uchun ishlash huquqi
     * (`tickets.transition`) yetarli: support xodimi boshqa birovga biriktira
     * olmasa ham, o'z ishini navbatdan ola bilishi kerak.
     *
     * `tickets.view` ATAYLAB hisobga olinmaydi: u faqat KO'RISH huquqi.
     */
    public function canTakeTickets(): bool
    {
        return $this->canAssignTickets()
            || $this->hasPermission('tickets.transition');
    }

    /**
     * Zayavkani BOSHQA xodimga biriktirish (yoki sherigining ishini o'ziga
     * olish) huquqi — dispetcherlik amali.
     *
     * Ilgari biriktirish ham, holat o'zgartirish ham isSupportStaff() ga
     * tayanardi — natijada navbatni ko'rish uchun berilgan huquq jimgina
     * amallarni ham ochib qo'yardi va RBAC dagi `tickets.assign` /
     * `tickets.transition` katakchalari amalda hech narsani hal qilmasdi.
     */
    public function canAssignTickets(): bool
    {
        return $this->isSuperAdmin()
            || $this->isDepartmentAdmin()
            || $this->hasPermission('tickets.assign');
    }

    /**
     * Zayavka holatini o'zgartira oladimi (jarayonga o'tkazish, yakunlash, rad etish).
     */
    public function canTransitionTickets(): bool
    {
        return $this->isSuperAdmin()
            || $this->isDepartmentAdmin()
            || $this->hasPermission('tickets.transition');
    }

    /**
     * Bitta foydalanuvchining huquqlar keshini bekor qiladi.
     *
     * Rol yoki to'g'ridan-to'g'ri huquq o'zgargan HAR BIR joyda chaqirilishi shart —
     * aks holda `permission:` middleware eski (keshlangan) huquqlar bilan ishlab qoladi.
     */
    public static function forgetPermissionsCache(int $userId): void
    {
        \Illuminate\Support\Facades\Cache::forget("user_permissions_{$userId}");
    }

    /**
     * Rolga biriktirilgan BARCHA foydalanuvchilarning huquqlar keshini bekor qiladi.
     *
     * Rolning huquqlari tahrirlanganda yoki rol o'chirilganda chaqiriladi: huquq
     * olib tashlangan zahoti uning barcha egalari uchun kuchga kiradi.
     */
    public static function forgetPermissionsCacheForRole(int $roleId): void
    {
        $userIds = DB::table('model_has_roles')
            ->where('role_id', $roleId)
            ->pluck('model_id')
            ->all();

        foreach ($userIds as $userId) {
            self::forgetPermissionsCache((int) $userId);
        }
    }

    /**
     * Huquq (permission) o'zgarganda unga bog'liq barcha foydalanuvchilar keshini bekor qiladi:
     * rol orqali olganlar ham, to'g'ridan-to'g'ri berilganlar ham.
     */
    public static function forgetPermissionsCacheForPermission(int $permissionId): void
    {
        $roleIds = DB::table('role_has_permissions')
            ->where('permission_id', $permissionId)
            ->pluck('role_id')
            ->all();

        foreach ($roleIds as $roleId) {
            self::forgetPermissionsCacheForRole((int) $roleId);
        }

        $userIds = DB::table('model_has_permissions')
            ->where('permission_id', $permissionId)
            ->pluck('model_id')
            ->all();

        foreach ($userIds as $userId) {
            self::forgetPermissionsCache((int) $userId);
        }
    }

    public function clearPermissionsCache(): void
    {
        $this->_cachedPermissions = null;
        $this->_cachedRoles = null;
        $this->_cachedRole = null;
        $this->_roleChecked = false;
        self::forgetPermissionsCache((int) $this->id);
    }

    /**
     * Get all permission names assigned to this user (via role + direct)
     */
    public function getAllPermissions(): array
    {
        if ($this->_cachedPermissions !== null) {
            return $this->_cachedPermissions;
        }

        $userId = $this->id;
        $this->_cachedPermissions = \Illuminate\Support\Facades\Cache::remember("user_permissions_{$userId}", 600, function () use ($userId) {
            $roleIds = DB::table('model_has_roles')
                ->where('model_id', $userId)
                ->pluck('role_id')
                ->toArray();

            $rolePermissions = [];
            if (!empty($roleIds)) {
                $rolePermissions = DB::table('role_has_permissions')
                    ->join('permissions', 'role_has_permissions.permission_id', '=', 'permissions.id')
                    ->whereIn('role_has_permissions.role_id', $roleIds)
                    ->pluck('permissions.name')
                    ->toArray();
            }

            $directPermissions = DB::table('model_has_permissions')
                ->join('permissions', 'model_has_permissions.permission_id', '=', 'permissions.id')
                ->where('model_has_permissions.model_id', $userId)
                ->pluck('permissions.name')
                ->toArray();

            return array_values(array_unique(array_merge($rolePermissions, $directPermissions)));
        });

        return $this->_cachedPermissions;
    }

    /**
     * Check dynamic permission
     */
    public function hasPermission(string $permissionName): bool
    {
        if ($this->isSuperAdmin()) {
            return true; // Super Admin has all dynamic permissions
        }

        $allPermissions = $this->getAllPermissions();
        if (empty($allPermissions)) {
            return in_array($permissionName, ['tickets.view_own', 'tickets.create']);
        }

        return in_array($permissionName, $allPermissions);
    }

    /**
     * Get query for tickets accessible to this user based on role:
     * - Super Admin: ALL tickets
     * - Dept Admin: Tickets in their department
     * - Regular User / No Role: Only their own tickets
     */
    public function getAccessibleTicketsQuery()
    {
        $query = DB::table('tickets')->whereNull('deleted_at');

        if ($this->isSuperAdmin()) {
            return $query; // Full access
        }

        if ($this->isDepartmentAdmin()) {
            // Get employee department
            $employee = DB::table('employees')->where('id', $this->employee_id)->first();
            $deptId = $employee ? $employee->department_id : 1;
            return $query->where('department_id', $deptId);
        }

        // Standard User / No Role: Minimum access (only own tickets)
        return $query->where('requester_user_id', $this->id);
    }
}
