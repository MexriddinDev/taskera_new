# Localhostda ishlaydigan frontendni Senior darajada testing qilish

Senior frontend dasturchi localhostda port orqali ishlaydigan frontendni faqat “sahifa ochilyaptimi” yoki “tugma ishlayaptimi” darajasida tekshirmaydi.

Masalan loyiha:

```text
Frontend:
http://localhost:3000

yoki

http://localhost:5173

Backend API:
http://localhost:8000/api
```

ko‘rinishida ishlayotgan bo‘lsa, testing ham shu lokal muhitga mos tashkil qilinadi.

Asosiy tekshiruvlar:

```text
Kod sifati
+
Komponentlar
+
Biznes logika
+
Local API
+
Real user flow
+
Role/Permission
+
Responsive
+
Browser compatibility
+
Performance
+
Security
```

---

## 1. Local server ishlashini tekshirish

Avval frontendning o‘zi normal ishga tushishi kerak.

Masalan:

```bash
npm install
npm run dev
```

yoki:

```bash
npm start
```

Shundan keyin:

```text
http://localhost:3000
```

yoki:

```text
http://localhost:5173
```

ochiladi.

Tekshiriladi:

* frontend server ishga tushdimi;
* port band emasmi;
* sahifa ochilyaptimi;
* blank page yo‘qmi;
* console error yo‘qmi;
* build error yo‘qmi;
* frontend backend API bilan bog‘langanmi.

Agar port band bo‘lsa:

```text
Port 3000 already in use
```

kabi xato chiqishi mumkin.

Bu holat ham tekshiriladi.

---

# 2. Static Code Checks

Local testni boshlashdan oldin kod sifati tekshiriladi.

```bash
npm run lint
npm run typecheck
npm run build
```

Tekshiriladi:

* ESLint error;
* TypeScript error;
* unused import;
* unused variable;
* build error;
* noto‘g‘ri dependency;
* console.log;
* debugger;
* duplicate kod;
* hardcoded API URL.

Masalan:

```ts
const API_URL = 'http://localhost:8000/api'
```

development uchun normal bo‘lishi mumkin.

Lekin yaxshiroq variant:

```env
VITE_API_URL=http://localhost:8000/api
```

yoki:

```env
NEXT_PUBLIC_API_URL=http://localhost:8000/api
```

---

# 3. Localhost konfiguratsiyasini tekshirish

Local loyihalarda ayniqsa quyidagilar tekshiriladi:

```text
Frontend port
Backend port
API URL
CORS
WebSocket URL
Environment variables
Proxy
```

Masalan:

```text
Frontend:
localhost:5173

Backend:
localhost:8000
```

Frontenddan request:

```text
http://localhost:8000/api/users
```

ga ketishi kerak.

Tekshiriladi:

* noto‘g‘ri portga request ketmayaptimi;
* API URL to‘g‘rimi;
* CORS error chiqmayaptimi;
* HTTP/HTTPS aralashib ketmaganmi;
* WebSocket bo‘lsa to‘g‘ri port ishlatyaptimi.

---

# 4. Unit Testing

Kichik funksiyalar alohida tekshiriladi.

Masalan:

```ts
function calculateDeviation(value: number, norm: number) {
  return value - norm
}
```

Test:

```ts
expect(calculateDeviation(4.82, 4)).toBe(0.82)
```

Tool:

```text
Vitest
yoki
Jest
```

Tekshiriladi:

* calculator;
* formatter;
* validator;
* filter logic;
* status calculation;
* permission helper;
* date formatter.

---

# 5. Component Testing

Har bir komponent localhost API'dan mustaqil ham test qilinishi kerak.

Masalan:

```text
LoginForm
Button
Modal
Drawer
Table
Filter
AlertCard
Select
DatePicker
```

LoginForm uchun:

* input render;
* required validation;
* password field;
* submit;
* loading;
* disabled;
* error;
* success.

React bo‘lsa:

```text
React Testing Library
+
Vitest
```

---

# 6. Local API Testing

Agar backend ham localhostda bo‘lsa:

```text
Frontend:
http://localhost:5173

API:
http://localhost:8000/api
```

faqat `200 OK` holatini test qilish yetarli emas.

Quyidagilar ham tekshiriladi:

```text
200 → success
400 → validation error
401 → unauthorized
403 → forbidden
404 → not found
409 → conflict
422 → validation
429 → too many requests
500 → server error
timeout
backend o‘chirilgan
empty response
wrong response
```

Masalan backendni vaqtincha o‘chirib:

```text
Frontend ochiq
Backend OFF
```

holatda frontend qanday ishlashi tekshiriladi.

To‘g‘ri frontend:

```text
Server bilan aloqa mavjud emas
```

kabi error ko‘rsatishi kerak.

Noto‘g‘ri holat:

```text
oq sahifa
```

yoki:

```text
Uncaught TypeError
```

chiqishi.

---

# 7. API Error State Testing

Har bir sahifa uchun kamida:

```text
Loading
Success
Empty
Error
```

holati bo‘lishi kerak.

Masalan Monitoring:

### Loading

```text
Ma’lumot yuklanmoqda...
```

### Success

```text
17 ta muammo
```

### Empty

```text
Muammo topilmadi
```

### Error

```text
Server bilan aloqa mavjud emas
[Qayta urinish]
```

---

# 8. Playwright E2E Local Testing

Local loyiha uchun Playwright juda qulay.

Masalan frontend:

```text
http://localhost:5173
```

Playwright config:

```ts
import { defineConfig } from '@playwright/test'

export default defineConfig({
  use: {
    baseURL: 'http://localhost:5173',
  },
})
```

Endi testda:

```ts
await page.goto('/login')
```

deb yozish kifoya.

---

# 9. Playwright bilan local serverni avtomatik ko‘tarish

Playwright test boshlaganda frontend serverni o‘zi ishga tushirishi mumkin.

Masalan:

```ts
import { defineConfig } from '@playwright/test'

export default defineConfig({
  webServer: {
    command: 'npm run dev',
    url: 'http://localhost:5173',
    reuseExistingServer: true,
  },

  use: {
    baseURL: 'http://localhost:5173',
  },
})
```

Keyin:

```bash
npx playwright test
```

deyiladi.

Playwright:

```text
frontendni ishga tushiradi
↓
localhost:5173 ochilishini kutadi
↓
testlarni bajaradi
```

---

# 10. Login E2E Test

Masalan:

```ts
import { test, expect } from '@playwright/test'

test('login ishlashi kerak', async ({ page }) => {
  await page.goto('/login')

  await page.getByLabel('Login').fill('operator')
  await page.getByLabel('Parol').fill('123456')

  await page.getByRole('button', {
    name: 'Kirish'
  }).click()

  await expect(page).toHaveURL(/dashboard/)
})
```

Bu localhostda real browser orqali ishlaydi.

---

# 11. Real User Flow Testing

Senior frontend testni sahifalar bo‘yicha emas, real flow bo‘yicha ham yozadi.

Masalan:

```text
localhost:5173/login
        ↓
Login
        ↓
Dashboard
        ↓
Monitoring
        ↓
Hudud tanlash
        ↓
Tuman tanlash
        ↓
Obyektni tanlash
        ↓
Muammo markerini bosish
        ↓
Detail drawer
```

Playwright shu jarayonni to‘liq tekshiradi.

---

# 12. Monitoring sahifasi

Local loyiha bo‘lsa ham asosiy biznes flow to‘liq tekshiriladi.

Masalan:

```text
http://localhost:5173/monitoring
```

Tekshiriladi:

* sahifa ochiladi;
* muammolar soni chiqadi;
* kritik holatlar chiqadi;
* hudud filter ishlaydi;
* tuman filter ishlaydi;
* obyekt filter ishlaydi;
* xarita ishlaydi;
* marker ishlaydi;
* drawer ochiladi;
* table yangilanadi;
* API requestlar to‘g‘ri ketadi.

---

# 13. Network orqali tekshirish

Browser DevTools:

```text
F12
→ Network
```

Tekshiriladi:

* request qayerga ketmoqda;
* localhost port to‘g‘rimi;
* status code;
* response;
* request
