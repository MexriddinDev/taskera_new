<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Infrastructure\Services;

use App\Models\User;
use App\Services\AdAuthService;
use App\Services\AdUserProvisionService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class VerifyBotLoginService
{
    /**
     * DIQQAT: parametrlarda `= null` default BO'LMASLIGI kerak.
     *
     * Laravel konteyneri (Container::resolveClass) default qiymati bor va
     * konteynerkda aniq binding'i yo'q parametrni umuman yaratmaydi — shunchaki
     * default'ni qaytaradi. Ilgari shu sabab $adAuth doim null bo'lib qolar,
     * botdagi AD login esa jimgina "parol noto'g'ri" berardi.
     */
    public function __construct(
        private readonly AdAuthService $adAuth,
        private readonly AdUserProvisionService $provision,
    ) {}

    public function verify(string $username, string $password): ?User
    {
        $username = trim($username);
        if ($username === '' || $password === '') {
            return null;
        }

        $username = strtolower($username);
        // UPN ko'rinishida yozilsa ("yusuf.rahimboyev@xb.uz") — domain qismi olib tashlanadi,
        // chunki AD da sAMAccountName domensiz saqlanadi.
        $username = preg_replace('/@.*$/', '', $username);

        $user = User::query()
            ->where('username', $username)
            ->orWhere('email', $username)
            ->first();

        // LOCAL user: DB hash bilan tekshirish (saytdagi login bilan bir xil)
        if ($user && strtoupper((string) $user->auth_source) === 'LOCAL' && ! empty($user->password)) {
            return Hash::check($password, (string) $user->password) ? $user : null;
        }

        // AD user (yoki DB da yo'q, yoki parol DB da yo'q) — AD orqali tekshirish
        return $this->verifyViaAd($username, $user, $password);
    }

    private function verifyViaAd(string $username, ?User $user, string $password): ?User
    {
        try {
            $attributes = $this->adAuth->authenticate($username, $password);
        } catch (\RuntimeException $e) {
            Log::warning("Bot AD login: server mavjud emas ({$username})", ['error' => $e->getMessage()]);

            return null;
        }

        if (! $attributes) {
            return null;
        }

        if (isset($attributes['enabled']) && ! $attributes['enabled']) {
            Log::info("Bot AD login: hisob bloklangan ({$username})");

            return null;
        }

        try {
            return $this->provision->findOrProvision($attributes);
        } catch (\Throwable $e) {
            Log::error('Bot AD provision xatosi', ['username' => $username, 'error' => $e->getMessage()]);

            return null;
        }
    }
}
