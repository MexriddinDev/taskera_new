import React from 'react';
import { useToastStore, ToastMessage } from '../store/useToastStore';
import { CheckCircle2, AlertCircle, AlertTriangle, Info } from 'lucide-react';
import { useT } from '../i18n/i18n';

/**
 * Bildirishnoma oynasi — ekran o'rtasida, "OK" tugmasi bilan.
 *
 * Navbatda bir nechta xabar bo'lsa eng eskisi ko'rsatiladi; "OK" bosilgach
 * keyingisi chiqadi. Shu sabab bir vaqtda bitta oyna turadi va xabarlar
 * bir-birini bosib ketmaydi.
 */
export const ToastContainer: React.FC = () => {
  const t = useT();
  const { toasts, removeToast } = useToastStore();
  const current: ToastMessage | undefined = toasts[0];

  // Escape ham yopadi — sichqonchaga qo'l cho'zmasdan davom etish uchun.
  React.useEffect(() => {
    if (!current) return;

    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') removeToast(current.id);
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [current, removeToast]);

  if (!current) return null;

  const getIcon = (type: ToastMessage['type']) => {
    switch (type) {
      case 'success':
        return <CheckCircle2 className="w-7 h-7 text-emerald-500" />;
      case 'error':
        return <AlertCircle className="w-7 h-7 text-rose-500" />;
      case 'warning':
        return <AlertTriangle className="w-7 h-7 text-amber-500" />;
      default:
        return <Info className="w-7 h-7 text-blue-500" />;
    }
  };

  const getAccent = (type: ToastMessage['type']) => {
    switch (type) {
      case 'success':
        return { ring: 'border-emerald-400 dark:border-emerald-600', halo: 'bg-emerald-50 dark:bg-emerald-950/60', button: 'bg-emerald-600 hover:bg-emerald-500' };
      case 'error':
        return { ring: 'border-rose-400 dark:border-rose-600', halo: 'bg-rose-50 dark:bg-rose-950/60', button: 'bg-rose-600 hover:bg-rose-500' };
      case 'warning':
        return { ring: 'border-amber-400 dark:border-amber-600', halo: 'bg-amber-50 dark:bg-amber-950/60', button: 'bg-amber-600 hover:bg-amber-500' };
      default:
        return { ring: 'border-blue-400 dark:border-blue-600', halo: 'bg-blue-50 dark:bg-blue-950/60', button: 'bg-blue-600 hover:bg-blue-500' };
    }
  };

  const accent = getAccent(current.type);

  return (
    <div className="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm animate-fadeIn">
      <div
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="toast-title"
        aria-describedby="toast-message"
        className={`w-full max-w-sm rounded-3xl border-2 bg-white dark:bg-slate-900 p-6 shadow-2xl space-y-4 text-center ${accent.ring}`}
      >
        <div className={`mx-auto w-14 h-14 rounded-2xl flex items-center justify-center ${accent.halo}`}>
          {getIcon(current.type)}
        </div>

        {current.title && (
          <h4 id="toast-title" className="text-sm font-black uppercase tracking-wider text-slate-900 dark:text-slate-100">
            {current.title}
          </h4>
        )}

        <p id="toast-message" className="text-sm font-semibold leading-relaxed text-slate-700 dark:text-slate-300">
          {current.message}
        </p>

        <button
          type="button"
          autoFocus
          onClick={() => removeToast(current.id)}
          className={`w-full px-6 py-2.5 rounded-xl text-white text-sm font-extrabold shadow-md transition-colors cursor-pointer ${accent.button}`}
        >
          {t('common.ok')}
        </button>
      </div>
    </div>
  );
};
