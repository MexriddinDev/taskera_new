# Ertaga qilinadigan ishlar

> Tuzilgan: 2026-09-10 · Asos: `AUDIT_REPORT.md` (2026-09-07) + **2026-09-10 da kod bo'yicha qayta tekshirish**
>
> Audit hisoboti 3 kunlik. Undagi topilmalarning har biri bugun kod ochib tekshirildi —
> quyida faqat **hozir ham tirik** bo'lganlari bor. Tuzatilganlari 6-bo'limda.

---

## 0. Qabul qilingan qarorlar

| Savol | Qaror | Oqibati |
|---|---|---|
| Ko'p tashkilotlilik (multi-tenant) haqiqiy talabmi? | **HA** — kelajakda boshqa tashkilotlar qo'shiladi | **C-02 → P0**. Global scope arxitekturasi kerak, "arzon himoya" yetarli emas |
| Ish tartibi | Avval **tez g'alabalar**, keyin **IDOR'lar** | Partiya 1 → 2 → 3 → 4 |

Hozirgi holat: **1 ta tashkilot, 9 foydalanuvchi, 56 zayavka**. Ya'ni tenant muammosi
bugun amalda zarar keltirmayapti — lekin ikkinchi tashkilot qo'shilgan kuni keltiradi,
va o'shanda tuzatish ancha qimmatroq bo'ladi.

---

## 1.0 — 2026-09-11 holati

**Partiya 1 bajarildi**, `1.2` dan tashqari. Test to'plami: **70/70 o'tadi**
(avval 55 ta edi, 53 tasi xato berardi — pastga qarang).

| Band | Holat | Tekshiruv natijasi |
|---|---|---|
| 1.1 `/register` | ✅ | `route:list \| grep register` → bo'sh; `/register` → 404. `SelfRegistrationDisabledTest` |
| 1.2 superadmin bypass | ⏸ | **Sizda.** Kodga tegilmadi — sabab pastda |
| 1.3 token TTL | ✅ | `SANCTUM_EXPIRATION=720` (12 soat); muddati o'tgan token → 401; frontend `/login` ga qaytaradi. `SanctumTokenExpirationTest` |
| 1.4 production config | ✅ | `php artisan deploy:check` — lokal muhitda 7 ta muammo topib exit 1 qaytaradi. `ProductionConfigCheckTest` (10 test) |
| 1.5 bog'liqliklar | 🟡 | `composer audit` → **0**. `npm audit` → 5 dan 4 ga tushdi |

### Avval tuzatilgan ikki blokirovka (hisobotda yo'q)

Partiya 1 dan oldin test to'plami ishlamasdi, ya'ni hech narsani tekshirib
bo'lmasdi. Ikkalasi ham 2026-09-10 dagi ishning regressiyasi:

1. `2026_09_14_000002_backfill_ticket_requester_employee_id.php` — `UPDATE ... JOIN`
   ishlatardi, SQLite'da (test bazasi) `SET` ichida qo'shilgan jadval ustuni
   ko'rinmaydi → **55 testdan 53 tasi xato**. Foydalanuvchi bo'yicha aylanadigan
   portativ variantga o'tkazildi
2. `PermitTest` hali `full_name` yuborardi, F.I.Sh esa uchta maydonga ajratilgan
   → 2 test yiqilardi. Test yangi API'ga moslandi

### 1.2 nega to'xtatilgan

Jonli bazada `superadmin` (user 1) da **"Super Admin" roli yo'q** — unda
`support` (role_id=5) turibdi. Ya'ni `isSuperAdmin()` hozir **faqat** username
bypass tufayli `true` qaytaryapti. Bypass olib tashlansa superadmin darhol
huquqsiz qoladi.

➡️ Rol biriktirilgandan keyin `User.php:133-137` dagi username tekshiruvi
olib tashlanadi (bir qatorlik ish).

### 1.5 da nima qolgan

`nanoid` (high) tuzatildi, `league/commonmark` → **2.10.1**. Qolgan 4 advisory
major sakrash talab qiladi va shu sababli alohida ishga qoldirildi:

| Paket | Hozir | Kerak | Advisory |
|---|---|---|---|
| `vite` | 5.4.x | **8.3.0** | 1 high + 1 moderate (esbuild) — ikkalasi ham **dev-server**, production build'ga tegmaydi |
| `react-router-dom` | 6.28.x | **7.18.3** | 2 moderate — open redirect va SSR hydration (**SSR ishlatilmaydi**) |

Yangilagandan keyin barcha marshrutlarni qo'lda sinash kerak.

---

## PARTIYA 1 — Tez g'alabalar

> Bir necha soatlik ish, xavfning eng katta qismini yopadi. Shundan boshlanadi.

### 1.1 — `/register` ni yopish `[C-01]` ✅ BAJARILDI (2026-09-11)

**Muammo:** `backend/routes/web.php:30-31` — `/register` GET va POST **hech qanday
middleware'siz** (hatto `guest` ham yo'q). `RegisterController.php` istalgan kishiga
`auth_source=LOCAL`, `status=ACTIVE` hisob ochib beradi va `:70` da darhol
`Auth::login()` qiladi. Parol minimumi **6 belgi** (`:25`).

Ya'ni tashqi shaxs korporativ AD'ni, HR provisioningni va hisob holati nazoratini
butunlay chetlab o'tib tizimga kiradi.

**Yechim:**
1. `routes/web.php:30-31` dagi ikkala marshrutni **o'chirish**
2. `RegisterController.php` ni o'chirish (yoki agar invite-only oqim kerak bo'lsa —
   alohida, tasdiqlangan korporativ identifikator va rate-limit bilan qayta yozish)
3. Frontendda `/register` ga havola bor-yo'qligini tekshirish

**Tekshiruv:** `php artisan route:list | grep register` → bo'sh. Brauzerda `/register`
→ 404.

---

### 1.2 — `isSuperAdmin()` dagi username bypass `[C-01 ayrim]` ⏸ TO'XTATILGAN

**Muammo:** `backend/app/Models/User.php:133-137`:

```php
if (strtolower((string) $this->username) === 'superadmin') {
    return true;   // rol umuman tekshirilmaydi
}
```

Hozir bu nom band, shuning uchun darhol zarar yo'q. Lekin bootstrap hisobi
o'chirilsa/yumshoq o'chirilsa yoki yangi bazada — `/register` orqali `superadmin`
nomini olgan kishi **to'liq super admin** bo'ladi.

**Yechim:** username tekshiruvini olib tashlash, faqat rol/permission qolsin.

**Diqqat:** `EnterpriseDemoSeeder` yaratgan `superadmin` foydalanuvchisiga
"Super Admin" **roli biriktirilganini** avval tasdiqlang — aks holda o'zgarishdan
keyin superadmin huquqsiz qoladi.

**Tekshiruv:** superadmin bilan kirib admin sahifalari ochilishini tekshirish;
`superadmin2` kabi nom bilan yaratilgan test foydalanuvchida `isSuperAdmin() === false`.

---

### 1.3 — Sanctum tokeni muddatsiz `[H-10]` ✅ BAJARILDI (2026-09-11)

**Muammo:** `backend/config/sanctum.php:53` — `'expiration' => null`. O'g'irlangan
token qo'lda bekor qilinmaguncha abadiy amal qiladi. Frontend tokenni
`localStorage` da saqlaydi (`frontend/src/shared/infrastructure/http/axiosClient.ts`),
ya'ni XSS ta'siri kuchayadi.

**Yechim:** `expiration` ni daqiqada belgilash (masalan `60 * 12` — 12 soat).
Frontend 401 ni ushlab login sahifasiga yuborishini tekshirish.

**Tekshiruv:** muddati o'tgan token bilan so'rov → 401; foydalanuvchi login sahifasiga
qaytariladi, oq ekran qolmaydi.

---

### 1.4 — Production konfiguratsiyasi `[M-01]` ✅ BAJARILDI (2026-09-11)

**Muammo:** hozir `APP_ENV=local`, `APP_DEBUG=true`. Shu holda deploy qilinsa xato
sahifalari kod, konfiguratsiya va ba'zan credential ko'rsatadi. Bundan tashqari
`SSO_VERIFY_SSL=false`, `SMS_VERIFY_SSL=false`.

**Yechim:** production `.env` uchun alohida qiymatlar; deploy oldidan tekshiruvchi
skript (`APP_DEBUG=false`, `APP_ENV=production`, `verify_ssl` yoqilgan, `APP_URL`
to'g'ri, `FRONTEND_URL` majburiy).

**Tekshiruv:** production muhitida ataylab xato yuzaga keltirib, javobda stack trace
YO'Qligini ko'rish.

---

### 1.5 — Bog'liqlik zaifliklari `[H-08, H-09]` 🟡 QISMAN (2026-09-11)

**Muammo (backend):** `league/commonmark 2.8.3` — 10 ta advisory (8 high, jumladan
XSS va DoS). Kerak: `>= 2.10.0`. Paket Laravel orqali keladi.

**Muammo (frontend):** `npm audit` → **5 ta zaiflik (2 high, 3 moderate)** — Vite
(Windows path traversal), nanoid (DoS), React Router/DOM, esbuild.

**Yechim:** `composer update league/commonmark` va frontend lockfile'ni yangilash.
React Router yangilanishi buzuvchi bo'lishi mumkin — yangilagandan keyin marshrutlarni
qo'lda sinash kerak.

**Tekshiruv:** `composer audit --locked` va `npm audit` → 0 high. Keyin
`npx tsc --noEmit` + `npx vite build` + `php artisan test` o'tishi shart.

---

## PARTIYA 2 — Tenant izolyatsiyasi `[C-02]` 🔴 P0

> Ko'p tashkilotlilik haqiqiy talab bo'lgani uchun bu eng katta va eng muhim ish.
> Partiya 1 dan keyin, IDOR'lardan **oldin** — chunki to'g'ri qilingan tenant scope
> quyidagi IDOR'larning bir qismini ham yopadi.

**Muammo:**
- `SetOrganizationContextMiddleware.php:18-20` — `SET LOCAL app.current_organization_id`
  faqat PostgreSQL uchun bajariladi. Amaldagi baza — **MariaDB**, ya'ni hech narsa
  qilinmaydi. (Ustiga: `SET LOCAL` tranzaksiyadan tashqarida umuman ta'sirsiz.)
- Eloquent'da global scope **yo'q**: `addGlobalScope` va `BelongsToOrganization`
  bo'yicha qidiruv — 0 natija.
- **32 ta controller** `organization_id` ni client so'rov parametri sifatida qabul
  qiladi, va u **ixtiyoriy filtr** shaklida:
  ```php
  ->when($request->filled('organization_id'), fn($q) => $q->where(...))
  ```
  Parametr yuborilmasa — **hech qanday org filtri qo'llanmaydi**, ya'ni hamma
  tashkilot ma'lumoti qaytadi. Yuborilsa — boshqa tashkilotni tanlash mumkin.

**32 ta controller ro'yxati** (hammasi `index()` da):
`ApprovalRequest`, `Asset`, `AssetModel`, `AuditLog`, `AutomationRule`, `Branch`,
`CalendarEvent`, `Category`, `Change`, `Chat`, `Employee`, `Integration`,
`KnowledgeArticle`, `Location`, `MaintenanceWindow`, `Manufacturer`, `Position`,
`Problem`, `Region`, `ResolutionCode`, `ServiceCatalogItem`, `Service`,
`ServiceOffering`, `SoftwareLicense`, `SoftwareProduct`, `Tag`, `Task`, `Vendor`,
`WebhookEndpoint`, `Workflow`, `Notification`, `NotificationTemplate`

**To'g'ri qilingan namunalar** (shulardan nusxa oling):
`PermitController.php:30,73,91` va `FinesseController.php:108,192` — ikkalasi ham
`CurrentOrg::id($request)` bilan **majburiy** filtr qo'yadi.

**Yechim (bosqichma-bosqich):**
1. `app/Models/Concerns/BelongsToOrganization.php` trait: `booted()` da
   `addGlobalScope` (o'qishda) + `creating` hook (yozishda `organization_id` ni
   avtomatik qo'yish)
2. Traitni barcha tenant modellariga qo'shish
3. 32 controllerdan client `organization_id` parametrini **olib tashlash**
4. `exists:` validatsiyalarini org bilan cheklash (`Rule::exists(...)->where('organization_id', ...)`)
5. Cross-tenant kerak bo'lsa — alohida, auditlanadigan super-admin API

**Tekshiruv (majburiy):** ikkinchi test tashkiloti va unda foydalanuvchi yaratib,
**ikki-tenantli regressiya testi** yozish: A tashkiloti foydalanuvchisi B ning
zayavkasini/xodimini/aktivini na ro'yxatda ko'rsin, na ID orqali ocha olsin.
Bu testsiz ish tugagan hisoblanmaydi — hozirgi testlar bitta org'li SQLite'da
ishlagani uchun aynan shu muammoni **o'tkazib yuborgan** (`M-08`).

---

## PARTIYA 3 — IDOR va avtorizatsiya

> Har biri alohida o'ylashni talab qiladi. Partiya 2 dan keyin, chunki global scope
> bularning org qismini allaqachon yopgan bo'ladi — qolgani **foydalanuvchi darajasidagi**
> tekshiruv.

### 3.1 — Approval'ni istalgan kishi tasdiqlaydi `[C-04]` 🔴

`backend/app/Http/Controllers/Api/ApprovalRequestController.php:48-66` (approve),
`:68-86` (reject). Ikkalasi ham `findOrFail($id)` qilib darhol statusni yozadi.
Tekshirilmaydi: joriy foydalanuvchi tasdiqlovchimi, qadam navbati, tashkilot,
joriy status `PENDING` mi. `decision_by` validatsiyada bor (`:54`) lekin
**umuman ishlatilmaydi**; `comment` ham yozilmaydi.

Marshrutlarda (`routes/api.php:405-406`) **permission middleware yo'q**.

**Yechim:** Policy (`canDecide`) + faol qadam + `PENDING` guard + tranzaksiya va
pessimistic lock + qaror/audit yozuvi + idempotentlik. Aktor har doim
autentifikatsiyalangan foydalanuvchidan olinsin, so'rovdan emas.

**Tekshiruv:** begona foydalanuvchi approve qilmoqchi → 403; ikki marta approve →
ikkinchisi rad etiladi; `decision_by` va `comment` bazaga yoziladi.

---

### 3.2 — Notification IDOR `[C-03]` 🔴

`app/Modules/Notification/.../NotificationController.php`:
- `:20-28` — `index()` da egalik cheklovi yo'q; `notifiable_id` faqat ixtiyoriy filtr
- `:43` — `show()` oddiy `findOrFail`
- `:50-57` — `markAsRead()` egalikni ham tekshirmaydi

**Yechim:** so'rovni majburiy ravishda
`notifiable_type = User::class`, `notifiable_id = $request->user()->id` bilan
cheklash. Admin ko'rinishi kerak bo'lsa — alohida permission bilan.

**Tekshiruv:** A foydalanuvchi B ning bildirishnomasini ID orqali ocholmasin (403/404).

---

### 3.3 — `markAsRead` umuman ishlamaydi `[H-05]` 🟠

Yuqoridagi fayl `:50-58` — faqat `findOrFail` qilib "Marked as read" deb javob
qaytaradi, **hech narsa yozmaydi**. Sababi: `notifications` jadvalida `read_at`
ustuni **yo'q** (`0000_00_00_000090_create_notification_tables.php:33-49` — faqat
`created_at`, `expires_at`).

**Yechim:** migration bilan `read_at` qo'shish (yoki per-user read jadvali, agar bitta
bildirishnoma bir necha kishiga ketsa) va controllerda haqiqatan yozish.

**Tekshiruv:** o'qilgan deb belgilangandan keyin bazada `read_at` to'lgan bo'lsin va
qayta yuklashda ham o'qilgan bo'lib qolsin.

---

### 3.4 — Profile summary IDOR `[H-03]` 🟠

`app/Http/Controllers/Api/ProfileController.php` — `show()` da `:29-33` da
`$isSelf`/`$isStaff` tekshiruvi **bor**, lekin `summary()` da (`:63-73`) **yo'q**.
`GET /users/{id}/summary` (`routes/api.php:121`) orqali istalgan kishi boshqa
foydalanuvchining statistikasini va oxirgi 3 ta zayavkasi mavzusini oladi.

**Yechim:** `show()` dagi aynan o'sha tekshiruvni `summary()` ga ham qo'llash.

**Tekshiruv:** begona foydalanuvchi uchun 403.

---

### 3.5 — Web izoh marshruti API himoyasini aylanib o'tadi `[H-04]` 🟠

`backend/routes/web.php:181-191` — `POST /tickets/{id}/comments` closure'i
`getAccessibleTicketsQuery()` ni **ishlatmaydi** (taqqoslash uchun: `:167-169` dagi
`show` marshruti ishlatadi). Ya'ni ticket accessi tekshirilmaydi. Ustiga `:184` da
`'organization_id' => 1` **qattiq yozilgan**.

**Yechim:** closure'ni olib tashlab, avtorizatsiyasi bor yagona controller/servisdan
o'tkazish (API'dagi `CommentController` bilan bir xil).

**Tekshiruv:** begona zayavkaga web orqali izoh yozib bo'lmasin.

---

### 3.6 — Calendar event IDOR `[H-01]` 🟠

`app/Http/Controllers/Api/CalendarEventController.php:19-29` — `index` da scoping yo'q;
`:76`, `:85`, `:107` — `show/update/destroy` da `findOrFail` dan boshqa hech narsa yo'q.
Istalgan foydalanuvchi begona tadbirni o'zgartiradi/o'chiradi.

**Yechim:** Policy (yaratuvchi yoki ishtirokchi) + joriy tashkilot scope.

**Tekshiruv:** begona tadbir uchun update/destroy → 403.

---

### 3.7 — Service request IDOR `[H-02]` 🟠

`app/Http/Controllers/Api/ServiceRequestController.php:39` —
`ServiceRequest::with([...])->findOrFail($id)`, bog'liq ticket egaligi tekshirilmaydi.
`index` (`:18-24`) ham scoping'siz.

**Yechim:** so'rovni foydalanuvchi kira oladigan ticketlar subquery'si bilan cheklash.

**Eslatma:** hisobotdagi `TicketSlaController` qismi **bekor** — u fayl 2026-09-08 da
o'chirilgan va marshruti yo'q.

**Tekshiruv:** begona ticketning service-request yozuvi ID orqali ochilmasin.

---

## PARTIYA 4 — Qolganlar

### 4.1 — LDAP sertifikat tekshiruvi o'chirilgan `[C-05]` 🔴

`app/Services/AdAuthService.php:105-106, 129-130` va
`ExchangeMailService.php:48-49, 850-851`:
```php
ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_NEVER);
putenv('LDAPTLS_REQCERT=never');
```

> ⚠️ **DIQQAT — soxta tuzatish bor.** `AdAuthService.php:36-37` da konstruktorda
> `config('services.ad.tls_require_cert', 'never')` qo'shilgan, ya'ni **sozlanadigandek
> ko'rinadi**. Lekin `:105` va `:129` dagi qattiq `NEVER` konstruktordagi qiymatni
> **ustidan qayta yozadi** — sozlama amalda ta'sirsiz. Kodni ko'rgan odam buni
> "tuzatilgan" deb o'ylashi mumkin. Ikkala joyni ham tuzatish kerak.

**Xavf:** MITM hujumchi service-account va foydalanuvchi parollarini o'g'irlashi,
LDAP javoblarini almashtirishi mumkin. Hozir port 389 (`ldap://`) ishlatiladi.

**Yechim:** LDAPS 636 yoki StartTLS; korporativ CA'ni trust store'ga qo'shish;
`DEMAND`/`HARD` tekshiruv; xato bo'lsa **fail-closed** (ochib yubormaslik).
Service credentialni rotate qilish.

**Tekshiruv:** noto'g'ri sertifikat bilan ulanish **rad etilsin**, o'tib ketmasin.

---

### 4.2 — Attachment `public` diskda `[C-06]` 🟠

`app/Http/Controllers/Api/AttachmentController.php:37` — `$disk = 'public'`.
Hozir `backend/public/storage` symlinki **yo'q**, shuning uchun fayllar tashqaridan
ochilmaydi. **Lekin bu kod himoyasi emas, deploy tasodifi** — production'da
`php artisan storage:link` bajarilsa, imzolangan URL himoyasi butunlay aylanib
o'tiladi va barcha skrinshot/ovoz/hujjat ochiq bo'ladi.

Ayrim: `:29-31`, `:48-51` — `attachable_id` faqat `required|integer`; obyekt
mavjudligi ham, foydalanuvchining unga kirish huquqi ham tekshirilmaydi. Ya'ni
begona zayavkaga fayl biriktirish mumkin.

**Tuzatilgan qismlar (qayta qilmang):** MIME allowlist, UUID fayl nomi,
`signed:relative` yuklab olish, `destroy()` da IDOR himoyasi.

**Yechim:** private diskka o'tkazish + policy tekshiruvidan keyin stream qilish;
`attachable` uchun morph-map va policy.

**Tekshiruv:** `storage:link` bajarilgan holatda ham fayl to'g'ridan-to'g'ri
URL orqali ochilmasin.

---

### 4.3 — Event listenerlar — o'lik kod `[H-07]` 🟠

Ikki qavatli muammo:

**1) Placeholder** — quyidagilar faqat `logger()->info(...)` qiladi:
- `Modules/Audit/Infrastructure/Listeners/WriteAuditLogListener.php:15`
- `Modules/Ticketing/Infrastructure/Listeners/GeneratePreviewListener.php:15`
- `Modules/Ticketing/Infrastructure/Listeners/RecalculateQueueMetricsListener.php:15`
- `Modules/Ticketing/Infrastructure/Listeners/RecordAssignmentHistoryListener.php:15`
- `Modules/Ticketing/Infrastructure/Listeners/RecordStatusHistoryListener.php:15`

**2) Ro'yxatdan o'tmagan** — `app/Providers/AppServiceProvider.php:52` da faqat
`SyncTelegramThreadListener` bog'langan. Qolganlari **hech qachon chaqirilmaydi**.

Ya'ni "event-driven arxitektura" ko'rinadi, lekin amalda ishlamaydi.

`InitializeSlaListener` va `PauseOrResumeSlaListener` fayllari umuman yo'q (faqat
eskirgan `vendor/composer/autoload_classmap.php` da nom qolgan).

**Yechim — ikki yo'ldan biri:**
- **(a)** Kerakli listenerlarni real qilib yozish va ro'yxatdan o'tkazish
- **(b)** Kerak bo'lmasa — **o'chirish**. O'lik placeholder kod "bu funksiya bor"
  degan yolg'on taassurot beradi va keyingi ishlaganni chalg'itadi

➡️ Avval qaysi biri haqiqatan kerakligini hal qiling. Hozir zayavka tarixi va audit
boshqa yo'l bilan (controller ichida to'g'ridan-to'g'ri) yozilyapti, ya'ni bu
listenerlar **dublikat** bo'lishi mumkin.

---

### 4.4 — Kichikroq bandlar

| | Muammo | Fayl |
|---|---|---|
| M-03 | Registratsiya atomik emas, `EMP-` + `rand(1000,9999)` collision | `RegisterController.php:29-62` (1.1 da o'chirilsa — bekor) |
| M-04 | Avatar: cheklanmagan base64 to'g'ridan-to'g'ri bazaga | `AuthController.php:185-205` |
| M-05 | `per_page` da pastki chegara yo'q (`0`, manfiy qabul qilinadi) | ko'p controller |
| M-06 | 21 ta `ShouldQueue` classdan 20 tasida `$tries`/`backoff` yo'q | — |
| M-07 | `npm run lint` ishlamaydi — `eslint` package'da yo'q | `frontend/package.json` |
| — | `TeamController::formatTeam()` N+1 (`withCount` kerak) | `TeamController.php:198-202` |
| — | README hali default Laravel matni | `README.md` |

---

## 5. Ochiq savol — javob kutilmoqda

### Cisco: ikki tomonlama qo'ng'iroq yozuvi

Hozir suhbat yozuvi **brauzer mikrofonidan** olinadi, ya'ni faqat operator ovozi
yoziladi. Bu jismoniy cheklov: suhbatdoshning ovozi telefon liniyasida, brauzer uni
eshitmaydi. Finesse REST API'sida yozib olish endpointi **yo'q** (tekshirilgan:
`/Recording`, `/Recordings` → 404).

**Cisco administratoridan so'ralishi kerak:**

> Qo'ng'iroqlarni yozib oluvchi server bormi? Bo'lsa — qaysi (Cisco WFO/QM yoki
> boshqa) va uning API'si yoki yozuvlar papkasi bormi?

Javob kelsa: yozuvni olib zayavkaga biriktirish qismi yoziladi. Biriktirma mexanizmi
va "faqat xodimlar ko'radi" filtri **allaqachon tayyor**, ular qayta ishlatiladi.

Jabber (softfon) ishlatilgani ma'lum, ya'ni CUCM'ning **Built-in Bridge** (BiB)
yechimi mos keladi — sozlash CUCM tomonida qilinadi, dasturda emas.

---

## 6. Bugun (2026-09-10) qilingan ishlar — audit hisobotida yo'q

> Bu bandlar `AUDIT_REPORT.md` tuzilgandan keyin qilingan. Hisobot ularni bilmaydi.

**Tuzatilgan xatolar:**
- Guruh SLA foizi muddatga umuman qaramasdi (`completed/assigned` hisoblanardi) →
  endi `TicketSlaService` dan, haqiqiy kechikish bo'yicha
- Umumiy SLA KPI'si qat'iy 24 soat shartida edi → endi sozlangan qoidalardan
  (**diqqat:** raqam ~92% dan **26.8%** ga tushdi — bu regressiya emas, avvalgi raqam soxta edi)
- `tickets.requester_employee_id` hech qachon to'ldirilmasdi (49 tadan 0) → `store()`
  da yoziladi + mavjudlari migration bilan to'ldirildi. Bu telefon/pochtaning
  profildan kelishini ta'minlaydi
- Rad etilgan zayavkani boshqa xodimga biriktirib bo'lmasdi (`CLOSED_STATUS_IDS` da 9
  bor edi) → `ASSIGN_LOCKED_STATUS_IDS = [7,8,10]`
- Baholash yozishmaga tushmasdi (faqat audit jurnaliga) → endi ko'k "Baho" yozuvi
  bo'lib chiqadi, izoh bilan birga
- `slaRuleId` string kelib TypeError berardi → `StoreTicketRequest` FormRequest'i

**Qo'shilgan funksiyalar:**
- Cisco Finesse: `Cisco Call` bo'limi (login/parol shifrlangan holda saqlanadi),
  zayavkadan qo'ng'iroq, DROP, suhbat yozuvi (faqat xodimlar ko'radi)
- Elektron ruxsatnoma: F.I.Sh uchta maydonga ajratildi, tashkilot o'rniga rasm
- Guruhlar SLA jadvali (Team workload sahifasi)
- Jarayondagi va rad etilgan zayavkalarni qayta biriktirish

**Bilib qo'yish kerak:**
- Finesse paroli `Mirolim@56592722` zayavka #INC-000054 matnida ochiq turgan edi —
  bazadan tozalandi, lekin **parolni almashtirish kerak** (u suhbat tarixida qolgan)
- `loyiha2/` papkasi bo'shatildi; kod unga bog'liq emas

---

## 7. Ish tartibi — qisqacha

```
1. PARTIYA 1  (soatlar)      /register, superadmin bypass, token TTL,
                             APP_DEBUG, bog'liqliklar
2. PARTIYA 2  (kunlar)       Tenant izolyatsiyasi + ikki-tenantli testlar
3. PARTIYA 3  (kunlar)       IDOR'lar: approval, notification, profile,
                             web comment, calendar, service request
4. PARTIYA 4                 LDAP TLS, attachment private disk, listenerlar
```

**Har bir band uchun qoida:** tuzatishdan oldin muammoni ko'rsatadigan tekshiruv
yozing, keyin tuzating. Yuqorida har bandda "Tekshiruv:" satri bor — ish o'sha shart
bajarilganda tugagan hisoblanadi.

**Partiya 2 uchun alohida:** ikki-tenantli regressiya testisiz tugagan deb
hisoblamang. Hozirgi 28 ta test bitta org'li SQLite'da ishlagani uchun aynan shu
muammoni o'tkazib yuborgan.
