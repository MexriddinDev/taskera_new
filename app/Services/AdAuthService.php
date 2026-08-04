<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Active Directory (LDAP) orqali foydalanuvchi autentifikatsiyasi.
 *
 * PHP built-in ldap_* funksiyalaridan foydalanadi —
 * qo'shimcha composer paket talab qilmaydi (faqat php8.5-ldap kerak).
 */
class AdAuthService
{
    private string $host;

    private int $port;

    private string $baseDn;

    private string $serviceUser;

    private string $servicePass;

    public function __construct()
    {
        $this->host = (string) config('services.ad.host');
        $this->port = (int) config('services.ad.port');
        $this->baseDn = (string) config('services.ad.base_dn');
        $this->serviceUser = (string) config('services.ad.service_user');
        $this->servicePass = (string) config('services.ad.service_pass');

        // AD LDAP Signing/Channel Binding muammosini hal qilish uchun
        // Windows Server 2019+ SASL signing talab qiladi — bu env o'zgaruvchisi
        // libldap ga sertifikat tekshirishni o'chirishni buyuradi.
        putenv('LDAPTLS_REQCERT=never');
        putenv('LDAPSASL_CBINDING=none');
    }

    /**
     * Foydalanuvchini AD orqali autentifikatsiya qiladi.
     *
     * @return array|null Muvaffaqiyatli bo'lsa AD atributlari, noto'g'ri login/parol bo'lsa null
     *
     * @throws \RuntimeException AD serverga ulanib bo'lmasa (server down, timeout)
     */
    public function authenticate(string $username, string $password): ?array
    {
        $conn = $this->connect();

        // 1. Service account bilan bog'lanish (foydalanuvchini qidirish uchun)
        $this->bindService($conn);

        // 2. sAMAccountName bo'yicha foydalanuvchini qidirish
        $entry = $this->findUserEntry($conn, $username);
        if (! $entry) {
            @ldap_unbind($conn);

            return null;
        }
        $userDn = $entry['dn'];

        // 3. Foydalanuvchi paroli bilan bind (haqiqiy autentifikatsiya)
        if (! @ldap_bind($conn, $userDn, $password)) {
            @ldap_unbind($conn);
            Log::info("AD: Parol noto'g'ri: {$username}");

            return null; // Parol noto'g'ri
        }

        @ldap_unbind($conn);

        return $this->formatUser($entry, $username);
    }

    /**
     * Foydalanuvchini AD dan service account orqali qidiradi (parolsiz).
     * Login talab qilmaydi — zayafka yaratishda guruh/bo'limni jonli olish uchun.
     *
     * @return array|null Foydalanuvchi topilsa AD atributlari, aks holda null
     *
     * @throws \RuntimeException AD serverga ulanib bo'lmasa
     */
    public function lookupByUsername(string $username): ?array
    {
        $conn = $this->connect();
        $this->bindService($conn);

        $entry = $this->findUserEntry($conn, $username);
        @ldap_unbind($conn);

        return $entry ? $this->formatUser($entry, $username) : null;
    }

    /**
     * AD serverga service account bilan ulanishni tekshiradi.
     * Artisan ldap:ping buyrug'i tomonidan ishlatiladi.
     *
     * @return array{ok: bool, error: string} Muvaffaqiyat → ok=true
     */
    public function ping(): array
    {
        try {
            putenv('LDAPTLS_REQCERT=never');
            $scheme = ($this->port === 636) ? 'ldaps' : 'ldap';
            $conn = @ldap_connect("{$scheme}://{$this->host}:{$this->port}");
            if (! $conn) {
                return ['ok' => false, 'error' => 'ldap_connect muvaffaqiyatsiz'];
            }
            ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($conn, LDAP_OPT_NETWORK_TIMEOUT, 3);
            $ok = @ldap_bind($conn, $this->serviceUser, $this->servicePass);
            $error = $ok ? '' : ldap_error($conn);
            @ldap_unbind($conn);

            return ['ok' => (bool) $ok, 'error' => $error];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    // ── Yordamchi metodlar ────────────────────────────────────────────────────

    private function connect()
    {
        // Port 636 → ldaps://, 389 → ldap://
        $scheme = ($this->port === 636) ? 'ldaps' : 'ldap';
        $conn = @ldap_connect("{$scheme}://{$this->host}:{$this->port}");

        if (! $conn) {
            throw new \RuntimeException('AD serverga ulanib bo\'lmadi');
        }

        ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($conn, LDAP_OPT_NETWORK_TIMEOUT, (int) config('services.ad.timeout'));
        // TLS sertifikat tekshirishni o'chirish (self-signed AD sertifikati uchun)
        ldap_set_option($conn, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);

        return $conn;
    }

    private function bindService($conn): void
    {
        if (! @ldap_bind($conn, $this->serviceUser, $this->servicePass)) {
            $err = ldap_error($conn);
            @ldap_unbind($conn);
            Log::error('AD: Service account bind muvaffaqiyatsiz', [
                'user' => $this->serviceUser,
                'error' => $err,
            ]);
            throw new \RuntimeException("AD xizmat akkaunti bind muvaffaqiyatsiz: {$err}");
        }
    }

    private function findUserEntry($conn, string $username): ?array
    {
        $filter = '(sAMAccountName='.ldap_escape($username, '', LDAP_ESCAPE_FILTER).')';
        $attrs = [
            'dn', 'samaccountname', 'mail', 'givenname', 'sn', 'middlename',
            'telephonenumber', 'department', 'title',
            'objectguid', 'memberof', 'useraccountcontrol', 'displayname',
        ];

        $search = @ldap_search($conn, $this->baseDn, $filter, $attrs, 0, 1);

        if (! $search) {
            Log::warning("AD: ldap_search muvaffaqiyatsiz: {$username}", ['error' => ldap_error($conn)]);

            return null;
        }

        $entries = ldap_get_entries($conn, $search);

        if ((int) $entries['count'] === 0) {
            Log::info("AD: Foydalanuvchi topilmadi: {$username}");

            return null; // AD da bunday username yo'q
        }

        return $entries[0];
    }

    private function formatUser(array $entry, string $username): array
    {
        // Akkaunt holati (ACCOUNTDISABLE = bit 2)
        $uac = (int) ($entry['useraccountcontrol'][0] ?? 512);
        $enabled = ! ($uac & 2);

        // objectGUID (16-bayt binary little-endian) → UUID string
        $rawGuid = $entry['objectguid'][0] ?? null;
        $guid = $rawGuid ? $this->binaryGuidToString($rawGuid) : null;

        // memberOf guruhlar ro'yxati
        $memberOf = [];
        if (isset($entry['memberof']['count'])) {
            for ($i = 0; $i < (int) $entry['memberof']['count']; $i++) {
                $memberOf[] = $entry['memberof'][$i];
            }
        }

        return [
            'username' => strtolower((string) ($entry['samaccountname'][0] ?? $username)),
            'email' => $entry['mail'][0] ?? null,
            'first_name' => $entry['givenname'][0] ?? null,
            'last_name' => $entry['sn'][0] ?? null,
            'middle_name' => $entry['middlename'][0] ?? null,
            'phone' => $entry['telephonenumber'][0] ?? null,
            'department' => $entry['department'][0] ?? null,
            'title' => $entry['title'][0] ?? null,
            'object_guid' => $guid,
            'member_of' => $memberOf,
            'enabled' => $enabled,
            'display_name' => $entry['displayname'][0] ?? null,
        ];
    }

    /**
     * Active Directory objectGUID (16-bayt little-endian binary) → UUID string formatga o'tkazish.
     * AD da objectGUID little-endian saqlanadi, UUID big-endian bo'ladi.
     */
    private function binaryGuidToString(string $bin): string
    {
        if (strlen($bin) !== 16) {
            return bin2hex($bin); // kutilmagan uzunlik — hexstring qaytaramiz
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
}
