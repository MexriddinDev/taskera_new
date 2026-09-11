/** Saqlanadigan avatarning eng uzun tomoni (px). Ilovadagi eng katta avatar
 *  ~176px, Retina ekranda 352px kerak — 512 zaxira bilan yetarli. */
const AVATAR_MAX_SIDE = 512;

/** JPEG sifati. 0.92 — ko'z bilan yo'qotishsiz, hajmi esa bir necha baravar kichik. */
const JPEG_QUALITY = 0.92;

const loadImage = (file: File): Promise<HTMLImageElement> =>
  new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file);
    const img = new Image();
    img.onload = () => {
      URL.revokeObjectURL(url);
      resolve(img);
    };
    img.onerror = () => {
      URL.revokeObjectURL(url);
      reject(new Error('Rasmni o\'qib bo\'lmadi'));
    };
    img.src = url;
  });

const readAsDataUrl = (file: File): Promise<string> =>
  new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(reader.result as string);
    reader.onerror = () => reject(new Error('Faylni o\'qib bo\'lmadi'));
    reader.readAsDataURL(file);
  });

const makeCanvas = (width: number, height: number): HTMLCanvasElement => {
  const canvas = document.createElement('canvas');
  canvas.width = width;
  canvas.height = height;
  return canvas;
};

const smooth = (ctx: CanvasRenderingContext2D): void => {
  ctx.imageSmoothingEnabled = true;
  ctx.imageSmoothingQuality = 'high';
};

/** Shaffoflikni saqlash kerak bo'lgan formatlar — ular PNG bo'lib qaytadi. */
const TRANSPARENT_TYPES = ['image/png', 'image/webp', 'image/gif', 'image/svg+xml'];

/**
 * Avatarni faqat KICHRAYTIRADI — nisbatini saqlagan holda.
 *
 * Ilgari bu yerda markazdan kvadrat kesib olinardi va rasmning chetlari
 * butunlay yo'qolardi (kesilgan rasm bazaga ham shundayligicha tushardi).
 * Endi rasm asl nisbatida qoladi, faqat eng uzun tomoni 512px gacha
 * kichraytiriladi — profilda rasm to'liq ko'rinadi.
 *
 * Nega bosqichma-bosqich: telefonda olingan rasm 3000x4000 bo'ladi. Brauzer uni
 * bir bosqichda kichraytirsa natija xira chiqadi, shuning uchun har safar taxminan
 * ikki barobar kichraytiriladi — bu usul chetlarni tiniq saqlaydi. Qo'shimcha
 * foyda: base64 hajmi keskin kamayadi, u esa har bir javobda uzatiladi.
 *
 * Canvas ishlamasa (juda eski brauzer yoki buzuq fayl) — asl fayl qaytariladi,
 * ya'ni yuklash baribir ishlaydi.
 */
export const resizeAvatar = async (file: File): Promise<string> => {
  try {
    const img = await loadImage(file);
    const width = img.naturalWidth;
    const height = img.naturalHeight;
    if (!width || !height) return readAsDataUrl(file);

    // Shaffof formatlarda JPEG fon qora bo'lib qolardi — ular PNG bo'lib chiqadi.
    const keepsAlpha = TRANSPARENT_TYPES.includes(file.type);

    let current = makeCanvas(width, height);
    const ctx = current.getContext('2d');
    if (!ctx) return readAsDataUrl(file);
    smooth(ctx);
    ctx.drawImage(img, 0, 0, width, height);

    // Bosqichma-bosqich yarmiga kichraytirish — eng uzun tomon maqsad
    // o'lchamdan oshib turgan ekan davom etadi.
    let currentWidth = width;
    let currentHeight = height;
    while (Math.max(currentWidth, currentHeight) > AVATAR_MAX_SIDE * 2) {
      const nextWidth = Math.max(1, Math.round(currentWidth / 2));
      const nextHeight = Math.max(1, Math.round(currentHeight / 2));
      const next = makeCanvas(nextWidth, nextHeight);
      const nextCtx = next.getContext('2d');
      if (!nextCtx) break;
      smooth(nextCtx);
      nextCtx.drawImage(current, 0, 0, currentWidth, currentHeight, 0, 0, nextWidth, nextHeight);
      current = next;
      currentWidth = nextWidth;
      currentHeight = nextHeight;
    }

    // Rasm allaqachon kichik bo'lsa kattalashtirilmaydi — bor o'lchamida qoladi.
    const scale = Math.min(1, AVATAR_MAX_SIDE / Math.max(currentWidth, currentHeight));
    const finalWidth = Math.max(1, Math.round(currentWidth * scale));
    const finalHeight = Math.max(1, Math.round(currentHeight * scale));

    const output = makeCanvas(finalWidth, finalHeight);
    const outputCtx = output.getContext('2d');
    if (!outputCtx) return readAsDataUrl(file);
    smooth(outputCtx);
    outputCtx.drawImage(current, 0, 0, currentWidth, currentHeight, 0, 0, finalWidth, finalHeight);

    return keepsAlpha
      ? output.toDataURL('image/png')
      : output.toDataURL('image/jpeg', JPEG_QUALITY);
  } catch {
    return readAsDataUrl(file);
  }
};
