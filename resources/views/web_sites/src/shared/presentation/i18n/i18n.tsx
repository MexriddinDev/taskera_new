import React, { createContext, useContext, useEffect, useMemo, useState } from 'react';
import { translations, type Lang } from './translations';

export type TFunction = (key: string, vars?: Record<string, string | number>) => string;

type I18nValue = {
  lang: Lang;
  setLang: (lang: Lang) => void;
  t: TFunction;
};

const STORAGE_KEY = 'taskera_lang';

const I18nContext = createContext<I18nValue>({
  lang: 'uz',
  setLang: () => {},
  t: (key) => key,
});

export const useI18n = (): I18nValue => useContext(I18nContext);

export const useT = (): TFunction => useContext(I18nContext).t;

const readStoredLang = (): Lang => {
  try {
    const stored = localStorage.getItem(STORAGE_KEY);
    if (stored === 'uz' || stored === 'ru' || stored === 'en') {
      return stored;
    }
  } catch {
    // localStorage mavjud emas — default til
  }
  return 'uz';
};

export const I18nProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [lang, setLangState] = useState<Lang>(readStoredLang);

  const setLang = (next: Lang) => {
    setLangState(next);
    try {
      localStorage.setItem(STORAGE_KEY, next);
    } catch {
      // localStorage mavjud emas
    }
  };

  useEffect(() => {
    document.documentElement.lang = lang;
  }, [lang]);

  const t: TFunction = (key, vars) => {
    let text = translations[lang]?.[key] ?? translations.uz[key] ?? key;
    if (vars) {
      for (const [k, v] of Object.entries(vars)) {
        text = text.split(`{${k}}`).join(String(v));
      }
    }
    return text;
  };

  const value = useMemo<I18nValue>(() => ({ lang, setLang, t }), [lang]);

  return <I18nContext.Provider value={value}>{children}</I18nContext.Provider>;
};