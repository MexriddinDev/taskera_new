# FRONTEND AUDIT NATIJASI

> **TUZATISH HOLATI (2026-09-11).** Kod bilan hal qilinganlar: **Y-1** (ESLint o'rnatildi +
> `eslint.config.js` yozildi — `npm run lint` endi ishlaydi: 0 xato, 292 ogohlantirish),
> **Y-3** (`ui-avatars.com` olib tashlandi — lokal SVG avatar, 9 ta joy),
> **O-1** (tarmoq xatosi endi tarjima qilinadi), **O-2** (429 uchun alohida xabar +
> `Retry-After`), **P-1** (`npm run typecheck`), **P-2** (`dist/` va `tsbuildinfo`
> git indeksidan chiqarildi), **P-4** (production build'da `console` olib tashlanadi).
> **Qolgan ish:** Y-2 (test infratuzilmasi), O-3 (tarjimalarni til bo'yicha ajratish),
> O-4 ("Qayta urinish" tugmalari), O-5/P-3/P-5.

**Sana:** 2026-09-11 · **Asos:** `front_test.md` (13 bo'lim)
**Qamrov:** faqat xavfsiz tekshiruvlar — kod o'qish, statik tahlil, production build, konfiguratsiya.
**Bajarilmadi:** dev-serverni ko'tarish (port band qilmaslik uchun), backendni o'chirib sinash, brauzerda qo'lda flow tekshiruvi.
**Audit paytida kod o'zgartirilmadi** — tuzatishlar keyin, yuqoridagi holat bloki bo'yicha kiritildi.

---

## XULOSA

| Jiddiylik | Soni |
|---|---|
| 🟠 Yuqori | 3 |
| 🟡 O'rta | 5 |
| 🔵 Past / kuzatuv | 5 |

Asosiy xulosa: **ilova kodi yaxshi yozilgan** — ErrorBoundary bor, XSS teshigi yo'q, 401 markazlashgan ishlov, konfiguratsiya env orqali, build toza o'tadi. Lekin **test va sifat infratuzilmasi umuman yo'q**: `front_test.md` ning 13 bo'limidan **6 tasi** (unit, component, E2E, Playwright, login E2E, real flow) hozirgi loyihada texnik jihatdan bajarib bo'lmaydi, **1 tasi** (lint) esa buzilgan holatda.

### Hujjat bo'limlari bo'yicha qamrov

| # | Bo'lim | Holat |
|---|---|---|
| 1 | Local server | ⚪ Tekshirilmadi (dev-server ko'tarilmadi) |
| 2 | Static code checks | 🟠 **lint buzilgan**, typecheck skripti yo'q, build ✅ |
| 3 | Localhost konfiguratsiyasi | ✅ Yaxshi |
| 4 | Unit testing | 🟠 **Yo'q** — test kutubxonasi o'rnatilmagan |
| 5 | Component testing | 🟠 **Yo'q** |
| 6 | Local API testing | 🟡 Qisman — 401/404/tarmoq bor, 429 yo'q |
| 7 | API error state | 🟡 Qisman — 28 sahifadan 19 tasida "Qayta urinish" yo'q |
| 8 | Playwright E2E | 🟠 **Yo'q** |
| 9 | Playwright webServer | 🟠 **Yo'q** |
| 10 | Login E2E | 🟠 **Yo'q** |
| 11 | Real user flow | 🟠 **Yo'q** |
| 12 | Monitoring sahifasi | 🟡 Holatlar bor, avtotest yo'q |
| 13 | Network tekshiruvi | ✅ Konfiguratsiya to'g'ri |

---

## 🟠 YUQORI

### Y-1. `npm run lint` umuman ishlamaydi

```
$ npx eslint .
ESLint couldn't find an eslint.config.* file.
```

Ikki muammo bir vaqtda:

1. **ESLint `devDependencies` da yo'q** (`package.json` da faqat vite, typescript, tailwind, postcss va tiplar bor). `npm run lint` npx orqali tasodifiy versiyani (10.10.0) tortib keladi.
2. **`eslint.config.js` fayli yo'q** — ESLint 9+ dan boshlab flat config majburiy.

Ya'ni `front_test.md` §2 dagi "ESLint error, unused import, unused variable, console.log" tekshiruvlari **hech qachon bajarilmagan**.

**Tavsiya:**

```bash
npm i -D eslint @eslint/js typescript-eslint eslint-plugin-react-hooks eslint-plugin-react-refresh
```

va `eslint.config.js` yaratish (Vite + React + TS shabloni).

### Y-2. Test infratuzilmasi umuman yo'q

`package.json` da **vitest ham, jest ham, Playwright ham, @testing-library ham yo'q**. `test` skripti ham yo'q.

Bu hujjatning **6 ta bo'limini** (§4 unit, §5 component, §8–11 E2E) bajarilmas qiladi. Backendda 88 ta test bor — frontendda **0 ta**.

**Tavsiya (bosqichma-bosqich):**

1. `vitest` + `@testing-library/react` — avval eng tavakkalchi sof mantiq uchun:
   `humanDuration()`, `slaTime()`, `requesterHeader()` (`TaskDetailPage.tsx`, `CreateTaskModal.tsx`), `RequesterPrefill` ning frontend juftligi.
2. `@playwright/test` — §10 dagi login flow va §11 dagi "login → dashboard → zayavka yaratish → detail" yo'li.
3. Playwright `webServer` sozlamasi (§9) — test o'zi `npm run dev` ni ko'taradi.

### Y-3. Xodimlarning F.I.Sh tashqi internet xizmatiga yuborilmoqda

**9 joyda** avatar uchun `ui-avatars.com` ishlatilgan:

```
ProfileCard.tsx:32   AssignTaskModal.tsx:119   TaskCard.tsx:317
ProfilePage.tsx:35   SupportPanelPage.tsx:71   TaskDetailPage.tsx:63, 1612
UsersPage.tsx:109    Navbar.tsx:54
```

```ts
`https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&...`
```

Ikki muammo:

1. **Maxfiylik:** har bir avatar ko'rsatilganda xodimning to'liq ismi tashqi serverga (Referer bilan birga) yuboriladi. Bank ichki tizimi uchun bu qabul qilinmaydi.
2. **Ishonchlilik:** tizim lokal tarmoqda ishlaydi (`front_test.md` §1). Tarmoqda internet bo'lmasa — **barcha avatarlar singan rasm** bo'lib qoladi, har sahifada o'nlab muvaffaqiyatsiz so'rov ketadi va sahifa sekinlashadi.

**Tavsiya:** ism bosh harflarini (`XA`) CSS/SVG bilan lokal chizish. Bitta kichik komponent 9 joyni ham almashtiradi va tashqi so'rov butunlay yo'qoladi.

---

## 🟡 O'RTA

### O-1. Tarmoq xatosi xabari inglizcha va tarjimasiz

**Fayl:** `src/shared/infrastructure/http/axiosClient.ts:63`

```ts
return Promise.reject(new AppError('Server unavailable or network connection lost', 503));
```

Bu matn to'g'ridan-to'g'ri foydalanuvchiga ko'rinadi (masalan `LoginForm.tsx:67` → `{error.message}`). Backend o'chganda o'zbek tilidagi interfeysda inglizcha xabar chiqadi.

`front_test.md` §6 aynan shuni talab qiladi: **"Server bilan aloqa mavjud emas"**.

**Tavsiya:** xato kodi (`503`) qaytarilsin, matn esa i18n kalitidan olinsin.

### O-2. 429 (Too Many Requests) alohida ishlanmaydi

Backendda throttle bor (`auth/login`, `ad-account/send-code`). 429 kelganda `axiosClient` uni oddiy `AppError` qiladi va Laravel'ning inglizcha `"Too Many Attempts."` matni ko'rinadi. Foydalanuvchi necha soniya kutishi kerakligini (`Retry-After` sarlavhasi bor) bilmaydi.

**Tavsiya:** 401 va 404 kabi 429 uchun ham alohida shox — tarjima qilingan "Juda ko'p urinish. {n} soniyadan keyin qayta urinib ko'ring."

### O-3. Boshlang'ich bundle'ning katta qismi — tarjimalar

Build natijasi:

```
dist/assets/index-*.js        330.22 kB │ gzip: 100.52 kB
dist/assets/vendor-react-*.js 162.89 kB │ gzip:  53.16 kB
```

`src/shared/presentation/i18n/translations.ts` — **265 KB, 4697 qator, uchala til (uz/ru/en) bitta faylda** va u `index` chunk'iga kiradi (tekshirildi: `createTask.defaultOption` kaliti `dist/assets/index-*.js` ichida).

Ya'ni har bir foydalanuvchi o'ziga kerak bo'lmagan ikkita tilni ham yuklab oladi.

**Tavsiya:** har tilni alohida faylga ajratib, `import()` bilan dinamik yuklash. Boshlang'ich yuk taxminan **60–70 KB gzip'ga** kamayadi.

> `recharts` (`AreaChart` 364 KB) allaqachon alohida chunk'da va faqat kerakli sahifada yuklanadi — bu to'g'ri qilingan.

### O-4. Xato holatida "Qayta urinish" tugmasi ko'p sahifada yo'q

28 sahifadan **19 tasida** qayta urinish tugmasi topilmadi. `front_test.md` §7 har sahifa uchun to'rt holatni talab qiladi: Loading / Success / Empty / **Error + [Qayta urinish]**.

Tugmasi bor (namuna sifatida): `SlaPoliciesPage`, `MyRequestsPage`, `MyTasksPage`, `OpenTasksPage`, `UsersPage`, `DashboardPage`, `AuditLogsPage`, `SupportPanelPage`, `PermitsPage`.

Yo'q: `ApprovalsPage`, `AssetsPage`, `ChangesPage`, `KnowledgeBasePage`, `MonitoringPage`, `ProblemsPage`, `RbacManagementPage`, `ServiceCatalogPage`, `TeamWorkloadPage`, `ItsmSettingsPage`, `AutomationPage`, `TaskDetailPage`, `CiscoCallPage` va b.

> **Eslatma:** `LoginPage`, `StatsPage`, `IntegrationMapPage` bu ro'yxatga kirmaydi — birinchisida mantiq `LoginForm` komponentida (holatlar bor), ikkinchisi `StatsOverview` ga topshiradi, uchinchisi esa umuman API'siz statik diagramma.

### O-5. Token `localStorage` da saqlanadi

`src/shared/infrastructure/storage/localStorage.ts` → `auth_token`. XSS yuz bersa token o'g'irlanadi va u Sanctum tokeni bo'lgani uchun to'g'ridan-to'g'ri API'ga ishlaydi.

Yumshatuvchi omillar: `dangerouslySetInnerHTML` **umuman ishlatilmagan**, React JSX avtomatik ekranlaydi, `eval`/`innerHTML` yo'q. Ya'ni hozirda XSS vektori ko'rinmaydi.

**Tavsiya:** hozircha o'zgartirish shart emas, lekin CSP sarlavhasi qo'shilsa himoya qatlami oshadi.

---

## 🔵 PAST / KUZATUV

### P-1. `typecheck` skripti yo'q

`front_test.md` §2 `npm run typecheck` ni talab qiladi. Hozir TypeScript faqat `build` ichida (`tsc -b && vite build`) tekshiriladi.

**Tavsiya:** `"typecheck": "tsc --noEmit"` qo'shish — CI uchun build'dan tez.

### P-2. Build artefaktlari git'da

```
frontend/dist/index.html
frontend/tsconfig.tsbuildinfo
```

Ikkalasi ham har build'da o'zgaradi va keraksiz diff/konflikt beradi.

**Tavsiya:** `.gitignore` ga `frontend/dist/` va `*.tsbuildinfo` qo'shib, `git rm --cached` bilan chiqarish.

### P-3. Production'da API manzili nisbiy

`VITE_API_BASE_URL=/api/v1` — dev'da Vite proxy (`vite.config.ts:server.proxy`) uni backendga yo'naltiradi. **Production'da proxy yo'q**: `dist` Laravel bilan bir xil originda xizmat qilinmasa, barcha so'rovlar 404 bo'ladi.

**Tavsiya:** deploy hujjatida shu shart yozilsin yoki `.env.production` da to'liq manzil ko'rsatilsin.

### P-4. 10 ta `console.error` qolgan

`console.log` va `debugger` **yo'q** (bu yaxshi). Lekin 10 ta `console.error` ekspluatatsiya build'ida ham qoladi va brauzer konsoliga ichki xato tafsilotlarini chiqaradi.

**Tavsiya:** `vite.config.ts` da `esbuild.drop: ['console', 'debugger']` (faqat production uchun).

### P-5. 403 uchun markazlashgan ishlov yo'q

`axiosClient` da 401 va 404 uchun alohida shox bor, 403 esa umumiy `AppError` bo'lib o'tadi va serverning matni ko'rsatiladi. Sahifa darajasida hal qilingan, lekin bir xil emas.

---

## ✅ YAXSHI BAJARILGAN (tekshirildi, muammo yo'q)

| Tekshiruv | Natija |
|---|---|
| §2 Build | `npm run build` toza o'tadi (`tsc -b` + vite, 8.5s). TypeScript xatosi **0**. |
| §2 `debugger` / `console.log` | **0 ta**. |
| §3 Hardcoded API URL | **Yo'q.** Barchasi `VITE_API_BASE_URL` + Vite proxy orqali (`vite.config.ts`). |
| §3 HTTP/HTTPS aralashuvi | To'g'ri hal qilingan: dev-server `basicSsl()` bilan HTTPS'da (mikrofon "secure context" talabi uchun), backendga proxy server tomonda ketadi. |
| §3 LAN kirish | `server.host: true` — boshqa qurilmalar ham kira oladi. |
| §6 401 | Markazlashgan: token tozalanadi + `auth:unauthorized` hodisasi, `App.tsx:150` da tinglovchi bor. |
| §6 Tarmoq uzilishi | `error.request` shoxi bor — oq sahifa emas, `AppError(503)` qaytadi (matn muammosi — O-1). |
| §6 Timeout | JSON so'rovlar 15s, fayl yuklash 10 daqiqa (alohida sozlangan). |
| §6 "Oq sahifa" | `ErrorBoundary` mavjud (`App.tsx` + `shared/presentation/components/ErrorBoundary.tsx`). |
| XSS | `dangerouslySetInnerHTML` **umuman yo'q**. |
| Performance | Sahifalar `lazy()` bilan bo'lingan (28 ta alohida chunk), vendor chunk'lar ajratilgan, `recharts` faqat kerak bo'lganda yuklanadi. |
| react-query | `retry: 1`, `staleTime: 15s`, `refetchOnReconnect: true` — oqilona sozlangan. |

---

## TAVSIYA ETILGAN TARTIB

1. **Birinchi:** Y-1 — ESLint'ni o'rnatib, config yozish. Bu keyingi hamma narsani ushlaydi va bir kunlik ish.
2. **Ikkinchi:** Y-3 — avatarni lokal SVG'ga o'tkazish (maxfiylik + internetsiz ishlash).
3. **Uchinchi:** O-1, O-2 — xato matnlarini tarjimaga o'tkazish (yarim kunlik ish, foydalanuvchi darhol sezadi).
4. **Keyin:** Y-2 — vitest bilan boshlash (avval sof funksiyalar), so'ng Playwright bilan login flow.
5. **Fursat bo'lganda:** O-3 (tarjimalarni ajratish), O-4 (qayta urinish tugmalari), P-1…P-5.
