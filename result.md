# TEST NATIJASI — PHPUnit + Gremlins.js

**Sana:** 2026-09-14 · **Vosita:** `sebastianbergmann/phpunit` 12.5.32, `marmelab/gremlins.js` 2.2.0
**Muhit:** PHP 8.4.24 · Vite dev-server `https://localhost:5173` · backend `127.0.0.1:5170` · MySQL 3306

---

## XULOSA

| Vosita | Qamrov | Natija |
|---|---|---|
| **PHPUnit** | 91 test, 21 fayl | ✅ **91/91 o'tdi**, 280 assertion, 0 xato |
| **Gremlins.js** | **29 sahifa × 80 amal = 2 320 tasodifiy amal** | ✅ **29/29 toza** (tuzatishdan keyin) |

**Topilgan va TUZATILGAN nuqson:** `auth_token` bor, lekin `auth_user` yo'q
bo'lganda ilova `/login` ↔ `/dashboard` orasida **cheksiz yo'naltirish
tsikliga** tushib, butunlay ishlamay qolardi. Tafsilot va tuzatish — §2.4.

**Bazaga ta'sir:** gremlins **hech qanday iz qoldirmadi** — 0 zayavka, 0 audit
yozuvi, 0 tarix. Tasdiq — §2.7.

---

## 1. PHPUnit — backend

### 1.1 Umumiy natija

```
PHPUnit 12.5.32 by Sebastian Bergmann and contributors.
Runtime:       PHP 8.4.24
Configuration: backend/phpunit.xml

OK (91 tests, 280 assertions)
Time: 00:04.011, Memory: 76.00 MB
```

| Suite | Testlar | Assertionlar | Vaqt |
|---|---|---|---|
| Unit | 1 | 1 | 10 ms |
| Feature | 90 | 279 | 4 156 ms |
| **Jami** | **91** | **280** | **~4 s** |

Xato yo'q, skipped yo'q, risky yo'q.

### 1.2 Fayllar bo'yicha taqsimot (21 ta fayl)

| Testlar | Fayl | Nimani qamraydi |
|---|---|---|
| 17 | `Feature/ITSM/SlaRuleTest.php` | SLA qoidalari, ish vaqti, muddat buzilishi |
| 10 | `Feature/ITSM/TicketAssignmentTest.php` | Biriktirish, huquqlar, taymer |
| 7 | `Feature/Security/ProductionSecurityHardeningTest.php` | IDOR, huquqsiz mutatsiya, SMS replay |
| 4 | `Feature/Security/SecurityFixesTest.php` | Imzolangan havola, SMS hash, o'chirish huquqi |
| 4 | `Feature/Security/AuthorizationTest.php` | Rol, MIME filtri, org spoofing |
| 4 | `Feature/ITSM/SupportPanelTest.php` | Support panel hisoblari va filtrlari |
| 4 | `Feature/ITSM/RequesterPrefillTest.php` | Murojaatchi maydonlarini to'ldirish |
| 4 | `Feature/ITSM/ItmsModuleComprehensiveTest.php` | Problem / Change / Asset lifecycle |
| 4 | `Feature/ITSM/FinesseCallActiveTest.php` | Finesse qo'ng'iroq holati |
| 3 | `Feature/Security/SanctumTokenExpirationTest.php` | Token muddati |
| 3 | `Feature/Security/ProductionConfigCheckTest.php` | `deploy:check` qoidalari |
| 3 | `Feature/Security/AccessControlTest.php` | Login throttle, begona izoh/xabar |
| 3 | `Feature/ITSM/PermitTest.php` | Ruxsatnoma CRUD va validatsiya |
| 3 | `Feature/ITSM/MyRequestsDateFilterTest.php` | Sana bo'yicha filtr |
| 3 | `Feature/Database/DatabaseOptimizationTest.php` | N+1 va agregatsiya aniqligi |
| 2 | `Feature/Security/SelfRegistrationDisabledTest.php` | O'z-o'zini ro'yxatdan o'tkazish yopiq |
| 2 | `Feature/Security/ProfileHrFieldsAreReadOnlyTest.php` | HR maydonlari faqat o'qish uchun |
| 1 | `Feature/Security/ChatAccessTest.php` | Begona suhbatni o'qib bo'lmaydi |
| 1 | `Feature/ITSM/ServiceCatalogSeederTest.php` | Katalog bir marta import qilinadi |
| 1 | `Feature/ExampleTest.php` | Login'ga yo'naltirish |
| 1 | `Unit/ExampleTest.php` | Namuna (mazmunsiz) |

### 1.3 🟡 Qamrov (coverage) o'lchanmaydi

```
$ php -m | grep -iE "xdebug|pcov"
(bo'sh)
```

`phpunit.xml` da `<source><include><directory>app</directory>` sozlangan, ya'ni
qamrov hisoblashga tayyor — lekin na Xdebug, na PCOV o'rnatilgan. "91 test `app/`
ning necha foizini qamraydi" degan savolga javob yo'q.

**Tavsiya:** `pecl install pcov` → `php artisan test --coverage --min=50`

### 1.4 🔵 `tests/Unit` deyarli bo'sh

91 testdan 90 tasi Feature (baza ko'taradi). Bu `back_test_natija.md` dagi **P-6** —
hali hal qilinmagan.

---

## 2. Gremlins.js — frontend

### 2.1 Usul

Gremlins — sof brauzer kutubxonasi, CLI'si yo'q. Barcha sahifalarni avtomatik
aylanib chiqish uchun **Playwright** o'rnatildi (`front_test_natija.md` dagi
**Y-2** bandi — E2E infratuzilma yo'qligi — shu bilan qisman yopildi).

| Komponent | Tafsilot |
|---|---|
| Runner | `frontend/scripts/gremlins-run.mjs` (qayta ishlatsa bo'ladi) |
| Brauzer | Chromium (Playwright), headless |
| Auth | Sanctum tokeni + foydalanuvchi obyekti `localStorage` ga kiritildi — **parol bilan ishlanmadi** |
| Hisob | Vaqtinchalik `gremlins.test` (super admin, 36 huquq) — testdan keyin o'chirildi |
| Rejim | Xavfsiz: `allowDestructive: false` |
| Yuklama | Har sahifada 80 amal, 10 ms oraliq |

### 2.2 Yakuniy natija — 29 sahifa, tuzatishdan keyin

```
Hisob: gremlins.test (Super Admin), 36 huquq

OK   /login                   80 amal | xato=0 tarmoq=0
OK   /ad-account              80 amal | xato=0 tarmoq=0
OK   /                        80 amal | xato=0 tarmoq=0
OK   /profile                 80 amal | xato=0 tarmoq=0
OK   /requests                80 amal | xato=0 tarmoq=0
OK   /task/65                 80 amal | xato=0 tarmoq=0
OK   /knowledge               80 amal | xato=0 tarmoq=0
OK   /catalog                 80 amal | xato=0 tarmoq=0
OK   /approvals               80 amal | xato=0 tarmoq=0
OK   /dashboard               80 amal | xato=0 tarmoq=0
OK   /support-panel           80 amal | xato=0 tarmoq=0
OK   /tasks                   80 amal | xato=0 tarmoq=0
OK   /my-tasks                80 amal | xato=0 tarmoq=0
OK   /problems                80 amal | xato=0 tarmoq=0
OK   /changes                 80 amal | xato=0 tarmoq=0
OK   /automation              80 amal | xato=0 tarmoq=0
OK   /itsm-settings           80 amal | xato=0 tarmoq=0
OK   /assets                  80 amal | xato=0 tarmoq=0
OK   /sla-policies            80 amal | xato=0 tarmoq=0
OK   /team-workload           80 amal | xato=0 tarmoq=0
OK   /users                   80 amal | xato=0 tarmoq=0
OK   /monitoring              80 amal | xato=0 tarmoq=0
OK   /stats                   80 amal | xato=0 tarmoq=0
OK   /cisco-call              80 amal | xato=0 tarmoq=0
OK   /permits                 80 amal | xato=0 tarmoq=0
OK   /rbac                    80 amal | xato=0 tarmoq=0
OK   /integrations-map        80 amal | xato=0 tarmoq=0
OK   /audit                   80 amal | xato=0 tarmoq=0
OK   /bunday-sahifa-yoq       80 amal | xato=0 tarmoq=0

Jami: 29 sahifa, 0 tasida muammo.
```

### 2.3 Natija haqiqiyligi tekshirildi

Agar auth ishlamasa, barcha sahifalar `/login` ga qaytarilib, "29/29 OK"
degan yolg'on natija chiqardi. Shuning uchun har sahifaning **yakuniy URL'i**
qayd qilinadi:

- `/login` ga qaytarilganlar: **0 / 29**
- gremlins haqiqatan ishlagan sahifalar: **29 / 29**
- `/profile` → `/profile`, `/dashboard` → `/dashboard`, `/task/65` → `/task/65` …

> Bu tekshiruv bekorga qo'yilmagan: §2.4 dagi tuzatishdan keyingi birinchi
> yugurishda **26 sahifa `/login` ga qaytarilgan** edi va natija "29/29 OK"
> ko'rinardi. Sabab — runner faqat `auth_token` kiritardi, tuzatilgan ilova
> esa buni to'g'ri ravishda "kirilmagan" deb hisobladi. Runner tuzatildi:
> endi u `/api/v1/me` dan foydalanuvchi obyektini olib, ikkala kalitni ham
> kiritadi va `/login` ga qaytarilgan sahifani `AUTH` deb belgilab,
> ogohlantirish chiqaradi.

### 2.4 🟠 TOPILGAN XATO — `/login` da cheksiz yo'naltirish tsikli — ✅ TUZATILDI

**Xabar:**

```
Warning: Maximum update depth exceeded. This can happen when a component calls
setState inside useEffect...
    at Navigate (react-router-dom)
```

**Sabab — ikki manba bir-biriga zid edi.**

`src/shared/presentation/store/useAuthStore.ts` (tuzatishdan oldin):

```ts
user:            storage.get<User>('auth_user'),             // null bo'lishi mumkin
isAuthenticated: Boolean(storage.get<string>('auth_token')), // true
```

`isAuthenticated` faqat **tokenga** qarab hisoblanardi, barcha marshrut
qorovullari esa **`user`** obyektini talab qiladi. Ikkisi mos kelmaganda:

1. `LoginPage.tsx:56` — `isAuthenticated` → `<Navigate to="/dashboard">`
2. `App.tsx:87` — `PermissionRouteGuard`: `!user` → `<Navigate to="/login">`
3. → 1-qadamga qaytadi → **cheksiz aylanish**

**Bu holat hayotda qanday yuzaga keladi:**

- `storage.get` `JSON.parse` xatosini **jimgina yutadi** va `null` qaytaradi —
  `auth_user` buzilsa, token esa joyida qolaveradi;
- `storage.set` xatoni faqat `console.error` qiladi — `auth_user` yozilmay
  qolishi (kvota to'lgani) mumkin, token esa allaqachon yozilgan;
- brauzer kengaytmasi yoki foydalanuvchi bitta kalitni o'chirsa.

**Oqibati:** ilova butunlay ishlamay qolardi — foydalanuvchi login sahifasiga
ham chiqa olmasdi, faqat sayt ma'lumotlarini qo'lda tozalash yordam berardi.

**Tuzatish** — `useAuthStore.ts` ga `restoreSession()` qo'shildi:

```ts
const restoreSession = (): Pick<AuthState, 'user' | 'token' | 'isAuthenticated'> => {
  const token = storage.get<string>('auth_token');
  const user = storage.get<User>('auth_user');

  if (!token || !user) {
    // Yarim holat saqlanib qolmasin: aks holda axiosClient egasiz tokenni
    // Authorization sarlavhasida yuboraverardi.
    if (token || user) {
      storage.remove('auth_token');
      storage.remove('auth_user');
    }

    return { user: null, token: null, isAuthenticated: false };
  }

  return { user, token, isAuthenticated: true };
};
```

**Tasdiqlangan o'lchov** — bir xil skript bilan, tuzatishdan oldin va keyin:

| Holat | Oldin | Keyin |
|---|---|---|
| `auth_token` + `auth_user` (odatdagi) | 4 navigatsiya, tsikl yo'q | 4 navigatsiya, tsikl yo'q — **regressiya yo'q** |
| faqat `auth_token` (`auth_user` yo'q) | **484 navigatsiya, TSIKL** | **2 navigatsiya, `/login` da qoladi, tsikl yo'q** |

Ya'ni buzilgan holatda ilova endi oddiygina login sahifasini ko'rsatadi.

### 2.5 Qolgan 28 sahifa — toza

2 320 ta tasodifiy amal davomida ulardan **bitta ham** konsol xatosi,
ushlanmagan istisno, 5xx javob yoki uzilgan so'rov chiqmadi. ErrorBoundary
hech qayerda ishga tushmadi.

### 2.6 Sifat darvozalari

| Tekshiruv | Natija |
|---|---|
| `npm run typecheck` | ✅ toza |
| `npx eslint .` | ✅ **0 xato** (291 ogohlantirish — hammasi eski kodda) |
| `npm run build` | ✅ 6.14 s da yig'ildi |

### 2.7 Bazaga ta'sir — yo'q

`gremlins.test` hisobi qoldirgan izlar:

| Nima | Soni |
|---|---|
| `audit_logs` | **0** |
| `tickets` (murojaatchi sifatida) | **0** |
| `ticket_assignment_history` | **0** |
| `ticket_status_history` | **0** |

`src/dev/gremlins.ts` dagi `canClick` filtri (o'chirish · chiqish · yuborish ·
saqlash · tasdiqlash · `type="submit"` · tashqi havolalar) o'z vazifasini bajardi.

> Test davomida `tickets` 64→65 va `audit_logs` 535→538 ga o'zgardi, lekin bu
> **haqiqiy foydalanuvchilar** ishi (superadmin 04:25 da zayavka yaratgan) —
> gremlins yugurishidan oldin. Test hisobi bilan bog'liq bitta ham yozuv yo'q.

### 2.8 Tozalash

Vaqtinchalik test hisobi, tokenlari va rollari o'chirildi:

```
qolgan_user  = 0
qolgan_token = 0
```

---

## 3. O'ZGARGAN / QO'SHILGAN FAYLLAR

| Fayl | Izoh |
|---|---|
| `frontend/src/shared/presentation/store/useAuthStore.ts` | **Tuzatish** — `restoreSession()` |
| `frontend/scripts/gremlins-run.mjs` | Barcha sahifalar bo'ylab avtomatik runner |
| `frontend/src/dev/gremlins.ts` | Dev-loader, `window.gremlins()` |
| `frontend/src/types/gremlins.d.ts` | Tip deklaratsiyasi |
| `frontend/eslint.config.js` | `src/dev/**` uchun `no-console` o'chirildi |
| `frontend/package.json` | `gremlins.js`, `playwright` devDependency |
| `frontend/gremlins-report.json` | Oxirgi yugurishning to'liq JSON hisoboti |

**Qayta ishga tushirish:**

```bash
# 1. Token oling
cd backend && php artisan tinker --execute="echo App\Models\User::find(1)->createToken('g')->plainTextToken;"

# 2. Yugurting (dev-server ko'tarilgan bo'lsin)
cd frontend
GREMLINS_TOKEN='<token>' GREMLINS_TICKET=65 node scripts/gremlins-run.mjs
```

Sozlamalar: `GREMLINS_NB` (amallar soni), `GREMLINS_DELAY` (ms),
`GREMLINS_HEADED=1` (brauzerni ko'rish), `GREMLINS_BASE` (manzil).

Qo'lda, brauzer konsolidan: `await gremlins()` · `gremlins.stop()`

---

## 4. KEYINGI QADAMLAR

1. 🟡 **PCOV o'rnatish** (§1.3) — 91 testning haqiqiy qiymatini faqat qamrov ko'rsatadi.
2. 🔵 **Yuklamani oshirish** — `GREMLINS_NB=500` bilan kechasi yugurtirish
   kamroq uchraydigan xatolarni ochishi mumkin.
3. 🔵 **CI'ga ulash** — Playwright endi bor; `gremlins-run.mjs` ni pipeline'ga
   qo'shsa, har commit'da 29 sahifa avtomatik tekshiriladi.
4. 🔵 **`/login` tsikli uchun regressiya testi** — hozir tuzatish qo'lda
   o'lchov bilan tasdiqlangan, avtomatik test yo'q.
