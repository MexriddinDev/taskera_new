/**
 * Gremlins.js — barcha sahifalar bo'ylab avtomatik "maymun testi".
 *
 * Nima qiladi: Playwright bilan Chromium ochadi, har bir marshrutga kiradi va
 * sahifada `window.gremlins()` ni ishga tushiradi (uni `src/dev/gremlins.ts`
 * dev-rejimda o'rnatadi). Har sahifa uchun konsol xatolari, ushlanmagan
 * istisnolar va muvaffaqiyatsiz tarmoq so'rovlari yig'iladi.
 *
 * Nega Playwright: gremlins.js sof brauzer kutubxonasi — CLI'si yo'q va
 * DOM'siz ishlamaydi. Barcha sahifalarni qo'lda aylanib chiqmaslik uchun
 * brauzer avtomatizatsiyasi kerak.
 *
 * Ishlatish:
 *   # dev-server ko'tarilgan bo'lishi shart (npm run dev)
 *   GREMLINS_TOKEN='<sanctum-token>' node scripts/gremlins-run.mjs
 *
 * Sozlash (env orqali):
 *   GREMLINS_TOKEN   majburiy — Sanctum tokeni (php artisan orqali olinadi)
 *   GREMLINS_BASE    standart https://localhost:5173
 *   GREMLINS_NB      har sahifadagi amallar soni, standart 80
 *   GREMLINS_DELAY   amallar orasidagi kutish ms, standart 10
 *   GREMLINS_TICKET  /task/:id uchun id, standart 1
 *   GREMLINS_HEADED  1 bo'lsa brauzer ko'rinadi
 *
 * DIQQAT: hujum haqiqiy backendga boradi. Standart holda buzuvchi tugmalar
 * (o'chirish/chiqish/yuborish) bloklanadi — `src/dev/gremlins.ts` dagi
 * `canClick` filtri. To'liq hujumdan oldin baza zaxirasini oling.
 */

import { chromium } from 'playwright';
import { writeFileSync } from 'node:fs';

const BASE = process.env.GREMLINS_BASE || 'https://localhost:5173';
const TOKEN = process.env.GREMLINS_TOKEN;
const NB = Number(process.env.GREMLINS_NB || 80);
const DELAY = Number(process.env.GREMLINS_DELAY || 10);
const TICKET_ID = process.env.GREMLINS_TICKET || '1';

if (!TOKEN) {
  console.error('GREMLINS_TOKEN berilmagan. Token olish:');
  console.error("  php artisan tinker --execute=\"echo App\\Models\\User::find(1)->createToken('g')->plainTextToken;\"");
  process.exit(1);
}

/** `App.tsx` dagi barcha marshrutlar. */
const ROUTES = [
  ['/login', 'Login (ochiq)'],
  ['/ad-account', 'AD hisob yaratish (ochiq)'],
  ['/', 'Ildiz (yo\'naltirish)'],
  ['/profile', 'Profil'],
  ['/requests', 'Mening murojaatlarim'],
  [`/task/${TICKET_ID}`, 'Zayavka tafsiloti'],
  ['/knowledge', 'Bilimlar bazasi'],
  ['/catalog', 'Xizmatlar katalogi'],
  ['/approvals', 'Tasdiqlashlar'],
  ['/dashboard', 'Boshqaruv paneli'],
  ['/support-panel', 'Support panel'],
  ['/tasks', 'Ochiq zayavkalar'],
  ['/my-tasks', 'Mening ishlarim'],
  ['/problems', 'Muammolar'],
  ['/changes', 'O\'zgarishlar'],
  ['/automation', 'Avtomatizatsiya'],
  ['/itsm-settings', 'ITSM sozlamalari'],
  ['/assets', 'Aktivlar'],
  ['/sla-policies', 'SLA siyosatlari'],
  ['/team-workload', 'Guruh yuklamasi'],
  ['/users', 'Foydalanuvchilar'],
  ['/monitoring', 'Monitoring'],
  ['/stats', 'Statistika'],
  ['/cisco-call', 'Cisco qo\'ng\'iroq'],
  ['/permits', 'Ruxsatnomalar'],
  ['/rbac', 'RBAC boshqaruvi'],
  ['/integrations-map', 'Integratsiyalar xaritasi'],
  ['/audit', 'Audit loglari'],
  ['/bunday-sahifa-yoq', '404 sahifa'],
];

/**
 * Shovqin filtri.
 *
 * Dev-server va brauzerning o'z xabarlari nuqson emas — ular hisobotni
 * to'ldirib, haqiqiy xatolarni ko'rinmas qilib qo'yadi.
 */
const NOISE = [
  /\[vite\]/i,
  /React DevTools/i,
  /Download the React DevTools/i,
  /favicon/i,
  /ERR_CERT/i,
  /net::ERR_ABORTED/i,
  /\[gremlins\]/,
];

const isNoise = (text) => NOISE.some((re) => re.test(text));

const run = async () => {
  const browser = await chromium.launch({ headless: !process.env.GREMLINS_HEADED });
  const context = await browser.newContext({
    ignoreHTTPSErrors: true, // dev-server o'z-o'zi imzolagan sertifikat bilan
    viewport: { width: 1440, height: 900 },
  });

  // Auth'ni localStorage orqali beramiz — login formasini to'ldirish shart emas.
  // `storage.set` qiymatni JSON.stringify qiladi, shu shaklga moslaymiz.
  //
  // IKKALA kalit ham kerak. `useAuthStore` sessiyani faqat `auth_token` VA
  // `auth_user` birga bo'lgandagina tiklaydi (yarim holat cheksiz yo'naltirish
  // tsikliga olib kelgani uchun shunday qilingan). Faqat token berilsa, barcha
  // himoyalangan sahifalar `/login` ga qaytariladi va test ma'nosiz bo'ladi.
  const meResponse = await context.request.get(`${BASE}/api/v1/me`, {
    headers: { Authorization: `Bearer ${TOKEN}`, Accept: 'application/json' },
  });

  if (!meResponse.ok()) {
    throw new Error(`Token yaroqsiz: GET /api/v1/me -> HTTP ${meResponse.status()}`);
  }

  const { user } = await meResponse.json();
  console.log(`Hisob: ${user.username} (${user.role}), ${user.permissions?.length ?? 0} huquq
`);

  await context.addInitScript(
    ([token, userObj]) => {
      localStorage.setItem('auth_token', JSON.stringify(token));
      localStorage.setItem('auth_user', JSON.stringify(userObj));
    },
    [TOKEN, user]
  );

  const results = [];

  for (const [route, label] of ROUTES) {
    const errors = [];
    const failedRequests = [];

    const page = await context.newPage();

    page.on('console', (msg) => {
      if (msg.type() !== 'error') return;
      const text = msg.text();
      if (!isNoise(text)) errors.push(text.slice(0, 300));
    });
    page.on('pageerror', (err) => {
      const text = `UNCAUGHT: ${err.message}`;
      if (!isNoise(text)) errors.push(text.slice(0, 300));
    });
    page.on('requestfailed', (req) => {
      const text = `${req.method()} ${req.url()} -> ${req.failure()?.errorText}`;
      if (!isNoise(text)) failedRequests.push(text.slice(0, 200));
    });
    page.on('response', (res) => {
      if (res.status() >= 500) failedRequests.push(`HTTP ${res.status()} ${res.url().slice(0, 160)}`);
    });

    let status = 'ok';
    let finalUrl = '';
    let gremlinsRan = false;

    try {
      await page.goto(BASE + route, { waitUntil: 'domcontentloaded', timeout: 30000 });
      // Ma'lumot so'rovlari tugashini kutamiz; tugamasa ham davom etamiz.
      await page.waitForLoadState('networkidle', { timeout: 15000 }).catch(() => {});
      finalUrl = new URL(page.url()).pathname;

      // `window.gremlins` ni `main.tsx` dev-rejimda o'rnatadi.
      await page.waitForFunction(() => typeof window.gremlins === 'function', { timeout: 10000 });

      await page.evaluate(
        async ([nb, delay]) => { await window.gremlins({ nb, delay }); },
        [NB, DELAY]
      );
      gremlinsRan = true;
    } catch (e) {
      status = 'error';
      errors.push(`RUNNER: ${String(e.message).split('\n')[0].slice(0, 200)}`);
    }

    await page.close();

    const row = {
      route,
      label,
      finalUrl,
      status,
      gremlinsRan,
      errors: [...new Set(errors)],
      failedRequests: [...new Set(failedRequests)],
    };
    results.push(row);

    // Sahifa `/login` ga qaytarilgan bo'lsa, gremlins o'sha sahifani emas,
    // login formasini urgan bo'ladi — natija yaroqsiz. Buni yashirmaymiz.
    const bouncedToLogin = route !== '/login' && finalUrl === '/login';
    row.bouncedToLogin = bouncedToLogin;

    const mark = bouncedToLogin
      ? 'AUTH'
      : row.errors.length === 0 && row.failedRequests.length === 0
        ? 'OK  '
        : 'XATO';
    console.log(
      `${mark} ${route.padEnd(24)} ${gremlinsRan ? `${NB} amal` : 'gremlins ishlamadi'}` +
        ` | xato=${row.errors.length} tarmoq=${row.failedRequests.length}` +
        (bouncedToLogin ? ' | LOGIN ga qaytarildi — natija yaroqsiz' : '')
    );
  }

  await browser.close();

  writeFileSync('gremlins-report.json', JSON.stringify(results, null, 2), 'utf8');

  const broken = results.filter((r) => r.errors.length || r.failedRequests.length);
  console.log(`\nJami: ${results.length} sahifa, ${broken.length} tasida muammo.`);
  const bounced = results.filter((r) => r.bouncedToLogin);
  if (bounced.length) {
    console.log(
      `DIQQAT: ${bounced.length} sahifa /login ga qaytarildi — ular aslida ` +
        'tekshirilmadi. Token yoki huquqlarni tekshiring.'
    );
  }
  console.log('Batafsil: frontend/gremlins-report.json');
};

run().catch((e) => {
  console.error(e);
  process.exit(1);
});
