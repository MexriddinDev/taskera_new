/**
 * Ism bosh harflaridan avatar rasmi — `data:` URI ko'rinishidagi SVG.
 *
 * Ilgari bu ish `https://ui-avatars.com` xizmatiga topshirilgandi. Ikki
 * sababga ko'ra olib tashlandi:
 *
 *   1. MAXFIYLIK — har bir avatar ko'rsatilganda xodimning to'liq ismi
 *      tashqi serverga so'rov manzilida yuborilardi.
 *   2. ISHONCHLILIK — tizim lokal tarmoqda ishlaydi. Internet bo'lmasa
 *      barcha avatarlar singan rasmga aylanardi va har sahifa o'nlab
 *      muvaffaqiyatsiz so'rov yuborardi.
 *
 * Natija tashqi so'rovsiz, bir xil ism uchun doim bir xil rangda chiqadi.
 */

/** Fon ranglari — to'q, oq harf ustida kontrast yetarli. */
const COLORS = ['#0D8ABC', '#7C3AED', '#059669', '#D97706', '#DC2626', '#0891B2', '#4F46E5', '#BE185D'];

const initialsOf = (name: string): string => {
  const words = name.trim().split(/\s+/).filter(Boolean);
  if (words.length === 0) return '?';

  const first = words[0][0] ?? '';
  const second = words.length > 1 ? (words[1][0] ?? '') : '';

  return (first + second).toLocaleUpperCase();
};

/** Bir ism — bir rang: ro'yxat aralashganda ham avatar "sakramaydi". */
const colorOf = (name: string): string => {
  let hash = 0;
  for (let i = 0; i < name.length; i++) {
    hash = (hash * 31 + name.charCodeAt(i)) | 0;
  }
  return COLORS[Math.abs(hash) % COLORS.length];
};

export const initialsAvatar = (name?: string | null, size = 256): string => {
  const safeName = (name || '').trim() || '?';
  const initials = initialsOf(safeName);
  const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 100 100">`
    + `<rect width="100" height="100" fill="${colorOf(safeName)}"/>`
    + `<text x="50" y="50" dy="0.35em" fill="#fff" font-family="system-ui,-apple-system,Segoe UI,Roboto,sans-serif"`
    + ` font-size="${initials.length > 1 ? 38 : 46}" font-weight="700" text-anchor="middle">${initials}</text>`
    + '</svg>';

  // `btoa` lotin bo'lmagan harflarda (kirill F.I.Sh) xato beradi, shuning
  // uchun base64 emas, URL-kodlash ishlatiladi.
  return `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`;
};
