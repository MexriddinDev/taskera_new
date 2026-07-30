<?php

namespace App\Shared\Infrastructure\Security;

class MaskPiiService
{
    private static array $sensitiveKeys = ['password', 'secret', 'token', 'credit_card', 'cvv'];

    public static function maskArray(array $data): array
    {
        foreach ($data as $key => &$value) {
            if (in_array(strtolower($key), self::$sensitiveKeys, true)) {
                $value = '********';
            } elseif (is_array($value)) {
                $value = self::maskArray($value);
            }
        }
        return $data;
    }
}
