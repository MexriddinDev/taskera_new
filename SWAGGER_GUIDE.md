# 📘 Swagger (OpenAPI 3.0) Qo'llanmasi — Taskera Enterprise ITSM

Ushbu qo'llanmada loyihadagi **L5-Swagger (OpenAPI 3.0)** tizimidan foydalanish, yangi API endpointlarini hujjatlashtirish va generatsiya qilish bo'yicha to'liq ma'lumotlar keltirilgan.

---

## 1. 🚀 Tezkor Ishga Tushirish

### Dokumentatsiyani generatsiya qilish:
Har safar controllerlarga yangi `#[OA\...]` attributlari yozilganda yoki o'zgartirilganda quyidagi buyruqni bering:

```bash
php artisan l5-swagger:generate
```

### Swagger UI interfeysini ochish:
Loyihani ishga tushirganingizdan so'ng brauzerda quyidagi manzilga kiring:

```
http://localhost:8000/api/documentation
```

---

## 2. ⚙️ Konfiguratsiya va Muhit Sozlamalari

- **Asosiy config fayl:** `config/l5-swagger.php`
- **Generatsiya qilingan JSON fayl:** `storage/api-docs/api-docs.json`
- **OpenAPI server URL:** `.env` fayldagi `APP_URL` qiymatiga bog'liq:
  ```env
  APP_URL=http://localhost:8000
  ```

---

## 3. 🛡️ Autentifikatsiya (Sanctum Bearer Token)

Swagger UI da himoyalangan API'larni test qilish:
1. `/api/v1/auth/login` endpointi orqali login qiling va `token` qiymatini oling (masalan: `1|xyz...`).
2. Swagger UI sahifasining yuqori o'ng burchagidagi **"Authorize"** tugmasini bosing.
3. `bearerAuth` maydoniga tokenni kiriting (faqat tokenning o'zi, `Bearer ` so'zi kerak emas).
4. **Authorize** -> **Close** tugmalarini bosing. Endi barcha himoyalangan endpointlarga so'rov yuborishingiz mumkin.

---

## 4. 📝 Kodda OpenAPI (PHP 8 Attributes) Sintaksisi

Loyihada zamonaviy **PHP 8 Attributes** (`OpenApi\Attributes as OA`) ishlatiladi.

### A. Asosiy Controller (`app/Http/Controllers/Controller.php`)
Loyiha va API haqidagi umumiy ma'lumotlar shu yerda belgilangan:

```php
use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Taskera Enterprise ITSM API',
    description: 'Taskera Enterprise ITSM API Documentation',
    contact: new OA\Contact(email: 'admin@taskera.uz')
)]
#[OA\Server(
    url: L5_SWAGGER_CONST_HOST,
    description: 'Taskera API Server'
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Sanctum'
)]
abstract class Controller
{
    //
}
```

---

### B. GET Endpoint namunasi (Ro'yxat olish yoki ID bo'yicha qidirish)

```php
use OpenApi\Attributes as OA;

#[OA\Get(
    path: '/api/v1/tickets',
    summary: 'Tiketlar ro\'yxatini olish',
    description: 'Foydalanuvchi huquqiga mos tiketlar ro\'yxatini qaytaradi',
    tags: ['Tickets'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'status_id',
            in: 'query',
            required: false,
            description: 'Status bo\'yicha filter',
            schema: new OA\Schema(type: 'integer')
        ),
        new OA\Parameter(
            name: 'page',
            in: 'query',
            required: false,
            description: 'Sahifa raqami',
            schema: new OA\Schema(type: 'integer', default: 1)
        ),
    ],
    responses: [
        new OA\Response(
            response: 200,
            description: 'Muvaffaqiyatli javob',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
                ]
            )
        ),
        new OA\Response(response: 401, description: 'Autentifikatsiyadan o\'tilmagan'),
    ]
)]
public function index(Request $request): JsonResponse
{
    // ...
}
```

---

### C. POST Endpoint namunasi (Yangi ma'lumot yaratish)

```php
use OpenApi\Attributes as OA;

#[OA\Post(
    path: '/api/v1/tickets',
    summary: 'Yangi tiket yaratish',
    tags: ['Tickets'],
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['subject', 'description', 'priority_id'],
            properties: [
                new OA\Property(property: 'subject', type: 'string', example: 'Internet sekin ishlamoqda'),
                new OA\Property(property: 'description', type: 'string', example: '102-xona kompyuterida internet tezligi juda past.'),
                new OA\Property(property: 'category_id', type: 'integer', example: 1),
                new OA\Property(property: 'priority_id', type: 'integer', example: 2),
            ]
        )
    ),
    responses: [
        new OA\Response(response: 201, description: 'Tiket muvaffaqiyatli yaratildi'),
        new OA\Response(response: 422, description: 'Validatsiya xatosi'),
        new OA\Response(response: 401, description: 'Autentifikatsiyadan o\'tilmagan'),
    ]
)]
public function store(Request $request): JsonResponse
{
    // ...
}
```

---

### D. PUT / PATCH Endpoint namunasi (Tahrirlash)

```php
use OpenApi\Attributes as OA;

#[OA\Put(
    path: '/api/v1/tickets/{id}',
    summary: 'Tiketni tahrirlash',
    tags: ['Tickets'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            description: 'Tiket ID si',
            schema: new OA\Schema(type: 'integer')
        ),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'subject', type: 'string', example: 'Yangi mavzu'),
                new OA\Property(property: 'description', type: 'string', example: 'Yangi tavsif'),
            ]
        )
    ),
    responses: [
        new OA\Response(response: 200, description: 'Tiket yangilandi'),
        new OA\Response(response: 404, description: 'Tiket topilmadi'),
    ]
)]
public function update(Request $request, int $id): JsonResponse
{
    // ...
}
```

---

### E. DELETE Endpoint namunasi (O'chirish)

```php
use OpenApi\Attributes as OA;

#[OA\Delete(
    path: '/api/v1/tickets/{id}',
    summary: 'Tiketni o\'chirish',
    tags: ['Tickets'],
    security: [['bearerAuth' => []]],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            description: 'Tiket ID si',
            schema: new OA\Schema(type: 'integer')
        ),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Tiket o\'chirildi'),
        new OA\Response(response: 404, description: 'Tiket topilmadi'),
    ]
)]
public function destroy(int $id): JsonResponse
{
    // ...
}
```

---

## 5. 💡 Foydali Maslahatlar

1. **Guruhlash (Tags):** Har bir controller classining tepasiga `#[OA\Tag(name: 'Nomi', description: 'Tavsifi')]` qo'ying. Bu Swagger UI da endpointlarni chiroyli bo'limlarga ajratadi.
2. **Auto-Generate (Dev muhitda):** `config/l5-swagger.php` ichida `'generate_always' => env('L5_SWAGGER_GENERATE_ALWAYS', false)` mavjud. Agar `.env` faylga `L5_SWAGGER_GENERATE_ALWAYS=true` qo'shsangiz, har safar `/api/documentation` sahifasi ochilganda avtomatik qayta generatsiya qilinadi.
3. **Validatsiya va Error Response:** Muhim endpointlarda `400`, `401`, `403`, `422`, `500` status kodlarini ko'rsatib o'tish frontend dasturchilar uchun juda qulay bo'ladi.
