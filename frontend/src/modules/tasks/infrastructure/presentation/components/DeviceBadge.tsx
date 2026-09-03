import React from 'react';
import { Monitor, Smartphone, Tablet, Send, HelpCircle } from 'lucide-react';
import type { TaskDevice, TaskDeviceKind } from '../../../domain/entities/Task';

interface DeviceBadgeProps {
  device?: TaskDevice;
  source?: 'web' | 'telegram';
  /** `compact` — ro'yxatdagi kartochka uchun (faqat ikonka + qisqa matn) */
  variant?: 'compact' | 'full';
  className?: string;
}

const ICONS: Record<TaskDeviceKind, React.ComponentType<{ className?: string }>> = {
  desktop: Monitor,
  mobile: Smartphone,
  tablet: Tablet,
  telegram: Send,
  unknown: HelpCircle,
};

const SHORT_LABELS: Record<TaskDeviceKind, string> = {
  desktop: 'Kompyuter',
  mobile: 'Telefon',
  tablet: 'Planshet',
  telegram: 'Telegram',
  unknown: '—',
};

/**
 * Zayavka qaysi manbadan va qaysi qurilmadan kelganini ko'rsatadi.
 *
 * Qurilma backendda zayavka YARATILAYOTGAN paytda aniqlanib saqlanadi
 * (tickets.metadata.device), shuning uchun bu yorliq zayavkani ochgan
 * odamning qurilmasiga bog'liq emas.
 */
export const DeviceBadge: React.FC<DeviceBadgeProps> = ({
  device,
  source,
  variant = 'compact',
  className = '',
}) => {
  // DIQQAT: `??` bu yerda yetarli emas edi — backend har doim device obyektini
  // qaytaradi va telegram zayavkalarida uning kind'i 'unknown' bo'lishi mumkin.
  // 'unknown' null emas, shuning uchun zaxira variant ishlamay, ikonka o'rniga
  // "—" chiqib qolardi.
  const resolvedKind = device?.kind && device.kind !== 'unknown' ? device.kind : undefined;
  const kind: TaskDeviceKind = resolvedKind ?? (source === 'telegram' ? 'telegram' : 'unknown');
  const Icon = ICONS[kind] ?? HelpCircle;

  // Telegram uchun qurilma turi ma'lum emas — manbaning o'zi yetarli ma'lumot.
  const isTelegram = kind === 'telegram';
  const text = variant === 'full' && resolvedKind && device?.label ? device.label : SHORT_LABELS[kind];

  const tone = isTelegram
    ? 'text-sky-600 dark:text-sky-400 bg-sky-50 dark:bg-sky-950/40'
    : 'text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-800';

  return (
    <span
      title={device?.label ?? SHORT_LABELS[kind]}
      className={`inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md text-[10px] font-bold ${tone} ${className}`}
    >
      <Icon className="w-3 h-3 flex-shrink-0" />
      <span className="truncate">{text}</span>
    </span>
  );
};
