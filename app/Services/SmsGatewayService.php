<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SMS Gateway — SSO tokeni bilan sms-gateway scoped xizmatga SMS yuboradi.
 *
 * So'rov:
 *   POST {SMS_SEND_URL}
 *   Authorization: Bearer {SSO token}
 *   {
 *     "username": "xb_smsbanking",
 *     "password": "...",
 *     "templateId": "SYSTEM_VERIFY_CODE",
 *     "sms": {
 *       "phone": "+998...",
 *       "requestId": "uuid",
 *       "keys": { "smstemName": "TaskFlow", "code": "12345" }
 *     }
 *   }
 */
class SmsGatewayService
{
    public function __construct(private readonly SsoTokenService $ssoTokens) {}

    /**
     * Telefonga tasdiqlash kodini SMS orqali yuboradi.
     *
     * @param  string  $phone  +998XXXXXXXXX formatdagi raqam
     * @param  string  $code   tasdiqlash kodi
     * @param  string  $requestId  har bir SMS uchun noyob id (bazaga saqlanadi)
     * @return bool muvaffaqiyatli yuborilgan bo'lsa true
     */
    public function send(string $phone, string $code, string $requestId): bool
    {
        $sendUrl = (string) config('services.sms.send_url');

        if ($sendUrl === '') {
            Log::info("[SMS_GATEWAY] Yuborilmoqchi SMS (URL sozlanmagan, logga yozildi): {$phone} -> {$code}");

            return true;
        }

        try {
            $token = $this->ssoTokens->token();

            $request = Http::timeout(15)
                ->withToken($token)
                ->acceptJson();

            if (config('services.sso.bypass_proxy')) {
                $request->withOptions(['proxy' => '']);
            }

            $response = $request->post($sendUrl, [
                    'username' => (string) config('services.sms.username'),
                    'password' => (string) config('services.sms.password'),
                    'templateId' => (string) config('services.sms.template_id'),
                    'sms' => [
                        'phone' => $phone,
                        'requestId' => $requestId,
                        'keys' => [
                            'system_name' => (string) config('services.sms.from'),
                            'code' => $code,
                        ],
                    ],
                ]);

            if (! $response->successful()) {
                Log::error('[SMS_GATEWAY] SMS yuborish muvaffaqiyatsiz', [
                    'phone' => $phone,
                    'request_id' => $requestId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            Log::info("[SMS_GATEWAY] SMS yuborildi: {$phone}", [
                'request_id' => $requestId,
                'body' => $response->body(),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('[SMS_GATEWAY] SMS yuborishda xatolik', [
                'phone' => $phone,
                'request_id' => $requestId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
