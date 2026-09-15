<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Infrastructure\Services;

use App\Models\User;
use App\Services\SmsGatewayService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Telegram botiga kirish uchun bir martalik SMS kod.
 *
 * Xavfsizlik qoidasi: kod FAQAT tizimda o'sha pochtaga biriktirilgan xodim
 * raqamiga yuboriladi. Foydalanuvchi raqamni o'zi tanlay olmaydi — aks holda
 * begona pochtani yozib, kodni o'z telefoniga olib, boshqa odam nomidan kirish
 * mumkin bo'lardi.
 *
 * `sms_codes` jadvali va gateway AD hisob ochish oqimidan qayta ishlatiladi:
 * kod plaintext saqlanmaydi (bcrypt), urinishlar sanaladi, muddati bor.
 */
final class BotLoginCodeService
{
    /** Kod amal qilish muddati. */
    private const TTL_MINUTES = 5;

    /** Shuncha xato urinishdan keyin kod kuydiriladi. */
    private const MAX_ATTEMPTS = 5;

    /**
     * Pochta bo'yicha foydalanuvchini topib, uning raqamiga kod yuboradi.
     *
     * @return array{ok:bool, message:string, phone?:string, user_id?:int}
     */
    public function send(string $email): array
    {
        $user = $this->findByEmail($email);

        if (! $user) {
            // Ataylab umumiy xabar: mavjud pochtalarni tergab bilib olishning
            // oldini oladi.
            return ['ok' => false, 'message' => 'Bunday pochta tizimda topilmadi yoki unga telefon raqam biriktirilmagan.'];
        }

        if (strtolower((string) $user->status) !== 'active') {
            return ['ok' => false, 'message' => "Hisobingiz nofaol holatda. Administrator bilan bog'laning."];
        }

        $phone = $this->phoneFor($user);

        if ($phone === null) {
            Log::warning('[BOT_LOGIN] Foydalanuvchida telefon raqam yo\'q', ['user_id' => $user->id]);

            return ['ok' => false, 'message' => 'Bunday pochta tizimda topilmadi yoki unga telefon raqam biriktirilmagan.'];
        }

        $code = (string) random_int(10000, 99999);
        $requestId = (string) Str::uuid();

        DB::table('sms_codes')->insert([
            'phone' => $phone,
            'code' => Hash::make($code),
            'request_id' => $requestId,
            'template_id' => (string) config('services.sms.template_id'),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (! app(SmsGatewayService::class)->send($phone, $code, $requestId)) {
            return ['ok' => false, 'message' => "SMS yuborilmadi. Birozdan so'ng qayta urinib ko'ring."];
        }

        DB::table('sms_codes')
            ->where('request_id', $requestId)
            ->update(['sent_at' => now(), 'updated_at' => now()]);

        return [
            'ok' => true,
            'message' => 'SMS yuborildi.',
            'phone' => $this->mask($phone),
            'user_id' => (int) $user->id,
        ];
    }

    /**
     * Pochta bo'yicha foydalanuvchi.
     *
     * Alohida metod, chunki bot kod yuborishdan OLDIN hisobning 1-qadamda
     * yuborilgan telefon raqamiga tegishli ekanini tekshiradi.
     */
    public function findByEmail(string $email): ?User
    {
        return User::query()
            ->whereNull('deleted_at')
            ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($email))])
            ->first();
    }

    /**
     * Kodni tekshiradi. To'g'ri bo'lsa foydalanuvchini qaytaradi.
     *
     * @return array{ok:bool, message:string, user?:User}
     */
    public function verify(int $userId, string $code): array
    {
        $user = User::query()->whereNull('deleted_at')->find($userId);

        if (! $user) {
            return ['ok' => false, 'message' => 'Sessiya eskirgan. Qaytadan boshlang.'];
        }

        $phone = $this->phoneFor($user);

        if ($phone === null) {
            return ['ok' => false, 'message' => 'Sessiya eskirgan. Qaytadan boshlang.'];
        }

        $record = DB::table('sms_codes')
            ->where('phone', $phone)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('id')
            ->first();

        if (! $record) {
            return ['ok' => false, 'message' => 'Kod muddati tugagan. Pochtangizni qaytadan yozing.'];
        }

        if ((int) $record->attempts >= self::MAX_ATTEMPTS) {
            return ['ok' => false, 'message' => "Juda ko'p xato urinish. Pochtangizni qaytadan yozing."];
        }

        if (! Hash::check($code, (string) $record->code)) {
            DB::table('sms_codes')->where('id', $record->id)->update([
                'attempts' => DB::raw('attempts + 1'),
                'updated_at' => now(),
            ]);

            return ['ok' => false, 'message' => "Kod noto'g'ri. Qaytadan kiriting:"];
        }

        // Kod bir martalik — darhol kuydiriladi.
        DB::table('sms_codes')->where('id', $record->id)->update([
            'verified_at' => now(),
            'updated_at' => now(),
        ]);

        return ['ok' => true, 'message' => 'Tasdiqlandi.', 'user' => $user];
    }

    /** Foydalanuvchining xodim kartochkasidagi raqami. */
    private function phoneFor(User $user): ?string
    {
        if (empty($user->employee_id)) {
            return null;
        }

        $phone = DB::table('employees')->where('id', $user->employee_id)->value('phone');
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        return $digits === '' ? null : $digits;
    }

    /** Xabarda to'liq raqam ko'rsatilmaydi: 998915092777 -> ****2777. */
    private function mask(string $phone): string
    {
        return '****'.mb_substr($phone, -4);
    }
}
