/**
 * Gremlins.js — "maymun testi" (monkey testing) uchun dev-rejim vositasi.
 *
 * Sahifaga tasodifiy bosadi, formalarni to'ldiradi, aylantiradi va tugma
 * bosadi — maqsad qo'lda o'ylab topib bo'lmaydigan ketma-ketlikda ilovani
 * buzish va ErrorBoundary'gacha yetib boradigan xatolarni topish.
 *
 * ┌─ MUHIM ────────────────────────────────────────────────────────────────┐
 * │ Bu modul PRODUCTION BUILD'GA TUSHMAYDI: `main.tsx` dagi chaqiruv       │
 * │ `import.meta.env.DEV` ichida va dinamik `import()` bilan, shuning      │
 * │ uchun Rollup uni ekspluatatsiya bundle'idan butunlay chiqarib          │
 * │ tashlaydi.                                                            │
 * │                                                                       │
 * │ O'Z-O'ZIDAN ISHGA TUSHMAYDI. Hujum haqiqiy backendga boradi va         │
 * │ haqiqiy zayavka yaratib/o'chirib yuborishi mumkin — shuning uchun      │
 * │ faqat konsoldan qo'lda chaqiriladi.                                   │
 * └───────────────────────────────────────────────────────────────────────┘
 *
 * Ishlatish (brauzer konsolida, dev-serverda):
 *
 *     await gremlins()            // 100 ta amal, xavfsiz sozlamalar
 *     await gremlins({ nb: 500 }) // uzoqroq hujum
 *     await gremlins({ nb: 200, allowDestructive: true })  // EHTIYOT BO'LING
 *     gremlins.stop()             // yarmida to'xtatish
 *
 * Manba: https://github.com/marmelab/gremlins.js
 */

export interface GremlinsRunOptions {
  /** Jami amallar soni. Standart — 100. */
  nb?: number;
  /** Ikki amal orasidagi kutish, ms. Standart — 20. */
  delay?: number;
  /**
   * Shuncha JS xatosidan keyin hujum avtomatik to'xtaydi. Standart — 10.
   * Xatolar konsolga chiqadi — aynan shular qidirilayotgan natija.
   */
  maxErrors?: number;
  /**
   * `true` bo'lsa — o'chirish, chiqish, yuborish kabi tugmalar ham bosiladi.
   * Standart `false`: hujum ma'lumotni buzmasligi va sizni tizimdan
   * chiqarib yubormasligi uchun.
   */
  allowDestructive?: boolean;
}

/**
 * Bosilmaydigan tugmalar.
 *
 * Gremlin ko'r-ko'rona bosadi: "Tizimdan chiqish" ni birinchi amalda bosib
 * qo'ysa, qolgan 99 amal login sahifasida behuda ketadi; "O'chirish" esa
 * haqiqiy yozuvni o'chiradi. Matn uch tilda tekshiriladi, chunki ilova
 * uz/ru/en da ishlaydi.
 */
const DESTRUCTIVE_TEXT =
  /o'chir|ochir|удал|delete|remove|chiqish|выйти|выход|log\s?out|sign\s?out|yubor|отправ|submit|saqla|сохран|save|tasdiq|подтверд|confirm/i;

const isDestructive = (element: Element): boolean => {
  // Tugmaning o'zi yoki uning ichidagi matn.
  const label = [
    element.textContent ?? '',
    element.getAttribute('aria-label') ?? '',
    element.getAttribute('title') ?? '',
    element.getAttribute('name') ?? '',
  ].join(' ');

  if (DESTRUCTIVE_TEXT.test(label)) return true;

  // `type="submit"` — forma yuboradi, ya'ni serverga yozadi.
  if (element instanceof HTMLButtonElement && element.type === 'submit') return true;
  if (element instanceof HTMLInputElement && element.type === 'submit') return true;

  // Boshqa sahifaga yoki tashqi saytga olib chiqadigan havola: hujum
  // tekshirilayotgan ekrandan chiqib ketmasligi kerak.
  if (element instanceof HTMLAnchorElement) {
    const href = element.getAttribute('href') ?? '';
    if (href.startsWith('http') || href.startsWith('//')) return true;
  }

  return false;
};

let activeHorde: { stop: () => void } | null = null;

/** Hujumni yarmida to'xtatadi. */
export const stopGremlins = (): void => {
  if (!activeHorde) {
    console.info('[gremlins] Hozir ishlab turgan hujum yo\'q.');
    return;
  }
  activeHorde.stop();
  activeHorde = null;
  console.info('[gremlins] To\'xtatildi.');
};

export const runGremlins = async (options: GremlinsRunOptions = {}): Promise<void> => {
  const { nb = 100, delay = 20, maxErrors = 10, allowDestructive = false } = options;

  if (activeHorde) {
    console.warn('[gremlins] Hujum allaqachon ketyapti. Avval gremlins.stop() ni chaqiring.');
    return;
  }

  // Dinamik import: gremlins.js (~230 KB) faqat shu funksiya chaqirilganda
  // yuklanadi va dev bundle'ni ham og'irlashtirmaydi.
  const gremlins = await import('gremlins.js');

  const canClick = allowDestructive ? undefined : (element: Element) => !isDestructive(element);

  console.info(
    `[gremlins] Hujum boshlandi: ${nb} ta amal, ${delay}ms oraliq, ` +
      `${allowDestructive ? 'BUZUVCHI AMALLAR YOQILGAN' : 'buzuvchi amallar o\'chirilgan'}. ` +
      'To\'xtatish: gremlins.stop()'
  );

  const horde = gremlins.createHorde({
    species: [
      gremlins.species.clicker({ canClick }),
      gremlins.species.toucher({ canTouch: canClick }),
      gremlins.species.formFiller(),
      gremlins.species.scroller(),
      gremlins.species.typer(),
    ],
    mogwais: [
      // `alert`/`confirm` ni ushlab qoladi: modal oyna chiqsa hujum
      // butunlay qotib qolardi.
      gremlins.mogwais.alert(),
      gremlins.mogwais.fps(),
      // Xatolar sanog'i chegaradan oshsa — o'zi to'xtaydi.
      gremlins.mogwais.gizmo({ maxErrors }),
    ],
    strategies: [gremlins.strategies.distribution({ delay, nb })],
  });

  activeHorde = horde;

  try {
    await horde.unleash();
    console.info('[gremlins] Hujum tugadi. Yuqoridagi xato va ogohlantirishlarni ko\'rib chiqing.');
  } finally {
    activeHorde = null;
  }
};

/** `window.gremlins()` — konsoldan chaqirish uchun. */
export interface GremlinsGlobal {
  (options?: GremlinsRunOptions): Promise<void>;
  stop: () => void;
}

export const installGremlins = (): void => {
  const api = runGremlins as GremlinsGlobal;
  api.stop = stopGremlins;

  (window as unknown as { gremlins: GremlinsGlobal }).gremlins = api;

  console.info(
    '%c[gremlins]%c tayyor — konsolda `await gremlins()` deb chaqiring. ' +
      'To\'xtatish: `gremlins.stop()`',
    'color:#22c55e;font-weight:bold',
    'color:inherit'
  );
};
