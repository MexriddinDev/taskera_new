# BACKEND AUDIT NATIJASI

> **TUZATISH HOLATI (2026-09-11).** Kod bilan hal qilinganlar: **Y-2** (PINFL qidiruviga `throttle:10,1`),
> **O-3** (N+1 qo'riqchisi — log rejimida), **O-4** (Telegram webhook siri endi majburiy),
> **P-4** (`prepare` javobidan token prefiksi olib tashlandi), **K-1 ning yarmi**
> (`.env.bak-163201` git indeksidan chiqarildi, `.gitignore` ga naqsh qo'shildi).
> **Sizdan kutilmoqda:** K-1 — parollarni almashtirish va git tarixini tozalash;
> Y-1/Y-3/O-1/O-2/O-5 — `.env` va server sozlamalari; P-1 (pint), P-2 (PHPStan),
> P-5 (idempotentlik), P-6 (unit testlar) — alohida ish sifatida.

**Sana:** 2026-09-11 · **Asos:** `back_test.md` (17 bo'lim)
**Qamrov:** faqat xavfsiz tekshiruvlar — kod o'qish, statik tahlil, mavjud test to'plami, konfiguratsiya.
**Bajarilmadi (kelishuv bo'yicha):** bazani to'xtatish, port skanerlash, jonli yuklama, disk to'ldirish.
**Audit paytida kod o'zgartirilmadi** — tuzatishlar keyin, yuqoridagi holat bloki bo'yicha kiritildi.

---

## XULOSA

| Jiddiylik | Soni |
|---|---|
| 🔴 Kritik | 1 |
| 🟠 Yuqori | 3 |
| 🟡 O'rta | 5 |
| 🔵 Past / kuzatuv | 6 |

Umumiy holat: **asos mustahkam** — race condition himoyasi, tranzaksiyalar, SQL injection'dan himoya, huquqlar tizimi va 88 ta avtomatik test bor. Asosiy muammolar **konfiguratsiya va maxfiy ma'lumotlarni saqlashda**, kod mantiqida emas.

---

## 🔴 KRITIK

### K-1. `.env` nusxasi maxfiy ma'lumotlar bilan git'ga tushgan

**Fayl:** `backend/.env.bak-163201` (144 qator) — `git ls-files` da bor, ya'ni repozitoriyga **commit qilingan**.
**Commit:** `0db4b661`

Ichidagi to'ldirilgan maxfiy kalitlar:

```
APP_KEY, DB_PASSWORD, TELEGRAM_BOT_TOKEN, SUPERADMIN_PASSWORD,
LDAP_SERVICE_PASS, SSO_CLIENT_SECRET, SMS_PASSWORD, EXCHANGE_LDAP_PASS
```

`LDAP_SERVICE_PASS` — bu `administrator@adatum.com` (domen administratori) paroli.

**Nima uchun muhim:** repozitoriyga kirish huquqi bor har bir odam (va kelajakda klon qiladigan har kim) domen administratori parolini, baza parolini va SSO sirini oladi. Faylni o'chirish **yetarli emas** — u git tarixida qoladi.

**Tavsiya (shu tartibda):**
1. Ro'yxatdagi **barcha parol va tokenlarni almashtirish** (eng avval LDAP admin va DB).
2. `APP_KEY` almashtirilsa — shu kalit bilan shifrlangan ma'lumotlar (masalan Finesse parollari) qayta yozilishi kerak.
3. Faylni git tarixidan tozalash (`git filter-repo` yoki BFG) va majburiy push.
4. `.gitignore` ga `.env.bak*`, `.env.*` naqshlarini qo'shish.

> `.env` ning o'zi to'g'ri ignor qilingan (`.gitignore:3`) — muammo faqat zaxira nusxada.

---

## 🟠 YUQORI

### Y-1. `APP_DEBUG=true` — xato sahifasi baza ma'lumotlarini ko'rsatadi

**Dalil:** shu sessiyada brauzerda quyidagi xato chiqdi:

```
SQLSTATE[42S22]: Unknown column 's.is_default' ...
(Connection: mysql, Host: 127.0.0.1, Port: 3306, Database: jira_pdf_transmitter, SQL: select ...)
```

Ya'ni oddiy foydalanuvchi **baza nomi, host, port va to'liq SQL so'rovni** ko'rdi. `back_test.md` §4 va §14 aynan shuni taqiqlaydi.

**Tavsiya:** ekspluatatsiya muhitida `APP_DEBUG=false`. Loyihada buni tekshiradigan **tayyor buyruq bor** — `php artisan deploy:check`.

### Y-2. Autentifikatsiyasiz PINFL bo'yicha xodim qidirish — cheklovsiz

**Marshrutlar:** `POST /api/v1/ad-account/check-employee`, `POST /api/v1/ad-account/check-bxm`
**Fayl:** `app/Http/Controllers/Api/AdAccountController.php:54, 122`

Bu ikkalasi **auth talab qilmaydi va throttle ham yo'q** (yonidagi `send-code` va `verify-code` da throttle bor). PINFL — 14 raqam, lekin tug'ilgan sana va jins bo'yicha tuzilishi ma'lum, ya'ni maqsadli tanlab urinish mumkin. Javobda xodimning F.I.Sh, telefon, bo'lim ma'lumotlari qaytadi.

**Tavsiya:** ikkala marshrutga IP bo'yicha throttle qo'yish (masalan `throttle:5,1`) va muvaffaqiyatsiz urinishlarni loglash.

### Y-3. TLS sertifikat tekshiruvi uchta integratsiyada o'chirilgan

```
SSO_VERIFY_SSL=false
SMS_VERIFY_SSL=false
FINESSE_VERIFY_TLS=false
```

SSO orqali token, SMS orqali tasdiqlash kodlari o'tadi. Sertifikat tekshirilmasa, tarmoq ichidagi MITM hujumi bilan ular ushlanishi mumkin.

**Tavsiya:** ichki CA sertifikatini serverga o'rnatib, uchalasini `true` qilish.

---

## 🟡 O'RTA

### O-1. LDAP xizmat hisobi — domen administratori

`LDAP_SERVICE_USER=administrator@adatum.com`. LDAP bind va Exchange operatsiyalari uchun domen admini ishlatilyapti. Ilova buzilsa — butun domen ochiladi.

**Tavsiya:** faqat kerakli huquqlari bor alohida xizmat hisobi (read + kerakli OU'ga yozish).

### O-2. `APP_URL=http://localhost`

Tizim lokal IP orqali ishlaydi (`back_test.md` §1), lekin `APP_URL` `localhost` da qolgan. Bu **imzolangan havolalarga** ta'sir qiladi — `attachments/{id}/download` va `permits/{id}/photo` aynan `ValidateSignature` bilan himoyalangan; boshqa kompyuterdan ochilganda havola noto'g'ri generatsiya bo'lishi mumkin.

**Tavsiya:** `APP_URL` ni haqiqiy `http://<server-ip>:<port>` ga qo'yish. `FRONTEND_URL` ham belgilanmagan (CORS localhost defaultiga tushadi).

### O-3. N+1 so'rovlarga qarshi qo'riqchi yo'q

`Model::preventLazyLoading()` hech qayerda yoqilmagan. Ya'ni dasturchi `with()` ni unutsa, ro'yxat sahifasi jimgina 100+ so'rov yuboraveradi va buni hech kim sezmaydi (`back_test.md` §13).

**Tavsiya:** `AppServiceProvider::boot()` da `Model::preventLazyLoading(! app()->isProduction())` — lokal muhitda xato beradi, ekspluatatsiyada jim qoladi.

### O-4. Telegram webhook siri majburiy emas

**Fayl:** `TelegramWebhookController.php:26`

```php
if ($bot->webhook_secret_hash && hash('sha256', $secret) !== $bot->webhook_secret_hash) { ... 401 }
```

Agar `webhook_secret_hash` **NULL** bo'lsa — tekshiruv butunlay o'tkazib yuboriladi va har kim Telegram nomidan xabar yuborishi mumkin.

**Hozirgi holat xavfsiz:** bazadagi yagona bot (`xbhelpbot`) da sir bor. Lekin yangi bot qo'shilganda sir qo'yilmasa — ochiq qoladi.

**Tavsiya:** sir yo'q bo'lsa so'rovni rad etish (`if (! $bot->webhook_secret_hash || ...)`).

### O-5. `memory_limit = 128M`

`upload_max_filesize=60M`, `post_max_size=100M` bo'lgani holda PHP xotira chegarasi 128M. Katta fayl + ovoz + video bir so'rovda kelsa (zayavka oynasi aynan shunga ruxsat beradi) xotira tugashi mumkin.

**Tavsiya:** `memory_limit` ni kamida 256M ga ko'tarish.

---

## 🔵 PAST / KUZATUV

### P-1. Kod uslubi — 212 faylda nomuvofiqlik

`./vendor/bin/pint --test` natijasi:

| Tuzatuvchi | Fayllar |
|---|---|
| `ordered_imports` | 123 |
| `fully_qualified_strict_types` | 83 |
| `concat_space` | 53 |
| `function_declaration` | 51 |
| `line_ending` | 38 (CRLF/LF aralash) |

Faqat kosmetik, xatti-harakatga ta'sir qilmaydi. `./vendor/bin/pint` bitta buyruq bilan tuzatadi — lekin 212 faylni bir commitda o'zgartiradi, shuning uchun alohida "style" commit sifatida qilish tavsiya etiladi.

### P-2. Statik tahlil vositasi umuman yo'q

`composer.json` da **PHPStan ham, Psalm ham yo'q** (`back_test.md` §2). Ya'ni type error, null qiymat, mavjud bo'lmagan metod kabi xatolar faqat ishga tushganda chiqadi.

**Tavsiya:** `composer require --dev phpstan/phpstan larastan/larastan` va level 5 dan boshlash.

### P-3. `SESSION_ENCRYPT=false`

Session drayveri — `database`, ya'ni ma'lumot bazada ochiq saqlanadi. Lokal tarmoq uchun jiddiy emas, lekin `back_test.md` §12 buni tekshirishni talab qiladi.

### P-4. `prepare` endpointi token prefiksini qaytaradi

`AdAccountController.php:348` — auth'siz chaqiriladigan endpoint javobida SSO tokenining birinchi 10 belgisi (`token_prefix`) qaytariladi. To'liq token emas, lekin keraksiz ma'lumot; ustiga har kim SSO token olishni ishga tushira oladi.

### P-5. Takroriy so'rov (§17) — faqat frontendda himoyalangan

Zayavka yaratishda idempotentlik kaliti yo'q. Ikki marta bosishdan himoya faqat frontend tugmasini bloklashda (`disabled={createTaskMutation.isPending}`). To'g'ridan-to'g'ri API'ga ikkita bir xil so'rov yuborilsa — ikkita zayavka yaratiladi.

> Telegram tomonida bu **to'g'ri hal qilingan**: `telegram_updates` jadvalida `update_id` bo'yicha `insertOrIgnore` + `PROCESSED` holati tekshiriladi.

### P-6. `tests/Unit` deyarli bo'sh

Faqat `ExampleTest.php`. Barcha 88 test — Feature darajasida (baza bilan). `back_test.md` §5 talab qiladigan sof unit testlar yo'q. Jiddiy emas (Feature testlar ko'proq narsani qamrab oladi), lekin SLA hisoblash kabi sof mantiqni bazasiz test qilish tezroq bo'lardi.

---

## ✅ YAXSHI BAJARILGAN (tekshirildi va muammo topilmadi)

| Bo'lim | Holat |
|---|---|
| §9 SQL injection | **Toza.** Barcha `whereRaw`/`selectRaw`/`DB::raw` ishlatilishi bindings (`?`) yoki `(int)` cast bilan. Foydalanuvchi kiritmasi hech qayerda SQL'ga to'g'ridan-to'g'ri qo'shilmaydi. |
| §11 Huquqlar | 278 ta `api/v1` marshrutidan 248 tasi `Authenticate:sanctum` bilan; yozuv amallari ustiga `CheckPermission` middleware. Auth'siz 30 tasining 18 tasi — faqat o'qiladigan `references/*` ro'yxatlari. |
| §13 Tranzaksiya | Zayavka yaratish, biriktirish, holat o'zgartirish, izoh qo'shish, biriktirma — hammasi `DB::transaction` ichida. |
| §16 Race condition | Zayavka raqami (`TicketRepository:46`) va biriktirish (`AssignTicketService:28`) `lockForUpdate()` bilan qulflangan. Ikki operator bir zayavkani olsa — ikkinchisidan **sabab talab qilinadi**. |
| §7 Feature test | 88 test, 268 assertion — hammasi o'tadi. Xavfsizlik uchun alohida papka: `tests/Feature/Security/` (9 fayl). |
| §14 Xato javoblari | `bootstrap/app.php:63` — API uchun JSON render, 401/403/404 uchun maxsus xabarlar. Muammo faqat `APP_DEBUG` da (Y-1). |
| Finesse qo'ng'irog'i | Raqam so'rovdan emas, zayavkadan olinadi (`FinesseController.php:118`) — mijozga ishonilmaydi. |
| Deploy tekshiruvi | `php artisan deploy:check` — tayyor buyruq, 7 ta konfiguratsiya muammosini aniqlaydi. |

---

## TAVSIYA ETILGAN TARTIB

1. **Bugun:** K-1 — parollarni almashtirish, `.env.bak` ni tarixdan tozalash.
2. **Ekspluatatsiyaga chiqishdan oldin:** Y-1, Y-3, O-2 — `php artisan deploy:check` toza o'tsin.
3. **Shu hafta:** Y-2 (throttle), O-4 (webhook siri), O-5 (memory_limit).
4. **Keyingi sprint:** O-1 (LDAP hisobi), O-3 (N+1 qo'riqchisi), P-2 (PHPStan).
