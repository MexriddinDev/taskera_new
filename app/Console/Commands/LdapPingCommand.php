<?php

namespace App\Console\Commands;

use App\Services\AdAuthService;
use Illuminate\Console\Command;

/**
 * AD/LDAP server ulanishini va foydalanuvchi autentifikatsiyasini tekshirish.
 *
 * Ishlatilishi:
 *   php artisan ldap:ping                             — server ulanishini tekshiradi
 *   php artisan ldap:ping --user=johndoe --pass=Parol — foydalanuvchi autentifikatsiyasini tekshiradi
 */
class LdapPingCommand extends Command
{
    protected $signature = 'ldap:ping
                                {--user= : AD foydalanuvchi username (ixtiyoriy — autentifikatsiya testi)}
                                {--pass= : AD foydalanuvchi paroli (ixtiyoriy — autentifikatsiya testi)}';

    protected $description = 'AD/LDAP server ulanishini va (ixtiyoriy) foydalanuvchi autentifikatsiyasini tekshirish';

    public function handle(AdAuthService $adService): int
    {
        $this->newLine();
        $this->line('╔═══════════════════════════════════════════╗');
        $this->line('║       AD Server Ulanish Testi             ║');
        $this->line('╚═══════════════════════════════════════════╝');
        $this->line('  Host   : '.config('services.ad.host', '—'));
        $this->line('  Port   : '.config('services.ad.port', '—'));
        $this->line('  BaseDN : '.config('services.ad.base_dn', '—'));
        $this->line('  SvcAcc : '.config('services.ad.service_user', '—'));
        $this->newLine();

        // 1. Service account bilan server ulanishini tekshirish
        $this->line('Service account ulanishi tekshirilmoqda...');

        $result = $adService->ping();

        if ($result['ok']) {
            $this->info('✅ AD server bilan ulanish MUVAFFAQIYATLI!');
        } else {
            $this->error('❌ AD server bilan ulanib bo\'lmadi!');
            if (! empty($result['error'])) {
                $this->line('   Xato: '.$result['error']);
            }
            $this->line('   Tekshiring: host/port to\'g\'rimi, server yoniqmi, service account paroli to\'g\'rimi?');
            $this->line('   (Agar "integrity checking" xatosi chiqsa: DC da LDAPServerIntegrity=1 qiling va DC ni restart qiling)');

            return self::FAILURE;
        }

        // 2. Foydalanuvchi autentifikatsiya testi (ixtiyoriy)
        $testUser = $this->option('user');
        $testPass = $this->option('pass');

        if ($testUser && $testPass) {
            $this->newLine();
            $this->line("Foydalanuvchi autentifikatsiyasi tekshirilmoqda: {$testUser}");

            try {
                $result = $adService->authenticate($testUser, $testPass);
            } catch (\RuntimeException $e) {
                $this->error('❌ AD ulanish xatosi: '.$e->getMessage());

                return self::FAILURE;
            }

            if ($result) {
                $this->info('✅ Autentifikatsiya MUVAFFAQIYATLI!');
                $this->newLine();
                $this->table(
                    ['Atribut', 'Qiymat'],
                    [
                        ['Username',    $result['username']],
                        ['Email',       $result['email'] ?? '—'],
                        ['Ism',         $result['first_name'] ?? '—'],
                        ['Familiya',    $result['last_name'] ?? '—'],
                        ['Otasining ismi', $result['middle_name'] ?? '—'],
                        ['Telefon',     $result['phone'] ?? '—'],
                        ['Bo\'lim',     $result['department'] ?? '—'],
                        ['Lavozim',     $result['title'] ?? '—'],
                        ['AD GUID',     $result['object_guid'] ?? '—'],
                        ['Akkaunt faol?', $result['enabled'] ? 'HA ✅' : 'YO\'Q ❌'],
                    ]
                );
            } else {
                $this->error('❌ Autentifikatsiya muvaffaqiyatsiz (noto\'g\'ri login yoki parol)');

                return self::FAILURE;
            }
        } else {
            $this->newLine();
            $this->line('💡 Foydalanuvchi testini ham bajarish uchun:');
            $this->line('   php artisan ldap:ping --user=username --pass=parol');
        }

        $this->newLine();

        return self::SUCCESS;
    }
}
