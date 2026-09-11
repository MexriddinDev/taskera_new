<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EmployeeCheckService;
use App\Services\ExchangeMailService;
use App\Services\SmsGatewayService;
use App\Services\SsoTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Yangi xodim uchun pochta (AD) ochish jarayoni.
 *
 * Bosqichlar:
 *  1. PINFL kiritiladi → hr_emps (EmployeeCheckService) API orqali xodim
 *     tekshiriladi (mavjudligi, state/condition holati, ma'lumotlari to'liqligi).
 *  2. BXM kodi kiritiladi → API dagi bxm_code bilan solishtiriladi.
 *     Rotatsiya bo'lsa (API BXM ≠ Exchange BXM) — to'g'ri kod kiritilgach
 *     telefon bosqichiga o'tiladi, keyin boshqa BXM ga biriktirish taklifi.
 *  3. Telefon raqami kiritiladi → API dagi telefon bilan solishtiriladi,
 *     mos bo'lsa SMS yuboriladi, mos bo'lmasa bildirishnoma chiqariladi.
 *  4. SMS kodi tasdiqlanadi.
 *  5. Exchange bosqichi — ExchangeMailService orqali AD user + pochta qutisi
 *     avtomatik yaratiladi, login/parol ekranga chiqariladi.
 */
class AdAccountController extends Controller
{
    private const CODE_TTL_MINUTES = 3;

    /**
     * BXM kodni taqqoslash uchun normalize qiladi: "09006" va "9006" teng.
     * Saqlashda esa asl (filial) qiymati ishlatiladi.
     */
    private function normalizeBxmCode(string $code): string
    {
        return ltrim(trim($code), '0');
    }

    /**
     * PINFL bo'yicha xodimni tekshiradi (1-bosqich).
     *
     * API dan xodimni topadi, state/condition (faol ishlayotganligi) va
     * ma'lumotlar to'liqligini tekshiradi. Hammasi to'g'ri bo'lsa keyingi
     * bosqichga (BXM kod) o'tish mumkin.
     */
    public function checkEmployee(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pinfl' => ['required', 'string', 'size:14', 'regex:/^[0-9]{14}$/'],
        ]);

        $pinfl = (string) $validated['pinfl'];

        $employee = app(EmployeeCheckService::class)->findByPinfl($pinfl);

        if (! $employee) {
            return response()->json([
                'message' => 'Xodim topilmadi. PINFL (JShShIR) raqamni tekshirib ko\'ring.',
            ], 422);
        }

        // AD ochish uchun barcha ma'lumotlar to'liq va faol bo'lishi shart:
        // state = A, condition = "Рабочие", BXM kodi, telefon, ism-familiya.
        $errors = app(EmployeeCheckService::class)->eligibilityErrors($employee);

        if ($errors !== []) {
            Log::warning('[AD_ACCOUNT] Xodim AD ochishga mos emas', [
                'pinfl' => $pinfl,
                'eligibility_errors' => $errors,
            ]);

            return response()->json([
                'message' => 'Siz hozircha xodimlar ro\'yxatida faol ko\'rinmayapsiz. Agar bu xato bo\'lsa, IT bo\'limiga murojaat qiling.',
                'eligibility_errors' => $errors,
            ], 422);
        }

        $hasExchangeAccount = $this->hasExchangeAccount($pinfl);
        $exchangeBxmCode = $hasExchangeAccount ? $this->exchangeBxmCode($pinfl) : null;
        $rotated = $hasExchangeAccount
            && $employee['bxm_code'] !== null
            && $exchangeBxmCode !== null
            && $this->normalizeBxmCode((string) $employee['bxm_code']) !== $this->normalizeBxmCode($exchangeBxmCode);

        return response()->json([
            'message' => 'Xodim topildi va tasdiqlandi. BXM kodini kiriting.',
            'has_exchange_account' => $hasExchangeAccount,
            'rotated' => $rotated,
            'exchange_bxm' => $exchangeBxmCode,
            'employee' => [
                'first_name' => $employee['first_name'],
                'last_name' => $employee['last_name'],
                'middle_name' => $employee['middle_name'],
                'department' => $employee['department'],
                'position' => $employee['position'],
                'bxm_code' => $employee['bxm_code'],
                'state' => $employee['state'],
                'condition_name' => $employee['condition_name'],
            ],
        ]);
    }

    /**
     * BXM kodini API dagi bxm_code bilan solishtiradi (2-bosqich).
     *
     * Avval rotatsiya aniqlanadi: API BXM ≠ Exchange akkaunt BXM bo'lsa,
     * xodim boshqa BXM ga ko'chirilgan. Bunday holatda faqat API'dagi
     * (to'g'ri) kod qabul qilinadi — noto'g'ri kod "o'z BXM kodingizni
     * kiriting" xabari bilan qaytariladi.
     *
     * Rotatsiya bo'lmasa: kiritilgan kod API'dagi kodga mos kelsa keyingi
     * bosqichga o'tadi, mos kelmasa "BXM kodi xato kiritildi" qaytariladi.
     */
    public function checkBxm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pinfl' => ['required', 'string', 'size:14', 'regex:/^[0-9]{14}$/'],
            'bxm_code' => ['required', 'string', 'max:20'],
        ]);

        $pinfl = (string) $validated['pinfl'];
        $bxmCode = $this->normalizeBxmCode((string) $validated['bxm_code']);

        $employee = app(EmployeeCheckService::class)->findByPinfl($pinfl);

        if (! $employee) {
            return response()->json([
                'message' => 'Xodim topilmadi. PINFL (JShShIR) raqamni tekshirib ko\'ring.',
            ], 422);
        }

        // "Ha, davom etamiz" bosilganda ham faqat barcha ma'lumotlar to'liq
        // va faol bo'lsagina davom ettiriladi (state, condition, BXM, telefon).
        $errors = app(EmployeeCheckService::class)->eligibilityErrors($employee);

        if ($errors !== []) {
            Log::warning('[AD_ACCOUNT] Xodim AD ochishga mos emas', [
                'pinfl' => $pinfl,
                'eligibility_errors' => $errors,
            ]);

            return response()->json([
                'message' => 'Siz hozircha xodimlar ro\'yxatida faol ko\'rinmayapsiz. Agar bu xato bo\'lsa, IT bo\'limiga murojaat qiling.',
                'eligibility_errors' => $errors,
            ], 422);
        }

        $apiBxmCode = $employee['bxm_code'];

        // ── Rotatsiya aniqlash: API dagi BXM ≠ Exchange'dagi akkaunt BXM ────
        // Xodim boshqa BXM ga ko'chirilgan bo'lsa, API (HR) yangi BXM ni biladi,
        // Exchange'dagi akkaunt esa eski BXM (OU) da qolgan bo'ladi.
        $hasExchangeAccount = $this->hasExchangeAccount($pinfl);
        $exchangeBxmCode = $hasExchangeAccount ? $this->exchangeBxmCode($pinfl) : null;
        $isRotated = $hasExchangeAccount
            && $apiBxmCode !== null
            && $exchangeBxmCode !== null
            && $this->normalizeBxmCode((string) $apiBxmCode) !== $this->normalizeBxmCode($exchangeBxmCode);

        Log::info('[AD_ACCOUNT] BXM solishtirish', [
            'pinfl' => $pinfl,
            'entered_bxm' => $bxmCode,
            'api_bxm' => $apiBxmCode,
            'exchange_bxm' => $exchangeBxmCode,
            'rotated' => $isRotated,
        ]);

        if ($isRotated) {
            // Rotatsiya bo'lgan xodim o'z (API'dagi) BXM kodini to'g'ri
            // kiritmaguncha keyingi bosqichga o'tmaydi — 422 qaytarilib,
            // frontend BXM maydonida xato sifatida ko'rsatadi (qayta-qayta).
            if ($this->normalizeBxmCode((string) $apiBxmCode) !== $bxmCode) {
                return response()->json([
                    'message' => 'Siz BXMni o\'zgartirgansiz. O\'z BXM kodingizni kiriting.',
                ], 422);
            }

            return response()->json([
                'message' => 'BXM kodingiz tasdiqlandi (BXM: '.$apiBxmCode.'). Davom etish uchun tasdiqlang.',
                'matched' => true,
                'rotated' => true,
                'bxm_code' => $apiBxmCode,
                'has_exchange_account' => true,
            ]);
        }

        // API'da BXM kodi ko'rsatilmagan — solishtirib bo'lmaydi
        if ($apiBxmCode === null) {
            return response()->json([
                'message' => 'BXM kodingiz tizimda aniqlanmadi. IT bo\'limiga murojaat qiling.',
            ], 422);
        }

        if ($this->normalizeBxmCode((string) $apiBxmCode) === $bxmCode) {
            return response()->json([
                'message' => 'BXM kodingiz tasdiqlandi (BXM: '.$apiBxmCode.'). Davom etish uchun tasdiqlang.',
                'matched' => true,
                'bxm_code' => $apiBxmCode,
                'has_exchange_account' => $hasExchangeAccount,
            ]);
        }

        // API BXM == Exchange BXM (rotatsiya yo'q), lekin kiritilgan kod
        // xato — oddiy xato xabari, oqim BXM bosqichida qoladi.
        return response()->json([
            'message' => 'BXM kodi xato kiritildi. Qayta tekshirib ko\'ring.',
        ], 422);
    }

    /**
     * Telefon raqamini API dagi raqam bilan solishtiradi va SMS yuboradi (3-bosqich).
     *
     * Raqam mos kelsa SMS yuboriladi, mos kelmasa bildirishnoma qaytariladi.
     */
    public function sendCode(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pinfl' => ['required', 'string', 'size:14', 'regex:/^[0-9]{14}$/'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?998[0-9]{9}$/'],
        ]);

        $pinfl = (string) $validated['pinfl'];
        $phone = $this->normalizePhone((string) $validated['phone']);

        $employee = app(EmployeeCheckService::class)->findByPinfl($pinfl);

        if (! $employee) {
            return response()->json([
                'message' => 'Xodim topilmadi. PINFL (JShShIR) raqamni tekshirib ko\'ring.',
            ], 422);
        }

        // SMS yuborishdan oldin ham xodim hali ham faol va to'liq ekanligi tekshiriladi
        $errors = app(EmployeeCheckService::class)->eligibilityErrors($employee);

        if ($errors !== []) {
            Log::warning('[AD_ACCOUNT] Xodim AD ochishga mos emas', [
                'pinfl' => $pinfl,
                'eligibility_errors' => $errors,
            ]);

            return response()->json([
                'message' => 'Siz hozircha xodimlar ro\'yxatida faol ko\'rinmayapsiz. Agar bu xato bo\'lsa, IT bo\'limiga murojaat qiling.',
                'eligibility_errors' => $errors,
            ], 422);
        }

        // Telefon raqamini API dagi (PINFL bo'yicha) raqam bilan solishtirish.
        // SMS FAQAT tizimda shu xodimga ko'rsatilgan raqamga yuboriladi —
        // ixtiyoriy/boshqa raqamga hech qachon SMS ketmaydi.
        $employeePhone = $employee['phone'];

        if ($employeePhone === null || $employeePhone === '') {
            Log::warning('[AD_ACCOUNT] Xodimda telefon raqami ko\'rsatilmagan', ['pinfl' => $pinfl]);

            return response()->json([
                'message' => 'Tizimda sizning telefon raqamingiz ko\'rsatilmagan. IT bo\'limiga murojaat qiling.',
                'phone_matched' => false,
            ], 422);
        }

        // Prefiksdan qat'iy nazar oxirgi 9 raqam solishtiriladi:
        // "+998944866308" / "998944866308" / "944866308" — hammasi teng.
        $employeeLast9 = substr(preg_replace('/\D/', '', (string) $employeePhone) ?? '', -9);
        $enteredLast9 = substr(preg_replace('/\D/', '', $phone) ?? '', -9);

        if ($employeeLast9 !== $enteredLast9) {
            Log::warning('[AD_ACCOUNT] Telefon raqam mos kelmadi — SMS yuborilmadi', [
                'pinfl' => $pinfl,
                'entered_phone' => $phone,
            ]);

            return response()->json([
                'message' => 'Siz boshqa odamning tel raqamini kiritdingiz. PINFL (JShShIR) bo\'yicha tizimda ko\'rsatilgan raqamni kiriting.',
                'phone_matched' => false,
            ], 422);
        }

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
            // Xavfsizlik: kod plaintext emas — bcrypt hash saqlanadi
            'code' => \Illuminate\Support\Facades\Hash::make($code),
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
            // Token faqat olinadi (keshga tushadi), javobga QO'SHILMAYDI:
            // endpoint autentifikatsiyasiz, tokenning bir bo'lagi ham
            // tashqariga chiqmasligi kerak.
            app(SsoTokenService::class)->token();

            return response()->json([
                'status' => 'ready',
                'token_cached' => true,
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
     * SMS tasdiqlash holatini xavfsiz va bir martalik iste'mol qilish (Replay attack prevention).
     *
     * Token MAJBURIY: usiz "telefon raqami tasdiqlangan" holatini raqamni biladigan
     * istalgan chaqiruvchi ilib ketishi mumkin edi — ya'ni token hech narsani
     * bog'lamas edi. Endi faqat verifyCode qaytargan tokengina qabul qilinadi.
     *
     * Iste'mol `verification_consumed_at` bilan belgilanadi, `verified_at` esa
     * TEGILMAYDI: uni NULL qilish yozuvni yana "tasdiqlanmagan, amaldagi" holatga
     * qaytarib, sendCode dagi "SMS allaqachon yuborilgan" qoidasini ishga tushirar
     * va foydalanuvchi kod muddati tugagunicha yangi SMS ololmay qolardi.
     */
    private function consumeSmsVerification(string $phone, string $token): ?object
    {
        if ($token === '') {
            return null;
        }

        return DB::transaction(function () use ($phone, $token) {
            $record = DB::table('sms_codes')
                ->where('phone', $phone)
                ->where('verification_token', $token)
                ->whereNotNull('verified_at')
                ->whereNull('verification_consumed_at')
                ->where('verified_at', '>', now()->subMinutes(15))
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (!$record) {
                return null;
            }

            // Bir martalik: shu tokenni qayta ishlatib bo'lmaydi.
            DB::table('sms_codes')
                ->where('id', $record->id)
                ->update([
                    'verification_consumed_at' => now(),
                    'updated_at' => now(),
                ]);

            return $record;
        });
    }

    /**
     * Exchange'da pochta (AD akkaunt) avtomatik yaratadi (5-bosqich).
     *
     * Shartlar:
     *  - PINFL orqali xodim hali ham faol va to'liq ma'lumotli
     *  - Telefon SMS orqali tasdiqlangan bo'lishi kerak (bir xil raqam)
     *  - Xodimga tegishli akkaunt Exchange'da mavjud bo'lmasligi kerak
     */
    public function createExchange(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pinfl' => ['required', 'string', 'size:14', 'regex:/^[0-9]{14}$/'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?998[0-9]{9}$/'],
            'bxm_code' => ['required', 'string', 'max:20'],
            'verification_token' => ['required', 'string', 'max:100'],
        ]);

        $pinfl = (string) $validated['pinfl'];
        $phone = $this->normalizePhone((string) $validated['phone']);
        $bxmCode = ltrim((string) $validated['bxm_code'], '0');
        $token = (string) $validated['verification_token'];

        // ── Telefon SMS orqali tasdiqlangan bo'lishi shart (bir martalik iste'mol) ──
        $verified = $this->consumeSmsVerification($phone, $token);

        if (! $verified) {
            return response()->json([
                'message' => 'Telefon raqam avval SMS orqali tasdiqlanishi kerak yoki tasdiqlash muddati tugagan.',
            ], 422);
        }

        // ── Xodim ma'lumotlarini qayta tekshirish ────────────────────────────
        $employee = app(EmployeeCheckService::class)->findByPinfl($pinfl);

        if (! $employee) {
            return response()->json([
                'message' => 'Xodim topilmadi. PINFL (JShShIR) raqamni tekshirib ko\'ring.',
            ], 422);
        }

        $errors = app(EmployeeCheckService::class)->eligibilityErrors($employee);

        if ($errors !== []) {
            Log::warning('[AD_ACCOUNT] Yaratishda xodim mos emas', [
                'pinfl' => $pinfl,
                'eligibility_errors' => $errors,
            ]);

            return response()->json([
                'message' => 'Siz hozircha xodimlar ro\'yxatida faol ko\'rinmayapsiz. Agar bu xato bo\'lsa, IT bo\'limiga murojaat qiling.',
                'eligibility_errors' => $errors,
            ], 422);
        }

        // ── Telefon API dagi raqam bilan mosligini qayta tekshirish ──────────
        if (! empty($employee['phone'])) {
            $employeePhone = ltrim((string) $employee['phone'], '+');
            $enteredPhone = ltrim($phone, '+');

            if ($employeePhone !== $enteredPhone) {
                return response()->json([
                    'message' => 'Telefon raqam mos kelmadi. Tizimda boshqa raqam ko\'rsatilgan.',
                ], 422);
            }
        }

        // ── Exchange'da yaratish ─────────────────────────────────────────────
        // PINFL employeeID atributiga saqlanadi — keyinchalik "pochta
        // yaratilganmi" tekshiruvi Exchange'dan shu orqali qilinadi.
        $employee['pinfl'] = $pinfl;

        try {
            $created = app(ExchangeMailService::class)->create($employee, $bxmCode);
        } catch (\Throwable $e) {
            Log::error('[AD_ACCOUNT] Exchange yaratishda xatolik', [
                'pinfl' => $pinfl,
                'error' => $e->getMessage(),
            ]);

            DB::table('ad_accounts')->insert([
                'pinfl' => $pinfl,
                'username' => '',
                'email' => app(ExchangeMailService::class)->buildEmail($employee),
                'password_encrypted' => '',
                'bxm_code' => $bxmCode,
                'status' => 'FAILED',
                'error' => $e->getMessage(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                // Raw exception message oshkor qilinmaydi (LDAP server IP/DN leak)
                'message' => 'Pochta yaratishda xatolik yuz berdi. IT administratoriga murojaat qiling.',
            ], 502);
        }

        // ── Natijani saqlash ─────────────────────────────────────────────────
        $accountId = DB::table('ad_accounts')->insertGetId([
            'pinfl' => $pinfl,
            'username' => $created['username'],
            'email' => $created['email'],
            'password_encrypted' => Crypt::encryptString($created['password']),
            'bxm_code' => $bxmCode,
            'ou_dn' => $created['ou'],
            'group_dn' => $created['group_dn'],
            'ad_object_guid' => $created['object_guid'],
            'status' => 'CREATED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::info('[AD_ACCOUNT] Pochta yaratildi', [
            'account_id' => $accountId,
            'pinfl' => $pinfl,
            'email' => $created['email'],
            'ou' => $created['ou'],
            'group' => $created['group_dn'],
        ]);

        return response()->json([
            'message' => 'Pochta muvaffaqiyatli yaratildi!',
            'account' => [
                'id' => $accountId,
                'username' => $created['username'],
                'email' => $created['email'],
                'password' => $created['password'],
                'ou' => $created['ou'],
                'group_dn' => $created['group_dn'],
            ],
        ]);
    }

    /**
     * Pochta (AD akkaunt) allaqachon yaratilgan bo'lsa — parolni almashtiradi.
     *
     * Oqim: telefon SMS orqali tasdiqlangan → Exchange'da employeeID (PINFL)
     * bo'yicha akkaunt topiladi → yangi parol generatsiya qilinadi va
     * o'rnatiladi → ad_accounts yangilanadi → yangi login/parol qaytariladi.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pinfl' => ['required', 'string', 'size:14', 'regex:/^[0-9]{14}$/'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?998[0-9]{9}$/'],
            'verification_token' => ['required', 'string', 'max:100'],
        ]);

        $pinfl = (string) $validated['pinfl'];
        $phone = $this->normalizePhone((string) $validated['phone']);
        $token = (string) $validated['verification_token'];

        // ── Telefon SMS orqali tasdiqlangan bo'lishi shart (bir martalik iste'mol) ──
        $verified = $this->consumeSmsVerification($phone, $token);

        if (! $verified) {
            return response()->json([
                'message' => 'Telefon raqam avval SMS orqali tasdiqlanishi kerak yoki tasdiqlash muddati tugagan.',
            ], 422);
        }

        // ── Xodim hali ham faol ekanligini tekshirish ────────────────────────
        $employee = app(EmployeeCheckService::class)->findByPinfl($pinfl);

        if (! $employee) {
            return response()->json([
                'message' => 'Xodim topilmadi. PINFL (JShShIR) raqamni tekshirib ko\'ring.',
            ], 422);
        }

        $errors = app(EmployeeCheckService::class)->eligibilityErrors($employee);

        if ($errors !== []) {
            return response()->json([
                'message' => 'Siz hozircha xodimlar ro\'yxatida faol ko\'rinmayapsiz. Agar bu xato bo\'lsa, IT bo\'limiga murojaat qiling.',
                'eligibility_errors' => $errors,
            ], 422);
        }

        // ── Telefon API dagi raqam bilan mosligini tekshirish ────────────────
        if (! empty($employee['phone'])) {
            $employeePhone = ltrim((string) $employee['phone'], '+');
            $enteredPhone = ltrim($phone, '+');

            if ($employeePhone !== $enteredPhone) {
                return response()->json([
                    'message' => 'Telefon raqam mos kelmadi. Tizimda boshqa raqam ko\'rsatilgan.',
                ], 422);
            }
        }

        // ── Exchange'da parolni almashtirish ─────────────────────────────────
        try {
            $newPassword = app(ExchangeMailService::class)->generatePassword();
            $updated = app(ExchangeMailService::class)->resetPassword($pinfl, $newPassword);
        } catch (\Throwable $e) {
            Log::error('[AD_ACCOUNT] Parolni almashtirishda xatolik', [
                'pinfl' => $pinfl,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Parolni almashtirishda xatolik yuz berdi. IT administratoriga murojaat qiling.',
            ], 502);
        }

        // ── ad_accounts ni yangilash (login sahifasi yangi parolni ko'rsatadi) ─
        DB::table('ad_accounts')
            ->where('pinfl', $pinfl)
            ->where('status', 'CREATED')
            ->update([
                'username' => $updated['username'],
                'email' => $updated['email'] ?? null,
                'password_encrypted' => Crypt::encryptString($newPassword),
                'updated_at' => now(),
            ]);

        Log::info('[AD_ACCOUNT] Parol almashtirildi', [
            'pinfl' => $pinfl,
            'username' => $updated['username'],
        ]);

        return response()->json([
            'message' => 'Parol muvaffaqiyatli almashtirildi!',
            'account' => [
                'username' => $updated['username'],
                'email' => $updated['email'],
                'password' => $newPassword,
                'dn' => $updated['dn'],
            ],
        ]);
    }

    /**
     * Rotatsiya holati: xodim boshqa BXM ga ko'chgan — mavjud pochtani
     * yangi BXM ga biriktiradi (user boshqa OU ga ko'chiriladi).
     *
     * Oqim: telefon SMS orqali tasdiqlangan → Exchange'da employeeID (PINFL)
     * bo'yicha akkaunt topiladi → yangi BXM OU siga moddn ko'chiriladi →
     * ad_accounts.bxm_code yangilanadi.
     */
    public function linkBxm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pinfl' => ['required', 'string', 'size:14', 'regex:/^[0-9]{14}$/'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?998[0-9]{9}$/'],
            'bxm_code' => ['required', 'string', 'max:20'],
            'verification_token' => ['required', 'string', 'max:100'],
        ]);

        $pinfl = (string) $validated['pinfl'];
        $phone = $this->normalizePhone((string) $validated['phone']);
        $bxmCode = (string) $validated['bxm_code'];
        $token = (string) $validated['verification_token'];

        // ── Telefon SMS orqali tasdiqlangan bo'lishi shart (bir martalik iste'mol) ──
        $verified = $this->consumeSmsVerification($phone, $token);

        if (! $verified) {
            return response()->json([
                'message' => 'Telefon raqam avval SMS orqali tasdiqlanishi kerak yoki tasdiqlash muddati tugagan.',
            ], 422);
        }

        // ── Exchange'da akkauntni yangi BXM (OU) ga ko'chirish ───────────────
        try {
            $moved = app(ExchangeMailService::class)->moveToOu($pinfl, $bxmCode);
        } catch (\Throwable $e) {
            Log::error('[AD_ACCOUNT] BXM ga biriktirishda xatolik', [
                'pinfl' => $pinfl,
                'bxm' => $bxmCode,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Pochtani boshqa BXM ga biriktirishda xatolik yuz berdi. IT administratoriga murojaat qiling.',
            ], 502);
        }

        // ── ad_accounts ni yangilash ─────────────────────────────────────────
        DB::table('ad_accounts')
            ->where('pinfl', $pinfl)
            ->where('status', 'CREATED')
            ->update([
                'bxm_code' => $bxmCode,
                'ou_dn' => $moved['ou'],
                'updated_at' => now(),
            ]);

        Log::info('[AD_ACCOUNT] Pochta boshqa BXM ga biriktirildi', [
            'pinfl' => $pinfl,
            'bxm' => $bxmCode,
            'ou' => $moved['ou'],
        ]);

        return response()->json([
            'message' => 'Pochtangiz yangi BXM ga muvaffaqiyatli biriktirildi!',
            'bxm_code' => $bxmCode,
            'ou' => $moved['ou'],
        ]);
    }

    /**
     * Xodimga Exchange'da pochta (AD akkaunt) yaratilganmi?
     *
     * employeeID (PINFL) bo'yicha Exchange AD dan tekshiradi. Xatolik yoki
     * server mavjud emas holatida false qaytaradi — oqim to'xtatilmaydi.
     */
    private function hasExchangeAccount(string $pinfl): bool
    {
        try {
            return app(ExchangeMailService::class)->findByPinfl($pinfl) !== null;
        } catch (\Throwable $e) {
            Log::warning('[AD_ACCOUNT] Exchange tekshiruvda xatolik', [
                'pinfl' => $pinfl,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Exchange'dagi akkauntning joriy BXM kodi (OU orqali teskari xarita).
     * Akkaunt yo'q yoki OU xaritada bo'lmasa null qaytadi.
     */
    private function exchangeBxmCode(string $pinfl): ?string
    {
        try {
            return app(ExchangeMailService::class)->getBxmCodeByPinfl($pinfl);
        } catch (\Throwable $e) {
            Log::warning('[AD_ACCOUNT] Exchange BXM kod o\'qishda xatolik', [
                'pinfl' => $pinfl,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Login sahifasi uchun oxirgi yaratilgan pochta ma'lumotlari.
     *
     * XAVFSIZLIK: parol BU ENDPOINTDA QAYTARILMAYDI (endpoint public —
     * auth'siz har qanday odam so'rovi mumkin). Parol faqat akkaunt
     * yaratish/reset oqimining to'g'ridan-to'g'ri javobida bir marta
     * ko'rsatiladi. Bu endpoint faqat salomlashish va login/email uchun.
     */
    /**
     * SMS kodni tasdiqlaydi (4-bosqich).
     */
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

        if ($record->expires_at && now()->gt($record->expires_at)) {
            return response()->json(['message' => 'Kod muddati tugagan. Yangi SMS yuboring.'], 422);
        }

        // ATOMAR attempts increment — parallel so'rovlar limitni chetlab
        // o'ta olmaydi (WHERE attempts < 5 sharti bilan).
        // affected rows = 0 bo'lsa limit allaqachon to'lgan.
        $incremented = DB::table('sms_codes')
            ->where('id', $record->id)
            ->where('attempts', '<', 5)
            ->update(['attempts' => DB::raw('attempts + 1'), 'updated_at' => now()]);

        if ($incremented === 0) {
            return response()->json(['message' => 'Urinishlar soni oshib ketdi. Yangi SMS yuboring.'], 422);
        }

        // Xavfsizlik: kod DB'da hash ko'rinishida — Hash::check bilan solishtiriladi.
        // Eski (hashlanmagan) yozuvlar uchun ham timing-safe plaintext solishtirish qo'llanadi.
        $codeMatches = str_starts_with((string) $record->code, '$2y$')
            ? \Illuminate\Support\Facades\Hash::check($code, (string) $record->code)
            : hash_equals((string) $record->code, $code);

        if (! $codeMatches) {
            return response()->json(['message' => 'Kod noto\'g\'ri. Qayta tekshirib ko\'ring.'], 422);
        }

        // Xavfsiz bir martalik verification token generatsiyasi
        $verificationToken = bin2hex(random_bytes(32));

        // DIQQAT: `request_id` TEGILMAYDI — u SMS gateway'ning so'rov identifikatori
        // (sendCode da yoziladi, yetkazib berishni kuzatish uchun kerak).
        DB::table('sms_codes')
            ->where('id', $record->id)
            ->whereNull('verified_at')
            ->update([
                'verified_at' => now(),
                'verification_token' => $verificationToken,
                'verification_consumed_at' => null,
                'updated_at' => now(),
            ]);

        return response()->json([
            'message' => 'Telefon raqamingiz muvaffaqiyatli tasdiqlandi!',
            'phone' => $phone,
            'verification_token' => $verificationToken,
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