import React, { useCallback, useEffect, useRef, useState } from 'react';
import { Check, Loader2, Minus, Plus, X } from 'lucide-react';
import { useT } from '@/shared/presentation/i18n/i18n';

/** Qirqish oynasidagi ko'rish maydoni (px). Chiquvchi rasm ham kvadrat. */
const VIEW_SIZE = 288;

/** Saqlanadigan rasm tomoni (px) — Retina ekranda ham yetarli. */
const OUTPUT_SIZE = 512;

const JPEG_QUALITY = 0.92;

const MIN_ZOOM = 1;
const MAX_ZOOM = 4;

interface AvatarCropperModalProps {
  /** Tanlangan faylning object URL manzili. */
  src: string;
  onCancel: () => void;
  onConfirm: (dataUrl: string) => void;
}

const clamp = (value: number, min: number, max: number) => Math.min(max, Math.max(min, value));

/**
 * Telegramdagidek avatar qirqish oynasi: rasm dumaloq oyna ichida suriladi va
 * kattalashtiriladi, saqlanganda AYNAN o'sha ko'rinayotgan qism tushadi.
 *
 * Ilgari fayl tanlangan zahoti butun rasm avatarga tushardi — foydalanuvchi
 * qaysi qismi ko'rinishini boshqara olmasdi.
 */
export const AvatarCropperModal: React.FC<AvatarCropperModalProps> = ({ src, onCancel, onConfirm }) => {
  const t = useT();
  const imageRef = useRef<HTMLImageElement | null>(null);
  const dragStart = useRef<{ x: number; y: number; offsetX: number; offsetY: number } | null>(null);

  const [natural, setNatural] = useState<{ width: number; height: number } | null>(null);
  const [zoom, setZoom] = useState(1);
  const [offset, setOffset] = useState({ x: 0, y: 0 });
  const [isSaving, setIsSaving] = useState(false);

  // "cover" masshtabi: rasmning kichik tomoni oynani to'liq qoplaydi.
  const baseScale = natural ? VIEW_SIZE / Math.min(natural.width, natural.height) : 1;
  const scale = baseScale * zoom;
  const displayWidth = natural ? natural.width * scale : 0;
  const displayHeight = natural ? natural.height * scale : 0;

  /** Rasm oynadan uzilib qolmasin — suriladigan masofa cheklanadi. */
  const clampOffset = useCallback(
    (next: { x: number; y: number }) => {
      const maxX = Math.max(0, (displayWidth - VIEW_SIZE) / 2);
      const maxY = Math.max(0, (displayHeight - VIEW_SIZE) / 2);

      return { x: clamp(next.x, -maxX, maxX), y: clamp(next.y, -maxY, maxY) };
    },
    [displayWidth, displayHeight]
  );

  useEffect(() => {
    setOffset((current) => clampOffset(current));
  }, [clampOffset]);

  const handlePointerDown = (event: React.PointerEvent<HTMLDivElement>) => {
    event.currentTarget.setPointerCapture(event.pointerId);
    dragStart.current = { x: event.clientX, y: event.clientY, offsetX: offset.x, offsetY: offset.y };
  };

  const handlePointerMove = (event: React.PointerEvent<HTMLDivElement>) => {
    if (!dragStart.current) return;
    setOffset(
      clampOffset({
        x: dragStart.current.offsetX + (event.clientX - dragStart.current.x),
        y: dragStart.current.offsetY + (event.clientY - dragStart.current.y),
      })
    );
  };

  const handlePointerUp = (event: React.PointerEvent<HTMLDivElement>) => {
    event.currentTarget.releasePointerCapture(event.pointerId);
    dragStart.current = null;
  };

  const handleWheel = (event: React.WheelEvent<HTMLDivElement>) => {
    setZoom((current) => clamp(current - event.deltaY / 500, MIN_ZOOM, MAX_ZOOM));
  };

  const handleConfirm = () => {
    const image = imageRef.current;
    if (!image || !natural) return;

    setIsSaving(true);
    try {
      const canvas = document.createElement('canvas');
      canvas.width = OUTPUT_SIZE;
      canvas.height = OUTPUT_SIZE;
      const ctx = canvas.getContext('2d');
      if (!ctx) return;

      ctx.imageSmoothingEnabled = true;
      ctx.imageSmoothingQuality = 'high';

      // Ko'rish oynasining chap-yuqori burchagi rasmning qaysi nuqtasiga
      // to'g'ri kelishini hisoblaymiz — ekrandagi ko'rinish bilan bir xil.
      const left = VIEW_SIZE / 2 + offset.x - displayWidth / 2;
      const top = VIEW_SIZE / 2 + offset.y - displayHeight / 2;
      const sourceSize = VIEW_SIZE / scale;

      ctx.drawImage(
        image,
        -left / scale,
        -top / scale,
        sourceSize,
        sourceSize,
        0,
        0,
        OUTPUT_SIZE,
        OUTPUT_SIZE
      );

      onConfirm(canvas.toDataURL('image/jpeg', JPEG_QUALITY));
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <div className="fixed inset-0 z-[70] flex items-center justify-center bg-slate-900/70 backdrop-blur-sm p-4">
      <div className="w-full max-w-sm rounded-3xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 shadow-2xl p-5 space-y-4">
        <div className="flex items-center justify-between">
          <p className="text-sm font-extrabold text-slate-900 dark:text-slate-100">{t('profile.cropTitle')}</p>
          <button
            type="button"
            onClick={onCancel}
            className="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
            aria-label={t('common.cancel')}
          >
            <X className="w-4 h-4" />
          </button>
        </div>

        <div
          className="relative mx-auto overflow-hidden rounded-2xl bg-slate-900 touch-none cursor-grab active:cursor-grabbing"
          style={{ width: VIEW_SIZE, height: VIEW_SIZE }}
          onPointerDown={handlePointerDown}
          onPointerMove={handlePointerMove}
          onPointerUp={handlePointerUp}
          onPointerCancel={handlePointerUp}
          onWheel={handleWheel}
        >
          <img
            ref={imageRef}
            src={src}
            alt=""
            draggable={false}
            onLoad={(event) => {
              const target = event.currentTarget;
              setNatural({ width: target.naturalWidth, height: target.naturalHeight });
              setOffset({ x: 0, y: 0 });
              setZoom(1);
            }}
            className="absolute left-1/2 top-1/2 max-w-none select-none"
            style={{
              width: displayWidth || undefined,
              height: displayHeight || undefined,
              transform: `translate(calc(-50% + ${offset.x}px), calc(-50% + ${offset.y}px))`,
            }}
          />

          {/* Dumaloq oyna: tashqarisi xiralashadi, ichidagi qism saqlanadi. */}
          <div className="pointer-events-none absolute inset-0 rounded-full border-2 border-white/80 shadow-[0_0_0_9999px_rgba(15,23,42,0.6)]" />
        </div>

        <div className="flex items-center gap-3">
          <Minus className="w-4 h-4 text-slate-400 flex-shrink-0" />
          <input
            type="range"
            min={MIN_ZOOM}
            max={MAX_ZOOM}
            step={0.01}
            value={zoom}
            onChange={(event) => setZoom(Number(event.target.value))}
            className="w-full accent-brand-500"
            aria-label={t('profile.cropZoom')}
          />
          <Plus className="w-4 h-4 text-slate-400 flex-shrink-0" />
        </div>

        <p className="text-[11px] font-semibold text-slate-500 dark:text-slate-400 text-center">
          {t('profile.cropHint')}
        </p>

        <div className="flex gap-2">
          <button
            type="button"
            onClick={onCancel}
            className="flex-1 px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold"
          >
            {t('common.cancel')}
          </button>
          <button
            type="button"
            onClick={handleConfirm}
            disabled={!natural || isSaving}
            className="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-500 disabled:opacity-50 text-white text-xs font-extrabold"
          >
            {isSaving ? <Loader2 className="w-4 h-4 animate-spin" /> : <Check className="w-4 h-4" />}
            {t('profile.cropApply')}
          </button>
        </div>
      </div>
    </div>
  );
};
