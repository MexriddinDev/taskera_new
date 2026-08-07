<?php

declare(strict_types=1);

namespace App\Modules\Telegram\Infrastructure\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class VerifyBotLoginService
{
    public function __construct(private readonly ?object $adVerifier = null) {}

    public function verify(string $username, string $password): ?User
    {
        $username = trim($username);
        if ($username === '' || $password === '') {
            return null;
        }

        $user = User::query()
            ->where('username', $username)
            ->orWhere('email', $username)
            ->first();

        if (! $user) {
            return null;
        }

        if (strtoupper((string) $user->auth_source) === 'AD') {
            return $this->verifyViaAd($user, $password);
        }

        if (empty($user->password) || ! Hash::check($password, (string) $user->password)) {
            return null;
        }

        return $user;
    }

    private function verifyViaAd(User $user, string $password): ?User
    {
        if ($this->adVerifier === null) {
            return null;
        }

        return $this->adVerifier->verify($user, $password);
    }
}
