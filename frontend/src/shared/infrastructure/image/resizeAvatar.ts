/** Saqlanadigan avatarning tomoni (px). Ilovadagi eng katta avatar 128px,
 *  Retina ekranda 256px kerak — 512 zaxira bilan yetarli. */
const AVATAR_SIZE = 512;

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

/**
 * Avatarni kvadrat qilib kesadi va 512x512 ga keltiradi.
 *
 * Nega kerak: telefonda olingan rasm 3000x4000 bo'ladi. Brauzer uni bevosita
 * 32px doiraga sig'dirganda bir bosqichda kichraytiradi va natija xira/yumshoq
 * chiqadi. Shuning uchun rasm oldindan bosqichma-bosqich (har safar ikki barobar)
 * kichraytiriladi — bu usul chetlarni tiniq saqlaydi. Qo'shimcha foyda: base64
 * hajmi keskin kamayadi, u esa har bir zayavka javobida uzatiladi.
 *
 * Canvas ishlamasa (juda eski brauzer yoki buzuq fayl) — asl fayl qaytariladi,
 * ya'ni yuklash baribir ishlaydi.
 */
export const resizeAvatar = async (file: File): Promise<string> => {
  try {
    const img = await loadImage(file);
    const side = Math.min(img.naturalWidth, img.naturalHeight);
    if (!side) return readAsDataUrl(file);

    // Markazdan kvadrat kesib olish.
    const sx = (img.naturalWidth - side) / 2;
    const sy = (img.naturalHeight - side) / 2;

    let current = makeCanvas(side, side);
    let ctx = current.getContext('2d');
    if (!ctx) return readAsDataUrl(file);
    smooth(ctx);
    ctx.drawImage(img, sx, sy, side, side, 0, 0, side, side);

    // Bosqichma-bosqich yarmiga kichraytirish — maqsad o'lchamdan oshib
    // turgan ekan davom etadi.
    let currentSide = side;
    while (currentSide > AVATAR_SIZE * 2) {
      const nextSide = Math.round(currentSide / 2);
      const next = makeCanvas(nextSide, nextSide);
      const nextCtx = next.getContext('2d');
      if (!nextCtx) break;
      smooth(nextCtx);
      nextCtx.drawImage(current, 0, 0, currentSide, currentSide, 0, 0, nextSide, nextSide);
      current = next;
      currentSide = nextSide;
    }

    // Rasm allaqachon kichik bo'lsa kattalashtirilmaydi — bor o'lchamida qoladi.
    const finalSide = Math.min(currentSide, AVATAR_SIZE);
    const output = makeCanvas(finalSide, finalSide);
    const outputCtx = output.getContext('2d');
    if (!outputCtx) return readAsDataUrl(file);
    smooth(outputCtx);
    outputCtx.drawImage(current, 0, 0, currentSide, currentSide, 0, 0, finalSide, finalSide);

    // PNG shaffofligi avatar uchun kerak emas — doira CSS bilan chiziladi.
    return output.toDataURL('image/jpeg', JPEG_QUALITY);
  } catch {
    return readAsDataUrl(file);
  }
};
