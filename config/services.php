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
    ],

    // SMS Gateway (SSO tokeni bilan chaqiriladi)
    'sms' => [
        'send_url' => env('SMS_SEND_URL', 'https://sms-test.xb.uz/v1/sms/single-sms-by-template'),
        'username' => env('SMS_USERNAME', 'xb_smsbanking'),
        'password' => env('SMS_PASSWORD', ''),
        'template_id' => env('SMS_TEMPLATE_ID', 'SYSTEM_VERIFY_CODE'),
        'from' => env('SMS_FROM', 'TaskFlow'),
    ],

    // Xodimni tekshirish (PINFL bo'yicha xodim ma'lumotlari).
    // Endpoint: GET {url}/{pinfl} — PINFL yo'l parametri sifatida beriladi.
    'employee_check' => [
        'url' => env('CHECK_EMPLOYEE_URL', 'http://172.28.6.201:8079/check-employee'),
        'timeout' => (int) env('CHECK_EMPLOYEE_TIMEOUT', 15),
        'bypass_proxy' => (bool) env('CHECK_EMPLOYEE_BYPASS_PROXY', false),
    ],

];
