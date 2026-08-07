<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EmployeeCheckService;
use App\Services\SmsGatewayService;
use App\Services\SsoTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Yangi xodim uchun pochta (AD) ochish jarayoni.
 *
 * Hozircha faqat birinchi bosqich: telefon raqamini kiritish va SMS kod bilan
 * tasdiqlash. Qolgan qadamlar (shaxsni aniqlash, AD user yaratish, parol
 * generatsiya qilish) keyinroq qo'shiladi.
 */
class AdAccountController extends Controller
{
    private const CODE_TTL_MINUTES = 1;

    public function sendCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?998[0-9]{9}$/'],
        ]);

        $phone = $this->normalizePhone((string) $validated['phone']);

        // Hali amal qilayotgan va haqiqatda yuborilgan kod bo'lsa — qayta
        // generatsiya qilinmaydi. SMS yuborilmagan (sent_at=null) yozuv
        // bloklamaydi — qayta urinish mumkin.
        $existing = DB::table('sms_codes')
            ->where('phone', $phone)
            ->whereNull('verified_at')
            ->whereNotNull('sent_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'SMS allaqachon yuborilgan. Kelingan kodni kiriting yoki birozdan so\'ng qayta urinib ko\'ring.',
                'phone' => $phone,
                'already_sent' => true,
                // Joriy kod qachon tugaydi (unix sekund) — shundan keyin qayta yuborish mumkin
                'resend_after' => (int) strtotime($existing->expires_at),
            ]);
        }

        $code = (string) random_int(10000, 99999);
        $requestId = (string) Str::uuid();

        DB::table('sms_codes')->insert([
            'phone' => $phone,
            'code' => $code,
            'request_id' => $requestId,
            'template_id' => (string) config('services.sms.template_id'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // SMS Gateway orqali real SMS yuborish (SSO tokeni bilan).
        $sent = app(SmsGatewayService::class)->send($this->smsPhone($phone), $code, $requestId);

        if ($sent) {
            DB::table('sms_codes')
                ->where('phone', $phone)
                ->where('request_id', $requestId)
                ->update(['sent_at' => now(), 'updated_at' => now()]);
        } else {
            return response()->json([
                'message' => 'SMS yuborilmadi. Xizmat hozircha mavjud emas, birozdan so\'ng qayta urinib ko\'ring.',
                'phone' => $phone,
            ], 502);
        }

        return response()->json([
            'message' => 'SMS yuborildi. Telefoningizga kelgan 5 xonali kodni kiriting.',
            'phone' => $phone,
        ]);
    }

    /**
     * AD akkaunt ochish oynasi ochilganda chaqiriladi — SSO tokenni
     * oldindan olib keshlaydi, SMS yuborish paytida kutish kerak bo'lmaydi.
     */
    public function prepare(Request $request): JsonResponse
    {
        try {
            $token = app(SsoTokenService::class)->token();

            return response()->json([
                'status' => 'ready',
                'token_cached' => true,
                'token_prefix' => substr($token, 0, 10).'...',
            ]);
        } catch (\Throwable $e) {
            Log::warning('[AD_ACCOUNT] SSO tokenni oldindan olishda xatolik', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'unavailable',
                'message' => 'SMS xizmati hozircha tayyor emas. Birozdan so\'ng qayta urinib ko\'ring.',
            ], 502);
        }
    }

    /**
     * PINFL va BXM kodi orqali xodimni tekshiradi. Telefon raqami avval
     * SMS orqali tasdiqlangan bo'lishi shart. To'g'ri bo'lsa xodim ma'lumotlari
     * va yaratiladigan pochta qaytariladi.
     */
    public function checkEmployee(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?998[0-9]{9}$/'],
            'pinfl' => ['required', 'string', 'size:14', 'regex:/^[0-9]{14}$/'],
            'bxm_code' => ['required', 'string', 'max:20'],
        ]);

        $phone = $this->normalizePhone((string) $validated['phone']);
        $pinfl = (string) $validated['pinfl'];
        $bxmCode = ltrim((string) $validated['bxm_code'], '0');

        // Telefon SMS orqali tasdiqlangan bo'lishi kerak
        $verified = DB::table('sms_codes')
            ->where('phone', $phone)
            ->whereNotNull('verified_at')
            ->latest('id')
            ->first();

        if (! $verified) {
            return response()->json(['message' => 'Telefon raqam avval SMS orqali tasdiqlanmagan.'], 422);
        }

        $employee = app(EmployeeCheckService::class)->findByPinfl($pinfl);

        if (! $employee) {
            return response()->json(['message' => 'Xodim topilmadi. PINFL raqamni tekshirib ko\'ring.'], 422);
        }

        // Telefon raqamini solishtirish
        if ($employee['phone'] !== null) {
            $employeePhone = ltrim($employee['phone'], '+');
            $enteredPhone = ltrim($phone, '+');

            if ($employeePhone !== $enteredPhone) {
                return response()->json([
                    'message' => 'Telefon raqam mos kelmadi. Tizimda boshqa raqam ko\'rsatilgan.',
                ], 422);
            }
        }

        // BXM kodini solishtirish
        if ($employee['bxm_code'] !== null && $employee['bxm_code'] !== $bxmCode) {
            return response()->json([
                'message' => 'BXM kodi mos kelmadi. Tizimda ko\'rsatilgan BXM kodni tekshiring.',
            ], 422);
        }

        $email = $employee['email'] ?? null;

        if (! $email) {
            $email = $this->generateEmail($employee);
        }

        return response()->json([
            'message' => 'Xodim tasdiqlandi. Pochta yaratilmoqda.',
            'employee' => $employee,
            'email' => $email,
        ]);
    }

    /**
     * Xodim ism-familiyasidan pochta manzilini yaratadi (API email bermasa).
     */
    private function generateEmail(array $employee): string
    {
        $first = $this->translit((string) ($employee['first_name'] ?? ''));
        $last = $this->translit((string) ($employee['last_name'] ?? ''));
        $domain = env('AD_EMAIL_DOMAIN', 'xb.uz');

        if ($first === '' || $last === '') {
            return '';
        }

        return strtolower($first . '.' . $last . '@' . $domain);
    }

    private function translit(string $text): string
    {
        $map = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
            'е' => 'e', 'ё' => 'e', 'ж' => 'j', 'з' => 'z', 'и' => 'i',
            'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
            'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
            'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'ts', 'ч' => 'ch',
            'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'i', 'ь' => '',
            'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
            'ў' => 'o', 'қ' => 'q', 'ғ' => 'g', 'ҳ' => 'h', 'ж' => 'j',
            'А' => 'a', 'Б' => 'b', 'В' => 'v', 'Г' => 'g', 'Д' => 'd',
            'Е' => 'e', 'Ё' => 'e', 'Ж' => 'j', 'З' => 'z', 'И' => 'i',
            'Й' => 'y', 'К' => 'k', 'Л' => 'l', 'М' => 'm', 'Н' => 'n',
            'О' => 'o', 'П' => 'p', 'Р' => 'r', 'С' => 's', 'Т' => 't',
            'У' => 'u', 'Ф' => 'f', 'Х' => 'h', 'Ц' => 'ts', 'Ч' => 'ch',
            'Ш' => 'sh', 'Щ' => 'sch', 'Ъ' => '', 'Ы' => 'i', 'Ь' => '',
            'Э' => 'e', 'Ю' => 'yu', 'Я' => 'ya',
            'Ў' => 'o', 'Қ' => 'q', 'Ғ' => 'g', 'Ҳ' => 'h',
            'Ə' => 'a', 'ə' => 'a', 'I' => 'i', 'i' => 'i', 'O' => 'o', 'o' => 'o',
            'U' => 'u', 'u' => 'u', 'G' => 'g', 'g' => 'g',
        ];

        return strtr($text, $map);
    }

    public function verifyCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?998[0-9]{9}$/'],
            'code' => ['required', 'string', 'size:5'],
        ]);
        $phone = $this->normalizePhone((string) $validated['phone']);
        $code = (string) $validated['code'];

        $record = DB::table('sms_codes')
            ->where('phone', $phone)
            ->whereNull('verified_at')
            ->latest('id')
            ->first();

        if (! $record) {
            return response()->json(['message' => 'Kod topilmadi. Avval SMS yuboring.'], 422);
        }

        if ($record->attempts >= 5) {
            return response()->json(['message' => 'Urinishlar soni oshib ketdi. Yangi SMS yuboring.'], 422);
        }

        if ($record->expires_at && now()->gt($record->expires_at)) {
            return response()->json(['message' => 'Kod muddati tugagan. Yangi SMS yuboring.'], 422);
        }

        if (! hash_equals($record->code, $code)) {
            DB::table('sms_codes')
                ->where('id', $record->id)
                ->update(['attempts' => $record->attempts + 1, 'updated_at' => now()]);

            return response()->json(['message' => 'Kod noto\'g\'ri. Qayta tekshirib ko\'ring.'], 422);
        }

        DB::table('sms_codes')
            ->where('id', $record->id)
            ->update(['verified_at' => now(), 'updated_at' => now()]);

        return response()->json([
            'message' => 'Telefon raqamingiz muvaffaqiyatli tasdiqlandi!',
            'phone' => $phone,
        ]);
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        return strlen($digits) === 9 ? '+998' . $digits : '+' . $digits;
    }

    /**
     * SMS API uchun raqam formati — "998" bilan boshida, "+" belgisisiz.
     * Misol: +998901234567 → 998901234567
     */
    private function smsPhone(string $phone): string
    {
        return ltrim($phone, '+');
    }
}
