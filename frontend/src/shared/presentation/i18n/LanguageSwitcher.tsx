import React from 'react';
import { useI18n } from './i18n';
import { LANGS } from './translations';
import { Globe } from 'lucide-react';

export const LanguageSwitcher: React.FC<{ className?: string }> = ({ className = '' }) => {
  const { lang, setLang } = useI18n();

  return (
    <div
      className={`flex items-center gap-1 p-1 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 shadow-sm ${className}`}
      title="Tilni tanlash"
    >
      <span className="hidden sm:flex px-1.5 text-slate-400 dark:text-slate-500">
        <Globe className="w-4 h-4" />
      </span>
      {LANGS.map((l) => (
        <button
          key={l.code}
          type="button"
          onClick={() => setLang(l.code)}
          className={`px-2.5 py-1 rounded-xl text-xs font-bold transition-all ${
            lang === l.code
              ? 'bg-brand-600 text-white shadow-sm'
              : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200'
          }`}
        >
          {l.label}
        </button>
      ))}
    </div>
  );
};
