<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Deploy oldidan production konfiguratsiyasini tekshiradi [M-01].
 *
 * Ishlatilishi: deploy konveyerida `php artisan deploy:check` — nolga teng
 * bo'lmagan chiqish kodi deploy'ni to'xtatadi. Maqsad: `APP_DEBUG=true` yoki
 * o'chirilgan TLS tekshiruvi bilan chiqib ketishning oldini olish.
 */
final class DeployCheckCommand extends Command
{
    protected $signature = 'deploy:check';

    protected $description = 'Production konfiguratsiyasini deploy oldidan tekshiradi';

    public function handle(): int
    {
        $problems = [];

        if (config('app.env') !== 'production') {
            $problems[] = 'APP_ENV production emas (hozir: '.var_export(config('app.env'), true).')';
        }

        if (config('app.debug') !== false) {
            $problems[] = 'APP_DEBUG yoqilgan — xato sahifasi kod va konfiguratsiyani ko\'rsatadi';
        }

        if (trim((string) config('app.key')) === '') {
            $problems[] = 'APP_KEY bo\'sh — sessiya va shifrlangan maydonlar ishlamaydi';
        }

        $appUrl = (string) config('app.url');
        if ($appUrl === '' || str_contains($appUrl, 'localhost') || ! str_starts_with($appUrl, 'https://')) {
            $problems[] = 'APP_URL haqiqiy https manzil bo\'lishi kerak (hozir: '.($appUrl ?: 'bo\'sh').')';
        }

        // cors.php buni default `http://localhost:5173` bilan o'qiydi — ya'ni
        // belgilanmagani jimgina noto'g'ri CORS beradi, xato bermaydi.
        if (trim((string) env('FRONTEND_URL', '')) === '') {
            $problems[] = 'FRONTEND_URL belgilanmagan — CORS localhost defaultiga tushadi';
        }

        if (! is_int(config('sanctum.expiration')) || config('sanctum.expiration') <= 0) {
            $problems[] = 'SANCTUM_EXPIRATION belgilanmagan — API tokeni muddatsiz';
        }

        $tlsChecks = [
            'services.sso.verify_ssl' => 'SSO_VERIFY_SSL',
            'services.sms.verify_ssl' => 'SMS_VERIFY_SSL',
            'services.finesse.verify_tls' => 'FINESSE_VERIFY_TLS',
        ];

        foreach ($tlsChecks as $key => $envName) {
            if (config($key) !== true) {
                $problems[] = $envName.' o\'chirilgan — sertifikat tekshirilmaydi, MITM mumkin';
            }
        }

        if ($problems === []) {
            $this->info('Production konfiguratsiyasi joyida.');

            return self::SUCCESS;
        }

        $this->error('Deploy to\'xtatildi — '.count($problems).' ta muammo:');
        foreach ($problems as $problem) {
            $this->line('  • '.$problem);
        }

        return self::FAILURE;
    }
}
