import { storage } from './localStorage';

/**
 * Yangi ochilgan AD hisobining login/pochtasi — FAQAT o'sha brauzerda.
 *
 * XAVFSIZLIK: ilgari bu ma'lumot `GET /ad-account/recent` orqali olinardi va u
 * endpoint autentifikatsiyasiz edi — login sahifasini ochgan HAR KIM oxirgi
 * yaratilgan xodimning login va pochtasini ko'rardi. Endpointni muntazam
 * so'rab turgan odam barcha yangi hisoblarni yig'ib olishi mumkin edi.
 *
 * Endi ma'lumot serverda emas, hisobni ochgan odamning o'z brauzerida saqlanadi.
 */
const KEY = 'recentAdCredentials';

/** Kredensiallar ko'rinadigan vaqt — 10 daqiqa. */
export const CREDENTIALS_VISIBLE_MS = 10 * 60 * 1000;

export interface RecentCredentials {
  username: string;
  email: string;
  /** Saqlangan vaqt (ms) — muddatni shundan hisoblaymiz. */
  savedAt: number;
}

export const rememberCredentials = (username: string, email: string): void => {
  storage.set<RecentCredentials>(KEY, { username, email, savedAt: Date.now() });
};

export const forgetCredentials = (): void => {
  storage.remove(KEY);
};

/**
 * Muddati o'tmagan kredensiallarni qaytaradi. Muddati o'tgan bo'lsa yozuvni
 * o'chirib, null qaytaradi — eskirgan ma'lumot brauzerda yotib qolmaydi.
 */
export const readCredentials = (): RecentCredentials | null => {
  const saved = storage.get<RecentCredentials>(KEY);

  if (!saved?.username || !saved?.email || typeof saved.savedAt !== 'number') {
    return null;
  }

  if (Date.now() - saved.savedAt >= CREDENTIALS_VISIBLE_MS) {
    forgetCredentials();
    return null;
  }

  return saved;
};

/** Yozuv o'z-o'zidan yo'qolguncha qolgan vaqt (ms). */
export const remainingVisibleMs = (saved: RecentCredentials): number =>
  Math.max(0, CREDENTIALS_VISIBLE_MS - (Date.now() - saved.savedAt));
