import React from 'react';
import { useI18n } from './i18n';
import { LANGS } from './translations';
import { Languages } from 'lucide-react';

export const LanguageSwitcher: React.FC<{ className?: string }> = ({ className = '' }) => {
  const { lang, setLang } = useI18n();

  return (
    <div
      className={`flex items-center rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm ${className}`}
      title="Tilni tanlash"
    >
      <span className="hidden sm:flex pl-2.5 text-slate-400 dark:text-slate-500">
        <Languages className="w-4 h-4" />
      </span>
      {LANGS.map((l, idx) => (
        <button
          key={l.code}
          type="button"
          onClick={() => setLang(l.code)}
          className={`px-3 py-1.5 text-xs font-bold transition-all ${
            lang === l.code
              ? 'text-brand-600 dark:text-brand-300'
              : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200'
          } ${idx === 0 ? 'rounded-l-xl' : ''} ${idx === LANGS.length - 1 ? 'rounded-r-xl' : ''}`}
        >
          {l.label}
        </button>
      ))}
    </div>
  );
};