<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Exchange (pochta) uchun AD foydalanuvchi + pochta qutisi yaratish.
 *
 * Exchange server: adatum.com (172.28.2.161, LDAPS 636).
 * Login AD (172.28.5.100) dan butunlay alohida.
 *
 * Oqim:
 *  1. Xodimga tegishli akkaunt mavjudligi tekshiriladi (email / ism-familiya)
 *  2. Username (isim.familiya) generatsiya qilinadi, band bo'lsa suffix qo'shiladi
 *  3. ldap_add bilan user yaratiladi (unicodePwd, mail, proxyAddresses, ...)
 *  4. Department nomi bilan guruhga a'zo qilinadi (yo'q bo'lsa yaratiladi)
 *  5. Pochta qutisi ochiladi (LDAP msExch atributlari yoki helper API)
 */
class ExchangeMailService
{
    private string $host;

    private int $port;

    private string $baseDn;

    private string $serviceUser;

    private string $servicePass;

    private string $domain;

    private string $upnDomain;

    public function __construct()
    {
        $this->host = (string) config('services.exchange.host');
        $this->port = (int) config('services.exchange.port');
        $this->baseDn = (string) config('services.exchange.base_dn');
        $this->serviceUser = (string) config('services.exchange.service_user');
        $this->servicePass = (string) config('services.exchange.service_pass');
        $this->domain = (string) config('services.exchange.email_domain');
        $this->upnDomain = (string) config('services.exchange.upn_domain');

        ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);
        putenv('LDAPTLS_REQCERT=never');
        putenv('LDAPSASL_CBINDING=none');
    }

    // ── Asosiy oqim ─────────────────────────────────────────────────────────

    /**
     * Xodimni employeeID (PINFL) bo'yicha Exchange AD'dan qidiradi.
     *
     * Bu "xodimga pochta yaratilganmi" degan savolga javob beradi:
     *  - topilsa → pochta (AD akkaunt) allaqachon ochilgan
     *  - topilmasa → hali yaratilmagan
     *
     * @return array{dn: string, username: string, email: string, ou: string}|null
     */
    public function findByPinfl(string $pinfl): ?array
    {
        $conn = $this->connect();
        $this->bindService($conn);

        try {
            $filter = '(employeeID='.ldap_escape($pinfl, '', LDAP_ESCAPE_FILTER).')';
            $attrs = ['dn', 'samaccountname', 'mail'];
            $search = @ldap_search($conn, $this->baseDn, $filter, $attrs, 0, 5);
            if (! $search) {
                return null;
            }
            $entries = ldap_get_entries($conn, $search);
            if ((int) $entries['count'] === 0) {
                return null;
            }

            return [
                'dn' => (string) $entries[0]['dn'],
                'username' => strtolower((string) ($entries[0]['samaccountname'][0] ?? '')),
                'email' => $entries[0]['mail'][0] ?? null,
                'ou' => $this->dnParent((string) $entries[0]['dn']),
            ];
        } finally {
            @ldap_unbind($conn);
        }
    }

    /**
     * Xodimga tegishli akkauntning joriy BXM kodini qaytaradi (employeeID bo'yicha).
     *
     * 1) physicalDeliveryOfficeName atributidan o'qiladi (create() da yoziladi)
     * 2) bo'sh bo'lsa — akkaunt OU'si bxm_ou_map config bilan teskari xaritalanadi
     */
    public function getBxmCodeByPinfl(string $pinfl): ?string
    {
        $conn = $this->connect();
        $this->bindService($conn);

        try {
            $filter = '(employeeID='.ldap_escape($pinfl, '', LDAP_ESCAPE_FILTER).')';
            $search = @ldap_search($conn, $this->baseDn, $filter, ['dn', 'physicalDeliveryOfficeName'], 0, 5);
            if (! $search) {
                return null;
            }
            $entries = ldap_get_entries($conn, $search);
            if ((int) $entries['count'] === 0) {
                return null;
            }

            $bxm = (string) ($entries[0]['physicaldeliveryofficename'][0] ?? '');
            if ($bxm !== '') {
                return $bxm;
            }

            // Eski akkauntlar (attribut yozilmagan) — OU orqali teskari xarita
            $ou = strtolower($this->dnParent((string) $entries[0]['dn']));
            foreach ((array) config('services.exchange.bxm_ou_map', []) as $code => $mappedOu) {
                if (strtolower((string) $mappedOu) === $ou) {
                    return (string) $code;
                }
            }

            return null;
        } finally {
            @ldap_unbind($conn);
        }
    }

    /**
     * Xodimga tegishli akkauntning parolini almashtiradi (employeeID bo'yicha).
     *
     * @return array{username: string, email: string, password: string, dn: string}
     *
     * @throws \RuntimeException topilmasa yoki parol o'rnatilmagan bo'lsa
     */
    public function resetPassword(string $pinfl, string $newPassword): array
    {
        $conn = $this->connect();
        $this->bindService($conn);

        try {
            $filter = '(employeeID='.ldap_escape($pinfl, '', LDAP_ESCAPE_FILTER).')';
            $attrs = ['dn', 'samaccountname', 'mail'];
            $search = @ldap_search($conn, $this->baseDn, $filter, $attrs, 0, 5);
            if (! $search) {
                throw new \RuntimeException('Exchange (AD) da xodim qidiruvda xatolik: '.ldap_error($conn));
            }
            $entries = ldap_get_entries($conn, $search);
            if ((int) $entries['count'] === 0) {
                throw new \RuntimeException('Xodim uchun Exchange (AD) da akkaunt topilmadi');
            }

            $dn = (string) $entries[0]['dn'];

            // Parolni alohida o'rnatamiz — pwdLastSet bilan BIRGA berilsa
            // AD parolni qabul qilmaydi (bind keyin ishlamaydi).
            // pwdLastSet=0 qo'yilmaydi: bu flag OWA (web pochta) loginini
            // bloklaydi ("data 773" → "incorrect username or password"),
            // chunki Exchange'da ChangePasswordEnabled sozlamasi yoqilmagan.
            if (! @ldap_mod_replace($conn, $dn, ['unicodePwd' => $this->encodeUnicodePwd($newPassword)])) {
                throw new \RuntimeException('Parolni almashtirishda xatolik (kod '.ldap_errno($conn).'): '.ldap_error($conn));
            }

            return [
                'username' => strtolower((string) ($entries[0]['samaccountname'][0] ?? '')),
                'email' => $entries[0]['mail'][0] ?? null,
                'password' => $newPassword,
                'dn' => $dn,
            ];
        } finally {
            @ldap_unbind($conn);
        }
    }

    /**
     * Xodim akkauntini boshqa BXM (OU) ga ko'chiradi — "pochtani boshqa
     * BXM ga biriktirish". User AD'da boshqa OU'ga moddn bilan o'tkaziladi.
     *
     * @return array{dn: string, ou: string}
     *
     * @throws \RuntimeException topilmasa yoki ko'chirishda xatolik bo'lsa
     */
    public function moveToOu(string $pinfl, string $newBxmCode): array
    {
        $conn = $this->connect();
        $this->bindService($conn);

        try {
            $filter = '(employeeID='.ldap_escape($pinfl, '', LDAP_ESCAPE_FILTER).')';
            $search = @ldap_search($conn, $this->baseDn, $filter, ['dn', 'cn'], 0, 5);
            if (! $search) {
                throw new \RuntimeException('Exchange (AD) da xodim qidiruvda xatolik: '.ldap_error($conn));
            }
            $entries = ldap_get_entries($conn, $search);
            if ((int) $entries['count'] === 0) {
                throw new \RuntimeException('Xodim uchun Exchange (AD) da akkaunt topilmadi');
            }

            $oldDn = (string) $entries[0]['dn'];
            $cn = (string) ($entries[0]['cn'][0] ?? $this->dnRdnValue($oldDn));
            $rdn = 'CN='.ldap_escape($cn, '', LDAP_ESCAPE_DN);
            $newOu = $this->resolveOu($newBxmCode);
            $newDn = $rdn.','.$newOu;

            // Ko'chirish (rename): RDN bir xil, faqat parent (OU) o'zgaradi
            if (! @ldap_rename($conn, $oldDn, $rdn, $newOu, true)) {
                throw new \RuntimeException('Akkauntni boshqa BXM ga ko\'chirishda xatolik (kod '.ldap_errno($conn).'): '.ldap_error($conn));
            }

            // BXM kod attributini ham yangilaymiz — rotatsiya aniqlash uchun
            if ($newBxmCode !== '') {
                @ldap_mod_replace($conn, $newDn, ['physicalDeliveryOfficeName' => $newBxmCode]);
            }

            Log::info('[EXCHANGE] Xodim boshqa BXM ga ko\'chirildi', [
                'pinfl' => $pinfl,
                'old_dn' => $oldDn,
                'new_dn' => $newDn,
                'bxm' => $newBxmCode,
            ]);

            return ['dn' => $newDn, 'ou' => $newOu];
        } finally {
            @ldap_unbind($conn);
        }
    }

    /**
     * Xodim uchun Exchange akkauntini yaratadi.
     *
     * @param array $employee EmployeeCheckService normalize natijasi
     * @param string $bxmCode tasdiqlangan BXM kodi
     *
     * @return array{username: string, email: string, password: string, dn: string, ou: string, group_dn: ?string, object_guid: ?string}
     *
     * @throws \RuntimeException yaratishda xatolik bo'lsa (mavjud akkaunt, LDAP xato va h.k.)
     */
    public function create(array $employee, string $bxmCode): array
    {
        $conn = $this->connect();
        $this->bindService($conn);

        try {
            $email = $this->buildEmail($employee);
            $personExists = $this->findPersonAccount($conn, $employee, $email);
            if ($personExists) {
                throw new \RuntimeException('Ushbu xodim uchun pochta (AD) akkaunti allaqachon ochilgan: '.$personExists);
            }

            $username = $this->generateUsername($conn, $employee);
            $password = $this->generatePassword();
            $ou = $this->resolveOu($bxmCode);

            // "Familiya Ism" ko'rinishidagi CN (displayName bilan bir xil)
            $displayName = trim(($employee['last_name'] ?? '').' '.($employee['first_name'] ?? ''));
            // AD attribut chegaralari: cn/displayName/sn/givenName/department/title — 64 belgi
            $displayName = $this->max64($displayName);
            $sn = $this->max64((string) ($employee['last_name'] ?? ''));
            $givenName = $this->max64((string) ($employee['first_name'] ?? ''));
            $department = $this->max64(trim((string) ($employee['department'] ?? '')));
            $title = $this->max64(trim((string) ($employee['position'] ?? '')));
            $telephone = $this->sanitizePhone((string) ($employee['phone'] ?? ''));

            $userDn = 'CN='.ldap_escape($displayName, '', LDAP_ESCAPE_DN).','.$ou;

            $attributes = [
                'objectClass' => ['top', 'person', 'organizationalPerson', 'user'],
                'cn' => $displayName,
                'sn' => $sn,
                'givenName' => $givenName,
                'displayName' => $displayName,
                'sAMAccountName' => $username,
                'userPrincipalName' => $username.'@'.$this->upnDomain,
                'mail' => $email,
                'mailNickname' => $username,
                'proxyAddresses' => ['SMTP:'.$email],
                'unicodePwd' => $this->encodeUnicodePwd($password),
                'userAccountControl' => '512', // NORMAL_ACCOUNT (faol)
            ];

            // PINFL (JShShIR) employeeID atributiga saqlanadi — keyinchalik
            // "xodimga pochta yaratilganmi" tekshiruvi shu orqali qilinadi.
            $pinfl = (string) ($employee['pinfl'] ?? '');
            if ($pinfl !== '') {
                $attributes['employeeID'] = $pinfl;
                // PINFL AD Users and Computers'da "P.O. Box" (postOfficeBox)
                // maydonida ham ko'rinib turadi.
                $attributes['postOfficeBox'] = $pinfl;
            }

            // BXM kodi physicalDeliveryOfficeName atributiga saqlanadi —
            // rotatsiyani aniqlash (API BXM ≠ Exchange BXM) shu orqali qilinadi.
            if ($bxmCode !== '') {
                $attributes['physicalDeliveryOfficeName'] = $bxmCode;
            }

            // Bo'sh bo'lgan ixtiyoriy attributlarni yubormaymiz —
            // bo'sh string AD'da "Invalid syntax" (21) xatosini beradi
            if ($department !== '') {
                $attributes['department'] = $department;
            }
            if ($title !== '') {
                $attributes['title'] = $title;
            }
            if ($telephone !== '') {
                $attributes['telephoneNumber'] = $telephone;
            }

            if (! @ldap_add($conn, $userDn, $attributes)) {
                throw new \RuntimeException(
                    'AD user yaratishda xatolik (kod '.ldap_errno($conn).'): '.ldap_error($conn)
                );
            }

            // pwdLastSet=0 qo'yilmaydi: bu flag OWA (web pochta) loginini
            // bloklaydi ("data 773" → "incorrect username or password"),
            // chunki Exchange'da ChangePasswordEnabled sozlamasi yoqilmagan.

            $objectGuid = null;
            try {
                $objectGuid = $this->readObjectGuid($conn, $userDn);
            } catch (\Throwable $e) {
                Log::warning('[EXCHANGE] objectGUID o\'qilmadi', ['dn' => $userDn, 'error' => $e->getMessage()]);
            }

            // ── Guruhga qo'shish (department nomi bo'yicha) ──────────────────
            $groupDn = $this->addToDepartmentGroup($conn, $employee, $userDn);

            // ── Pochta qutisini ochish ───────────────────────────────────────
            $this->provisionMailbox($conn, $employee, $userDn, $username, $email);

            @ldap_unbind($conn);

            return [
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'dn' => $userDn,
                'ou' => $ou,
                'group_dn' => $groupDn,
                'object_guid' => $objectGuid,
            ];
        } catch (\Throwable $e) {
            @ldap_unbind($conn);

            throw $e;
        }
    }

    /**
     * Xodimga tegishli akkaunt qidirish:
     *  1) email (isim.familiya@domain) bo'yicha
     *  2) givenName + sn (ism-familiya) mosligi bo'yicha
     */
    private function findPersonAccount($conn, array $employee, string $email): ?string
    {
        $filters = [
            '(|(mail='.ldap_escape($email, '', LDAP_ESCAPE_FILTER).')(userPrincipalName='.ldap_escape($email, '', LDAP_ESCAPE_FILTER).'))',
        ];

        $first = (string) ($employee['first_name'] ?? '');
        $last = (string) ($employee['last_name'] ?? '');
        if ($first !== '' && $last !== '') {
            $filters[] = '(&(givenName='.ldap_escape($first, '', LDAP_ESCAPE_FILTER).')(sn='.ldap_escape($last, '', LDAP_ESCAPE_FILTER).'))';
        }

        foreach ($filters as $filter) {
            $search = @ldap_search($conn, $this->baseDn, $filter, ['dn'], 0, 5);
            if (! $search) {
                continue;
            }
            $entries = ldap_get_entries($conn, $search);
            if ((int) $entries['count'] > 0) {
                return (string) $entries[0]['dn'];
            }
        }

        return null;
    }

    /**
     * "isim.familiya" — band bo'lsa "-2", "-3" va h.k. qo'shiladi.
     * sAMAccountName maksimum 20 belgi.
     */
    private function generateUsername($conn, array $employee): string
    {
        $first = $this->translit((string) ($employee['first_name'] ?? ''));
        $last = $this->translit((string) ($employee['last_name'] ?? ''));
        $base = strtolower($first.'.'.$last);

        $username = $base;
        $i = 2;
        while ($this->usernameExists($conn, $username)) {
            $suffix = '-'.$i;
            $username = substr($base, 0, 20 - strlen($suffix)).$suffix;
            $i++;
        }

        return $username;
    }

    private function usernameExists($conn, string $username): bool
    {
        $filter = '(sAMAccountName='.ldap_escape($username, '', LDAP_ESCAPE_FILTER).')';
        $search = @ldap_search($conn, $this->baseDn, $filter, ['dn'], 0, 1);
        if (! $search) {
            return true; // xatolik bo'lsa xavfsiz tomonda qolamiz
        }
        $entries = ldap_get_entries($conn, $search);

        return (int) $entries['count'] > 0;
    }

    private function resolveOu(string $bxmCode): string
    {
        $map = (array) config('services.exchange.bxm_ou_map', []);

        return (string) ($map[$bxmCode] ?? config('services.exchange.default_ou'));
    }

    /**
     * Department nomi bo'yicha guruhga a'zo qiladi.
     * Guruh topilmasa va auto_create_groups=true bo'lsa — yaratiladi.
     */
    private function addToDepartmentGroup($conn, array $employee, string $userDn): ?string
    {
        // Guruh nomi ham AD cn chegarasi (64) bilan mos ravishda kesiladi
        $department = $this->max64((string) ($employee['department'] ?? ''));
        if ($department === '') {
            return null;
        }

        $groupDn = $this->findDepartmentGroup($conn, $department);

        if (! $groupDn && config('services.exchange.auto_create_groups')) {
            $groupDn = $this->createDepartmentGroup($conn, $department);
        }

        if (! $groupDn) {
            Log::warning('[EXCHANGE] Guruh topilmadi va yaratilmadi', ['department' => $department]);

            return null;
        }

        // A'zolikni qo'shish (user allaqachon a'zo bo'lsa — duplicate chiqmaydi)
        if (! @ldap_mod_add($conn, $groupDn, ['member' => [$userDn]])) {
            Log::warning('[EXCHANGE] Guruhga qo\'shishda xatolik', [
                'group' => $groupDn,
                'user' => $userDn,
                'error' => ldap_error($conn),
            ]);
        }

        return $groupDn;
    }

    /**
     * Department nomiga mos guruh qidiradi (normalize qilingan cn solishtirish).
     */
    private function findDepartmentGroup($conn, string $department): ?string
    {
        $search = @ldap_search($conn, $this->baseDn, '(objectClass=group)', ['dn', 'cn'], 0, 0);
        if (! $search) {
            return null;
        }

        $entries = ldap_get_entries($conn, $search);
        $needle = $this->normalizeName($department);

        for ($i = 0; $i < (int) $entries['count']; $i++) {
            $cn = $entries[$i]['cn'][0] ?? '';
            if ($this->normalizeName((string) $cn) === $needle) {
                return (string) $entries[$i]['dn'];
            }
        }

        return null;
    }

    /**
     * Department nomi bilan guruh yaratadi (topilmaganda).
     * CN maksimum 64 belgi, sAMAccountName maksimum 20.
     */
    private function createDepartmentGroup($conn, string $department): ?string
    {
        $ou = (string) config('services.exchange.groups_ou');
        $cn = mb_substr(trim($department), 0, 64);

        // sAMAccountName: translit + alfanumerik, band bo'lsa suffix
        $slug = strtoupper(substr(preg_replace('/[^A-Za-z0-9]+/', '', $this->translit($cn)) ?? '', 0, 17));
        $sam = $slug === '' ? 'GRP' : $slug;
        $i = 2;
        while ($this->groupNameExists($conn, $sam)) {
            $sam = substr($slug === '' ? 'GRP' : $slug, 0, 17).'-'.($i++);
        }

        $groupDn = 'CN='.ldap_escape($cn, '', LDAP_ESCAPE_DN).','.$ou;

        $ok = @ldap_add($conn, $groupDn, [
            'objectClass' => ['top', 'group'],
            'cn' => $cn,
            'sAMAccountName' => $sam,
            'groupType' => '2147483650', // SECURITY_GLOBAL
        ]);

        if (! $ok) {
            Log::error('[EXCHANGE] Guruh yaratilmadi', ['cn' => $cn, 'error' => ldap_error($conn)]);

            return null;
        }

        Log::info('[EXCHANGE] Guruh yaratildi', ['dn' => $groupDn]);

        return $groupDn;
    }

    private function groupNameExists($conn, string $sam): bool
    {
        $filter = '(sAMAccountName='.ldap_escape($sam, '', LDAP_ESCAPE_FILTER).')';
        $search = @ldap_search($conn, $this->baseDn, $filter, ['dn'], 0, 1);
        if (! $search) {
            return true;
        }
        $entries = ldap_get_entries($conn, $search);

        return (int) $entries['count'] > 0;
    }

    /**
     * Pochta qutisini ochish.
     *
     * 1) Helper API sozlangan bo'lsa (New-Mailbox PowerShell xizmati) — unga POST
     * 2) Aks holda LDAP msExch atributlari (homeMDB, msExchMailboxGuid, ...)
     */
    private function provisionMailbox($conn, array $employee, string $userDn, string $username, string $email): void
    {
        $apiUrl = (string) config('services.exchange.mailbox_api_url');

        if ($apiUrl !== '') {
            $this->provisionViaApi($apiUrl, $employee, $username, $email);

            return;
        }

        $this->provisionViaLdap($conn, $employee, $userDn, $username, $email);
    }

    /**
     * Helper API orqali pochta qutisi (PowerShell New-Mailbox xizmati).
     */
    private function provisionViaApi(string $apiUrl, array $employee, string $username, string $email): void
    {
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(60)
                ->acceptJson()
                ->post($apiUrl, [
                    'username' => $username,
                    'email' => $email,
                    'display_name' => trim(($employee['last_name'] ?? '').' '.($employee['first_name'] ?? '')),
                    'ou' => null,
                ]);

            if (! $response->successful()) {
                throw new \RuntimeException('Pochta qutisi xizmati xatolik qaytardi: '.$response->status().' '.substr($response->body(), 0, 200));
            }
        } catch (\Throwable $e) {
            throw new \RuntimeException('Pochta qutisi xizmatiga ulanishda xatolik: '.$e->getMessage());
        }
    }

    /**
     * LDAP orqali pochta qutisi atributlarini o'rnatish.
     * homeMDB, msExchHomeServerName va legacyExchangeDN namuna (Administrator)
     * akkauntidan jonli o'qiladi — qiymatlar har doim shu domenga mos bo'ladi.
     */
    private function provisionViaLdap($conn, array $employee, string $userDn, string $username, string $email): void
    {
        // Namuna akkauntdan Exchange konfiguratsiyasini olish
        $sample = $this->findSampleMailbox($conn);
        if (! $sample) {
            Log::warning('[EXCHANGE] Pochta qutisi uchun namuna akkaunt topilmadi — atributlar o\'rnatilmadi');

            return;
        }

        $mailboxGuid = $this->randomGuidBytes();

        // legacyExchangeDN: /o=FirstOrganization/ou=Exchange Administrative Group
        // (FYDIBOHF23SPDLT)/cn=Recipients/cn={guid}-{FirstName}
        $legacyPrefix = preg_replace('#/cn=Recipients/cn=.*$#', '/cn=Recipients', (string) $sample['legacyExchangeDN']);
        $legacyExchangeDn = $legacyPrefix.'/cn='.strtolower(bin2hex($mailboxGuid)).'-'.($employee['first_name'] ?? '');

        $mods = [
            'homeMDB' => $sample['homeMDB'],
            'msExchHomeServerName' => $sample['msExchHomeServerName'],
            'msExchMailboxGuid' => $mailboxGuid,
            'msExchRecipientTypeDetails' => '1', // UserMailbox
            'msExchVersion' => $sample['msExchVersion'],
            'legacyExchangeDN' => $legacyExchangeDn,
            'msExchRecipientDisplayType' => '-2147483646', // RemoteUserMailbox? yo'q — oddiy UserMailbox uchun kerak emas
        ];
        unset($mods['msExchRecipientDisplayType']);

        if (! @ldap_modify($conn, $userDn, $mods)) {
            Log::error('[EXCHANGE] Pochta qutisi atributlari o\'rnatilmadi', [
                'dn' => $userDn,
                'error' => ldap_error($conn),
            ]);

            throw new \RuntimeException('Pochta qutisi atributlarini o\'rnatishda xatolik: '.ldap_error($conn));
        }
    }

    /**
     * Pochta qutisi ochilgan namuna akkaunt (masalan Administrator) dan
     * Exchange atributlarini o'qiydi.
     */
    private function findSampleMailbox($conn): ?array
    {
        $attrs = ['homeMDB', 'msExchHomeServerName', 'msExchMailboxGuid', 'msExchVersion', 'legacyExchangeDN'];

        // 1) Administrator (homeMDB borligi aniq tekshirilgan)
        $filter = '(&(objectClass=user)(sAMAccountName=Administrator))';
        $search = @ldap_search($conn, $this->baseDn, $filter, $attrs, 0, 1);
        if ($search) {
            $entries = ldap_get_entries($conn, $search);
            if ((int) $entries['count'] > 0 && ! empty($entries[0]['homemdb'][0])) {
                return $this->extractSample($entries[0]);
            }
        }

        // 2) Umumiy: homeMDB bor istalgan user
        $filter = '(&(objectClass=user)(homeMDB=*)(!(sAMAccountName=Administrator)))';
        $search = @ldap_search($conn, $this->baseDn, $filter, $attrs, 0, 1);
        if (! $search) {
            return null;
        }
        $entries = ldap_get_entries($conn, $search);

        return (int) $entries['count'] > 0 ? $this->extractSample($entries[0]) : null;
    }

    private function extractSample(array $entry): array
    {
        return [
            'homeMDB' => $entry['homemdb'][0] ?? null,
            'msExchHomeServerName' => $entry['msexchhomeservername'][0] ?? null,
            'msExchVersion' => $entry['msexchversion'][0] ?? null,
            'legacyExchangeDN' => $entry['legacyexchangedn'][0] ?? null,
        ];
    }

    private function readObjectGuid($conn, string $dn): ?string
    {
        $search = @ldap_read($conn, $dn, '(objectClass=*)', ['objectGUID']);
        if (! $search) {
            return null;
        }
        $entries = ldap_get_entries($conn, $search);
        $raw = $entries[0]['objectguid'][0] ?? null;

        return $raw ? $this->binaryGuidToString($raw) : null;
    }

    /**
     * DN dan parent (OU/container) qismini ajratadi.
     * "CN=User,OU=Headoffice,DC=adatum,DC=com" → "OU=Headoffice,DC=adatum,DC=com"
     */
    private function dnParent(string $dn): string
    {
        $pos = strpos($dn, ',');
        if ($pos === false) {
            return $dn;
        }

        return substr($dn, $pos + 1);
    }

    /**
     * DN dan RDN qiymatini ajratadi.
     * "CN=Nuriddinov Mexriddin,OU=..." → "Nuriddinov Mexriddin"
     */
    private function dnRdnValue(string $dn): string
    {
        $rdn = explode(',', $dn)[0] ?? '';

        return preg_replace('/^CN=/i', '', $rdn) ?? '';
    }

    // ── Umumiy yordamchilar ─────────────────────────────────────────────────

    public function buildEmail(array $employee): string
    {
        $first = $this->translit((string) ($employee['first_name'] ?? ''));
        $last = $this->translit((string) ($employee['last_name'] ?? ''));

        return strtolower($first.'.'.$last.'@'.$this->domain);
    }

    /**
     * AD complexity talabiga mos parol, QAT'IY formada: AAzz+123
     * — 2 katta harf, 2 kichik harf, "+" belgisi, 3 raqam (jami 8 belgi).
     *
     * Belgi doim "+": boshqa maxsus belgilar ishlatilmaydi. Tartib ham
     * aralashtirilmaydi — parol xodimga telefon orqali aytib beriladi,
     * shaklni oldindan bilish uni yozib olishni osonlashtiradi.
     * Chalkashadigan belgilar (O/0, I/l/1) alifbodan chiqarib tashlangan.
     */
    public function generatePassword(): string
    {
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower = 'abcdefghijkmnopqrstuvwxyz';
        $digits = '23456789';

        $pick = static fn (string $set): string => $set[random_int(0, strlen($set) - 1)];

        return $pick($upper).$pick($upper)
            .$pick($lower).$pick($lower)
            .'+'
            .$pick($digits).$pick($digits).$pick($digits);
    }

    /**
     * unicodePwd uchun UTF-16LE, tirnoqli qiymat (faqat LDAPS orqali ishlaydi).
     */
    private function encodeUnicodePwd(string $password): string
    {
        return iconv('UTF-8', 'UTF-16LE', '"'.$password.'"');
    }

    private function randomGuidBytes(): string
    {
        return random_bytes(16);
    }

    public function translit(string $text): string
    {
        $map = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
            'е' => 'e', 'ё' => 'e', 'ж' => 'j', 'з' => 'z', 'и' => 'i',
            'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
            'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch',
            'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'i', 'ь' => '',
            'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
            'ў' => 'o', 'қ' => 'q', 'ғ' => 'g', 'ҳ' => 'h',
            'А' => 'a', 'Б' => 'b', 'В' => 'v', 'Г' => 'g', 'Д' => 'd',
            'Е' => 'e', 'Ё' => 'e', 'Ж' => 'j', 'З' => 'z', 'И' => 'i',
            'Й' => 'y', 'К' => 'k', 'Л' => 'l', 'М' => 'm', 'Н' => 'n',
            'О' => 'o', 'П' => 'p', 'Р' => 'r', 'С' => 's', 'Т' => 't',
            'У' => 'u', 'Ф' => 'f', 'Х' => 'h', 'Ц' => 'ts', 'Ч' => 'ch',
            'Ш' => 'sh', 'Щ' => 'sch', 'Ъ' => '', 'Ы' => 'i', 'Ь' => '',
            'Э' => 'e', 'Ю' => 'yu', 'Я' => 'ya',
            'Ў' => 'o', 'Қ' => 'q', 'Ғ' => 'g', 'Ҳ' => 'h',
        ];

        return strtr($text, $map);
    }

    private function normalizeName(string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $name) ?? ''));
    }

    /**
     * AD attributlari uchun maksimum uzunlikka kesish (64 belgi).
     * Undan uzun qiymatlar "Constraint violation" (19) xatosini beradi.
     */
    private function max64(string $value): string
    {
        return mb_substr(trim($value), 0, 64);
    }

    /**
     * Telefon raqamni AD telephoneNumber (PrintableString) uchun
     * xavfsiz formatga keltiradi — faqat raqamlar va '+' qoldiriladi.
     */
    private function sanitizePhone(string $phone): string
    {
        $phone = trim($phone);
        if ($phone === '') {
            return '';
        }

        $clean = preg_replace('/[^0-9+]/', '', $phone) ?? '';

        return mb_substr($clean, 0, 64);
    }

    /**
     * Active Directory objectGUID (16-bayt little-endian binary) → UUID string.
     */
    private function binaryGuidToString(string $bin): string
    {
        if (strlen($bin) !== 16) {
            return bin2hex($bin);
        }

        $h = bin2hex($bin);

        return sprintf(
            '%s%s%s%s-%s%s-%s%s-%s%s-%s',
            $h[6].$h[7], $h[4].$h[5], $h[2].$h[3], $h[0].$h[1],
            $h[10].$h[11], $h[8].$h[9],
            $h[14].$h[15], $h[12].$h[13],
            $h[16].$h[17], $h[18].$h[19],
            $h[20].$h[21].$h[22].$h[23].substr($h, 24)
        );
    }

    // ── LDAP ulanish ────────────────────────────────────────────────────────

    private function connect()
    {
        ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);
        putenv('LDAPTLS_REQCERT=never');
        putenv('LDAPSASL_CBINDING=none');

        $scheme = ($this->port === 636) ? 'ldaps' : 'ldap';
        $conn = @ldap_connect("{$scheme}://{$this->host}:{$this->port}");

        if (! $conn) {
            throw new \RuntimeException('Exchange (AD) serverga ulanib bo\'lmadi');
        }

        ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($conn, LDAP_OPT_NETWORK_TIMEOUT, (int) config('services.exchange.timeout'));

        return $conn;
    }

    private function bindService($conn): void
    {
        if (! @ldap_bind($conn, $this->serviceUser, $this->servicePass)) {
            $err = ldap_error($conn);
            @ldap_unbind($conn);
            Log::error('[EXCHANGE] Service bind muvaffaqiyatsiz', [
                'user' => $this->serviceUser,
                'error' => $err,
            ]);
            throw new \RuntimeException("Exchange xizmat akkaunti bind muvaffaqiyatsiz: {$err}");
        }
    }
}