import React from 'react';
import { useToastStore, ToastMessage } from '../store/useToastStore';
import { CheckCircle2, AlertCircle, AlertTriangle, Info, X } from 'lucide-react';

export const ToastContainer: React.FC = () => {
  const { toasts, removeToast } = useToastStore();

  if (toasts.length === 0) return null;

  const getToastIcon = (type: ToastMessage['type']) => {
    switch (type) {
      case 'success':
        return <CheckCircle2 className="w-5 h-5 text-emerald-500 flex-shrink-0" />;
      case 'error':
        return <AlertCircle className="w-5 h-5 text-rose-500 flex-shrink-0" />;
      case 'warning':
        return <AlertTriangle className="w-5 h-5 text-amber-500 flex-shrink-0" />;
      default:
        return <Info className="w-5 h-5 text-blue-500 flex-shrink-0" />;
    }
  };

  const getToastClasses = (type: ToastMessage['type']) => {
    switch (type) {
      case 'success':
        return 'bg-white dark:bg-slate-900 border-emerald-500/30 text-slate-900 dark:text-slate-100 shadow-emerald-500/5';
      case 'error':
        return 'bg-white dark:bg-slate-900 border-rose-500/30 text-slate-900 dark:text-slate-100 shadow-rose-500/5';
      case 'warning':
        return 'bg-white dark:bg-slate-900 border-amber-500/30 text-slate-900 dark:text-slate-100 shadow-amber-500/5';
      default:
        return 'bg-white dark:bg-slate-900 border-blue-500/30 text-slate-900 dark:text-slate-100 shadow-blue-500/5';
    }
  };

  return (
    <aside
      aria-live="polite"
      aria-atomic="true"
      className="fixed bottom-4 left-4 right-4 z-50 flex flex-col space-y-2.5 pointer-events-none sm:left-auto sm:right-5 sm:bottom-5 sm:w-full sm:max-w-md"
    >
      {toasts.map((toast) => (
        <div
          key={toast.id}
          role="status"
          className={`pointer-events-auto flex items-start space-x-3 p-4 rounded-2xl border shadow-xl backdrop-blur-md transition-all duration-300 transform animate-in slide-in-from-bottom-5 fade-in ${getToastClasses(
            toast.type
          )}`}
        >
          {getToastIcon(toast.type)}
          <div className="flex-1 min-w-0 pr-2">
            {toast.title && <h4 className="text-xs font-black uppercase tracking-wider mb-0.5">{toast.title}</h4>}
            <p className="text-xs font-semibold leading-relaxed text-slate-700 dark:text-slate-300">{toast.message}</p>
          </div>
          <button
            onClick={() => removeToast(toast.id)}
            className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors"
            aria-label="Close notification"
          >
            <X className="w-4 h-4" />
          </button>
        </div>
      ))}
    </aside>
  );
};
