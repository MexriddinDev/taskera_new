// HR'dan ismlar ko'pincha KATTA harfda keladi, ism bo'lmasa esa backend login
// (yusuf.rahimboyev) qaytaradi — ikkalasi ham "Yusuf Rahimboyev" ko'rinishida chiqishi kerak.
const titleCase = (value: string): string =>
  value
    .toLocaleLowerCase()
    // So'z boshi — satr boshi, bo'sh joy yoki chiziqcha. Apostrofdan keyin (O'rinboy) katta harf qo'yilmaydi.
    .replace(/(^|[\s-])(\p{L})/gu, (_, sep: string, ch: string) => sep + ch.toLocaleUpperCase());

/** Tayyor ism yoki login satrini ko'rinadigan ko'rinishga keltiradi. */
export const formatPersonName = (value?: string | null): string => {
  const text = (value ?? '').trim();
  if (!text) return '';
  // Bo'sh joysiz, nuqta/pastki chiziqli satr — bu login: yusuf.rahimboyev → Yusuf Rahimboyev
  const readable = /\s/.test(text) ? text : text.split('@')[0].replace(/[._]+/g, ' ');
  return titleCase(readable.replace(/\s+/g, ' ').trim());
};

/** Ism-familiyadan to'liq ism; ikkalasi ham bo'lmasa login ishlatiladi. */
export const personName = (first?: string | null, last?: string | null, username?: string | null): string =>
  formatPersonName([first, last].map(part => part?.trim()).filter(Boolean).join(' ') || username);
