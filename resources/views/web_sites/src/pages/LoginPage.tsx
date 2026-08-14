import React, { useEffect, useState } from 'react';
import { Navigate, Link } from 'react-router-dom';
import { LoginForm } from '@/modules/authentication/infrastructure/presentation/components/LoginForm';
import { useAuthStore } from '@/shared/presentation/store/useAuthStore';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { CheckSquare, UserPlus, KeyRound, Mail, Copy, Check } from 'lucide-react';
import { useT } from '@/shared/presentation/i18n/i18n';
import { LanguageSwitcher } from '@/shared/presentation/i18n/LanguageSwitcher';

type RecentAccount = {
  username: string;
  email: string;
  password: string;
  created_at?: string;
};

export const LoginPage: React.FC = () => {
  const t = useT();
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);
  const [recent, setRecent] = useState<RecentAccount | null>(null);
  const [copied, setCopied] = useState<string | null>(null);

  // Oxirgi yaratilgan pochta kredensiallarini ko'rsatish —
  // "Sizning login va parolingiz" paneli
  useEffect(() => {
    axiosClient
      .get('/ad-account/recent')
      .then((res) => {
        const a = res.data?.account;
        if (a) setRecent({ username: a.username, email: a.email, password: a.password, created_at: a.created_at });
      })
      .catch(() => {
        // Panel ixtiyoriy — xato bo'lsa ko'rsatilmaydi
      });
  }, []);

  const copyToClipboard = async (label: string, value: string) => {
    try {
      await navigator.clipboard.writeText(value);
      setCopied(label);
      window.setTimeout(() => setCopied(null), 1500);
    } catch {
      // Clipboard mavjud emas (http bo'lsa) — tanlab olishni tavsiya qilamiz
      setCopied(null);
    }
  };

  if (isAuthenticated) {
    return <Navigate to="/dashboard" replace />;
  }

  const CopyButton: React.FC<{ label: string; value: string }> = ({ label, value }) => (
    <button
      type="button"
      onClick={() => copyToClipboard(label, value)}
      title="Nusxalash"
      className="flex-shrink-0 w-8 h-8 rounded-lg flex items-center justify-center text-emerald-600 dark:text-emerald-400 hover:bg-emerald-100 dark:hover:bg-emerald-800/50 transition-all"
    >
      {copied === label ? <Check className="w-4 h-4" /> : <Copy className="w-4 h-4" />}
    </button>
  );

  return (
    <div className="min-h-screen flex flex-col justify-center items-center p-4 bg-gradient-to-br from-gray-50 via-brand-50/20 to-gray-100 dark:from-gray-900 dark:via-gray-900 dark:to-gray-950">
      <div className="absolute top-4 right-4">
        <LanguageSwitcher />
      </div>
      <div className="flex items-center space-x-3 mb-8">
        <div className="w-12 h-12 rounded-xl bg-brand-600 flex items-center justify-center text-white shadow-lg">
          <CheckSquare className="w-7 h-7" />
        </div>
        <span className="text-3xl font-extrabold bg-gradient-to-r from-brand-600 to-brand-400 bg-clip-text text-transparent">
          TaskFlow
        </span>
      </div>

      <LoginForm />

      {/* Oxirgi yaratilgan pochta kredensiallari */}
      {recent && (
        <div className="mt-6 w-full max-w-md rounded-2xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50/70 dark:bg-emerald-950/40 p-4 shadow-sm">
          <p className="text-xs font-bold uppercase tracking-wide text-emerald-600 dark:text-emerald-400 mb-3">
            {t('loginPage.yourCredentials')}
          </p>
          <div className="space-y-2.5">
            <div className="flex items-center space-x-2.5 rounded-lg bg-white/70 dark:bg-gray-900/40 px-3 py-2">
              <Mail className="w-4 h-4 text-emerald-500 flex-shrink-0" />
              <span className="flex-1 text-sm font-bold text-gray-800 dark:text-gray-100 break-all select-all">
                {recent.email}
              </span>
              <CopyButton label="login" value={recent.email} />
            </div>
            <div className="flex items-center space-x-2.5 rounded-lg bg-white/70 dark:bg-gray-900/40 px-3 py-2">
              <KeyRound className="w-4 h-4 text-emerald-500 flex-shrink-0" />
              <span className="flex-1 text-sm font-bold text-gray-800 dark:text-gray-100 break-all select-all">
                {recent.password}
              </span>
              <CopyButton label="password" value={recent.password} />
            </div>
          </div>
        </div>
      )}

      {/* Yangi xodim: pochta (AD) ochilmagan bo'lsa */}
      <div className="mt-6 text-center space-y-1.5">
        <span className="block text-[11px] font-semibold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
          {t('loginPage.noAccountTitle')}
        </span>
        <Link
          to="/ad-account"
          className="inline-flex items-center space-x-2 px-5 py-2.5 rounded-2xl bg-white dark:bg-slate-800 border border-brand-200 dark:border-brand-800 text-brand-600 dark:text-brand-400 font-bold text-xs shadow-sm hover:shadow-md hover:bg-brand-50 dark:hover:bg-slate-700 transition-all"
        >
          <UserPlus className="w-4 h-4" />
          <span>{t('loginPage.createAccountCta')}</span>
        </Link>
      </div>
    </div>
  );
};