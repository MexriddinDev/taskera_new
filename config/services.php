<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'ad' => [
        'host' => env('LDAP_HOST', '172.28.2.178'),
        'port' => (int) env('LDAP_PORT', 389),
        'base_dn' => env('LDAP_BASE_DN', 'DC=adatum,DC=com'),
        'service_user' => env('LDAP_SERVICE_USER', 'administrator@adatum.com'),
        'service_pass' => env('LDAP_SERVICE_PASS', ''),
        'timeout' => (int) env('LDAP_TIMEOUT', 5),
    ],

    // SSO (sso.xb.uz) — client_credentials grant orqali xizmat tokenni olish.
    // Token olish: POST {url}/api/oauth2/token (Basic auth: client_id:client_secret)
    'sso' => [
        'url' => env('SSO_URL', 'https://sso-test.xb.uz'),
        'client_id' => env('SSO_CLIENT_ID', 'taskflow_test'),
        'client_secret' => env('SSO_CLIENT_SECRET', 'taskflow_test'),
        'scope' => env('SSO_SCOPE', 'sms_gateway'),
        // SSO expires_in bermasa ishlatiladigan fallback TTL (daqiqada)
        'token_ttl_minutes' => (int) env('SSO_TOKEN_TTL_MINUTES', 120),
        // Token muddati tugashidan qancha OLDIN yangilash kerak (sekundda)
        'token_buffer_seconds' => (int) env('SSO_TOKEN_BUFFER_SECONDS', 60),
        // SSO serverga to'g'ridan-to'g'ri ulanish (tarmoq proxysidan o'tmaslik).
        // Proxy ortidagi muhitlar uchun false qilish kerak.
        'bypass_proxy' => (bool) env('SSO_BYPASS_PROXY', false),
        // SSL sertifikat tekshiruvi (test muhitda self-signed CA uchun false qilinadi)
        'verify_ssl' => (bool) env('SSO_VERIFY_SSL', true),
    ],

    // SMS Gateway (SSO tokeni bilan chaqiriladi)
    'sms' => [
        'send_url' => env('SMS_SEND_URL', 'https://sms-test.xb.uz/v1/sms/single-sms-by-template'),
        'username' => env('SMS_USERNAME', 'xb_smsbanking'),
        'password' => env('SMS_PASSWORD', ''),
        'template_id' => env('SMS_TEMPLATE_ID', 'SYSTEM_VERIFY_CODE'),
        'from' => env('SMS_FROM', 'TaskFlow'),
        // SSL sertifikat tekshiruvi (test muhitda self-signed CA uchun false qilinadi)
        'verify_ssl' => (bool) env('SMS_VERIFY_SSL', true),
    ],

    // Xodimni tekshirish (PINFL bo'yicha xodim ma'lumotlari).
    // Endpoint: GET {url}/{pinfl} — PINFL yo'l parametri sifatida beriladi.
    'employee_check' => [
        'url' => env('CHECK_EMPLOYEE_URL', 'http://172.28.6.201:8079/check-employee'),
        'timeout' => (int) env('CHECK_EMPLOYEE_TIMEOUT', 15),
        'bypass_proxy' => (bool) env('CHECK_EMPLOYEE_BYPASS_PROXY', false),
    ],

    // Exchange (pochta) — AD foydalanuvchi va pochta qutisini yaratish.
    // Login AD (LDAP_*) dan alohida: bu yerda Exchange server (adatum.com).
    'exchange' => [
        'host' => env('EXCHANGE_LDAP_HOST', '172.28.2.161'),
        'port' => (int) env('EXCHANGE_LDAP_PORT', 636),
        'base_dn' => env('EXCHANGE_LDAP_BASE_DN', 'DC=adatum,DC=com'),
        'service_user' => env('EXCHANGE_LDAP_USER', 'administrator@adatum.com'),
        'service_pass' => env('EXCHANGE_LDAP_PASS', ''),
        'timeout' => (int) env('EXCHANGE_LDAP_TIMEOUT', 5),
        'email_domain' => env('EXCHANGE_EMAIL_DOMAIN', 'adatum.com'),
        // userPrincipalName uchun AD domeni (email_domain dan alohida bo'lishi mumkin)
        'upn_domain' => env('EXCHANGE_UPN_DOMAIN', env('EXCHANGE_EMAIL_DOMAIN', 'adatum.com')),
        // BXM kodi bo'lmagan barcha foydalanuvchilar tushadigan OU
        'default_ou' => env('EXCHANGE_DEFAULT_OU', 'OU=Headoffice,DC=adatum,DC=com'),
        // Avtomatik yaratiladigan guruhlar joylashadigan OU
        'groups_ou' => env('EXCHANGE_GROUPS_OU', 'OU=Headoffice,DC=adatum,DC=com'),
        // Department nomi bilan guruh topilmasa — avtomatik yaratilsinmi?
        'auto_create_groups' => (bool) env('EXCHANGE_AUTO_CREATE_GROUPS', true),
        // BXM kod → OU mapping (masalan: ["9006" => "OU=...,DC=adatum,DC=com"])
        'bxm_ou_map' => (function (string $raw): array {
            $map = [];
            foreach (explode(',', $raw) as $pair) {
                $pair = trim($pair);
                if ($pair === '' || ! str_contains($pair, ':')) {
                    continue;
                }
                [$code, $ou] = array_map('trim', explode(':', $pair, 2));
                if ($code !== '' && $ou !== '') {
                    $map[$code] = $ou;
                }
            }

            return $map;
        })(env('EXCHANGE_BXM_OU_MAP', '')),
        // Ixtiyoriy helper PowerShell xizmati — bo'sh bo'lsa LDAP usul ishlaydi
        'mailbox_api_url' => env('EXCHANGE_MAILBOX_API_URL', ''),
    ],

];
