import { useEffect, useRef, type ReactNode } from 'react';
import { X } from 'lucide-react';
import { useT } from '@/shared/presentation/i18n/i18n';
export function SlaDialog({ title, onClose, children, wide = false }: { title: string; onClose: () => void; children: ReactNode; wide?: boolean }) {
  const t = useT();
  const ref = useRef<HTMLDivElement>(null);
  const close = useRef(onClose);
  close.current = onClose;
  useEffect(() => {
    const previous = document.activeElement as HTMLElement | null;
    const overflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    ref.current?.focus();
    const handler = (event: KeyboardEvent) => {
      if (event.key === 'Escape') close.current();
      if (event.key !== 'Tab') return;
      const nodes = Array.from(ref.current?.querySelectorAll<HTMLElement>('button:not(:disabled), input:not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex="0"]') || []).filter(el => el.offsetParent !== null);
      if (!nodes.length) { event.preventDefault(); return; }
      if (event.shiftKey && (document.activeElement === nodes[0] || document.activeElement === ref.current)) { event.preventDefault(); nodes[nodes.length - 1]?.focus(); }
      else if (!event.shiftKey && document.activeElement === nodes[nodes.length - 1]) { event.preventDefault(); nodes[0].focus(); }
    };
    document.addEventListener('keydown', handler);
    return () => { document.removeEventListener('keydown', handler); document.body.style.overflow = overflow; previous?.focus(); };
  }, []);
  return <div className="fixed inset-0 z-[80] flex justify-end bg-slate-950/65 backdrop-blur-sm p-2 sm:p-4">
    <div ref={ref} role="dialog" aria-modal="true" aria-label={title} tabIndex={-1} className={`${wide ? 'max-w-6xl' : 'max-w-2xl'} w-full flex flex-col rounded-2xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-[#0d192b] text-slate-900 dark:text-slate-100 shadow-2xl outline-none`}>
      <header className="flex items-center justify-between gap-4 border-b border-slate-200 dark:border-slate-700/70 p-5"><div><h2 className="text-xl font-bold">{title}</h2><p className="mt-1 text-sm text-slate-500 dark:text-slate-400">{t('sla.dialogSubtitle')}</p></div><button className="p-2 rounded-lg hover:bg-slate-200 dark:hover:bg-slate-800" aria-label={t('sla.close')} onClick={onClose}><X size={20}/></button></header>
      {children}
    </div>
  </div>;
}
