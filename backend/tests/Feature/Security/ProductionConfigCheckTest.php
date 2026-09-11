<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * Deploy oldidan konfiguratsiya tekshiruvi [M-01].
 *
 * `APP_DEBUG=true` bilan deploy qilingan Laravel xato sahifasida kod,
 * konfiguratsiya va ba'zan credential ko'rsatadi; o'chirilgan TLS tekshiruvi
 * esa MITM'ga yo'l qoldiradi. Bu buyruq shu qiymatlarni deploy'dan oldin
 * ushlaydi, ya'ni xato productionda emas, konveyerda ko'rinadi.
 */
final class ProductionConfigCheckTest extends TestCase
{
    private const GOOD = [
        'app.env' => 'production',
        'app.debug' => false,
        'app.key' => 'base64:'.'x',
        'app.url' => 'https://taskera.xb.uz',
        'sanctum.expiration' => 720,
        'services.sso.verify_ssl' => true,
        'services.sms.verify_ssl' => true,
        'services.finesse.verify_tls' => true,
    ];

    public function test_it_passes_when_production_values_are_correct(): void
    {
        config(self::GOOD);
        putenv('FRONTEND_URL=https://taskera.xb.uz');

        $this->artisan('deploy:check')->assertSuccessful();
    }

    /**
     * @return array<string, array{0: string, 1: mixed}>
     */
    public static function badValues(): array
    {
        return [
            'debug yoqilgan' => ['app.debug', true],
            'env production emas' => ['app.env', 'local'],
            'kalit yo\'q' => ['app.key', ''],
            'url localhost' => ['app.url', 'http://localhost'],
            'token muddatsiz' => ['sanctum.expiration', null],
            'sso tls o\'chirilgan' => ['services.sso.verify_ssl', false],
            'sms tls o\'chirilgan' => ['services.sms.verify_ssl', false],
            'finesse tls o\'chirilgan' => ['services.finesse.verify_tls', false],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badValues')]
    public function test_it_fails_on_an_unsafe_value(string $key, mixed $value): void
    {
        config(self::GOOD);
        putenv('FRONTEND_URL=https://taskera.xb.uz');
        config([$key => $value]);

        $this->artisan('deploy:check')->assertFailed();
    }

    public function test_it_fails_when_frontend_url_is_not_set(): void
    {
        config(self::GOOD);
        putenv('FRONTEND_URL');

        $this->artisan('deploy:check')->assertFailed();
    }

    protected function tearDown(): void
    {
        putenv('FRONTEND_URL');
        parent::tearDown();
    }
}
