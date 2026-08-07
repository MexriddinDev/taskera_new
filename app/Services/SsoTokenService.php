<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SSO (sso-test.xb.uz) dan client_credentials granti orqali xizmat tokenni
 * olish va keshda saqlash.
 *
 * Kesh muddati SSO ning expires_in qiymatiga bog'lanadi (xavfsizlik uchun
 * biroz oldinroq yangilanadi). Agar SSO expires_in bermasa, fallback TTL
 * ishlatiladi (default 120 daqiqa — SSO_TOKEN_TTL_MINUTES orqali sozlanadi).
 */
class SsoTokenService
{
    private const CACHE_KEY = 'sso_access_token';

    private const CACHE_EXPIRY_KEY = 'sso_access_token_expires_at';

    private string $url;

    private string $clientId;

    private string $clientSecret;

    private string $scope;

    private int $fallbackTtlMinutes;

    private int $bufferSeconds;

    public function __construct()
    {
        $this->url = rtrim((string) config('services.sso.url'), '/');
        $this->clientId = (string) config('services.sso.client_id');
        $this->clientSecret = (string) config('services.sso.client_secret');
        $this->scope = (string) config('services.sso.scope');
        $this->fallbackTtlMinutes = (int) config('services.sso.token_ttl_minutes');
        $this->bufferSeconds = (int) config('services.sso.token_buffer_seconds');
    }

    /**
     * Amaldagi SSO tokenni qaytaradi. Keshda amal qilayotgan token bo'lmasa,
     * SSO dan yangi token olib, keshlaydi.
     *
     * @throws \RuntimeException SSO token olib bo'lmasa
     */
    public function token(): string
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $request = Http::timeout(15)
            ->asForm()
            ->withBasicAuth($this->clientId, $this->clientSecret)
            ->acceptJson();

        if (config('services.sso.bypass_proxy')) {
            $request->withOptions(['proxy' => '']);
        }

        $response = $request->post($this->url.'/api/oauth2/token', [
            'grant_type' => 'client_credentials',
            'scope' => $this->scope,
        ]);

        if (! $response->successful()) {
            Log::error('[SSO] Token olish muvaffaqiyatsiz', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException('SSO token olish muvaffaqiyatsiz (HTTP '.$response->status().')');
        }

        $data = $response->json();

        $token = (string) ($data['access_token'] ?? '');
        if ($token === '') {
            throw new \RuntimeException('SSO javobida access_token topilmadi');
        }

        // Kesh muddati = SSO expires_in (sekund) - buffer.
        // SSO expires_in bermasa fallback TTL (default 2 soat).
        $expiresIn = (int) ($data['expires_in'] ?? ($this->fallbackTtlMinutes * 60));
        $ttl = max(30, $expiresIn - $this->bufferSeconds);

        Cache::put(self::CACHE_KEY, $token, $ttl);
        Cache::put(self::CACHE_EXPIRY_KEY, now()->addSeconds($ttl)->timestamp, $ttl);

        Log::info('[SSO] Token olindi va keshlanadi', [
            'expires_in' => $expiresIn,
            'cache_ttl' => $ttl,
            'scope' => $this->scope,
        ]);

        return $token;
    }

    /**
     * Keshni bo'shatadi (masalan, token muddati tugagani ma'lum bo'lsa).
     */
    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::CACHE_EXPIRY_KEY);
    }

    /**
     * Token keshda amal qilayotgan bo'lsa true qaytaradi.
     */
    public function cached(): bool
    {
        $token = Cache::get(self::CACHE_KEY);

        return is_string($token) && $token !== '';
    }
}
