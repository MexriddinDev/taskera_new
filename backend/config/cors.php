<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:5173'),
        // Lokal dev-server ro'yxatda DOIM qoladi. Ilgari u faqat FRONTEND_URL
        // belgilanmaganda ishlardi: ekspluatatsiya manzili qo'yilishi bilan
        // `npm run dev` CORS'da bloklanib qolardi.
        'http://localhost:5173',
        'https://localhost:5173',
        'http://localhost:8000',
        'http://127.0.0.1:8000',
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
