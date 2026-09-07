import React, { useEffect, useState } from 'react';
import { Navigate } from 'react-router-dom';
import { LoginForm } from '@/modules/authentication/infrastructure/presentation/components/LoginForm';
import { TelegramBotPanel } from '@/modules/authentication/infrastructure/presentation/components/TelegramBotPanel';
import { useAuthStore } from '@/shared/presentation/store/useAuthStore';
import {
  readCredentials,
  forgetCredentials,
  remainingVisibleMs,
  type RecentCredentials,
} from '@/shared/infrastructure/storage/recentCredentials';
import { CheckSquare, KeyRound, Mail, Copy, Check, Eye, EyeOff } from 'lucide-react';
import { useT } from '@/shared/presentation/i18n/i18n';
import { LanguageSwitcher } from '@/shared/presentation/i18n/LanguageSwitcher';
import loginBuilding from '@/assets/login-building.jpg';

export const LoginPage: React.FC = () => {
  const t = useT();
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);
  const [recent, setRecent] = useState<RecentCredentials | null>(null);
  const [copied, setCopied] = useState<string | null>(null);
  // Kredensiallar yashirin — "To'liq ko'rish" bosilgandagina ko'rsatiladi
  const [showFull, setShowFull] = useState(false);

  // "Sizning login va parolingiz" paneli. Ma'lumot SERVERDAN emas, hisobni
  // ochgan odamning O'Z brauzeridan o'qiladi — aks holda login sahifasini
  // ochgan har kim oxirgi yaratilgan xodimning loginini ko'rardi.
  // Panel 10 daqiqadan keyin o'z-o'zidan yo'qoladi.
  useEffect(() => {
    const saved = readCredentials();
    if (!saved) {
      return;
    }

    setRecent(saved);

    const timer = window.setTimeout(() => {
      setRecent(null);
      forgetCredentials();
    }, remainingVisibleMs(saved));

    return () => window.clearTimeout(timer);
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
    <div className="min-h-screen relative overflow-hidden flex flex-col p-3 sm:p-4 bg-gradient-to-br from-gray-50 via-brand-50/30 to-gray-100 dark:from-[#0a1226] dark:via-[#0d1830] dark:to-[#060c1a]">
      {/* Fon dekoratsiyasi — kontentga xalaqit bermaydi (pointer-events-none).
          Bank binosi o'ng tomonda turadi va gradient bilan chapga qarab
          yo'qoladi: kirish kartasi ustidagi matn har doim o'qilarli qoladi. */}
      <div aria-hidden="true" className="pointer-events-none absolute inset-0 overflow-hidden">
        <img
          src={loginBuilding}
          alt=""
          className="absolute right-0 top-0 h-full w-[58%] max-w-none object-cover object-left opacity-25 dark:opacity-30"
        />
        <div className="absolute inset-0 bg-gradient-to-r from-gray-50 via-gray-50/92 to-gray-50/25 dark:from-[#0a1226] dark:via-[#0a1226]/92 dark:to-[#0a1226]/45" />
        <div className="absolute -top-40 -left-32 w-[520px] h-[520px] rounded-full bg-brand-500/10 blur-3xl" />
        <div className="absolute -bottom-52 -right-32 w-[620px] h-[620px] rounded-full bg-blue-500/10 dark:bg-blue-600/10 blur-3xl" />
      </div>

      {/* Yuqori chap: logotip, nom va shior */}
      <header className="relative z-10 flex items-start gap-3">
        <div className="w-11 h-11 sm:w-12 sm:h-12 flex-shrink-0 rounded-2xl bg-brand-600 flex items-center justify-center text-white shadow-lg shadow-brand-500/30">
          <CheckSquare className="w-6 h-6 sm:w-7 sm:h-7" />
        </div>
        <div>
          <span className="block text-2xl sm:text-3xl font-extrabold leading-none text-slate-900 dark:text-white">
            Task<span className="text-brand-500">Flow</span>
          </span>
          <span className="block mt-1 text-[11px] font-medium text-slate-400 dark:text-slate-500">
            {t('loginPage.tagline')}
          </span>
        </div>
      </header>

      <div className="absolute top-4 right-4 sm:top-6 sm:right-6 z-20">
        <LanguageSwitcher />
      </div>

      {/* Kirish kartasi sahifa markazida; QR paneli lg dan boshlab uning
          chap yonida suzib turadi, kichik ekranda esa ostiga tushadi. */}
      <main className="relative z-10 flex-1 w-full flex items-center justify-center py-2">
        <div className="relative w-full max-w-md">
          <LoginForm />

          {/* Oxirgi yaratilgan pochta kredensiallari — yashirin, "To'liq
              ko'rish" bilan. Ma'lumot SERVERDAN emas, hisobni ochgan odamning
              O'Z brauzeridan o'qiladi va 10 daqiqadan keyin yo'qoladi.

              sm dan boshlab oqimdan chiqarilgan (absolute, kartaning tagida):
              panel chiqqanda yoki "To'liq ko'rish" bilan kengayganda
              yuqoridagi kirish kartasi qimirlamasligi kerak. */}
          {recent && (
            <div className="mt-3 w-full rounded-2xl border border-emerald-200 dark:border-emerald-800 bg-emerald-50/70 dark:bg-emerald-950/40 p-3 shadow-sm backdrop-blur-sm sm:absolute sm:top-full sm:left-0 sm:right-0 sm:w-auto">
              <div className="flex items-center justify-between mb-2">
                <p className="text-[11px] font-bold uppercase tracking-wide text-emerald-600 dark:text-emerald-400">
                  {t('loginPage.yourCredentials')}
                </p>
                <button
                  type="button"
                  onClick={() => setShowFull((v) => !v)}
                  className="inline-flex items-center space-x-1.5 text-[11px] font-bold text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300 transition-colors"
                >
                  {showFull ? <EyeOff className="w-3.5 h-3.5" /> : <Eye className="w-3.5 h-3.5" />}
                  <span>{showFull ? t('loginPage.hideCredentials') : t('loginPage.showFullCredentials')}</span>
                </button>
              </div>

              {showFull ? (
                <div className="space-y-2">
                  <div className="flex items-center space-x-2.5 rounded-lg bg-white/70 dark:bg-gray-900/40 px-3 py-1.5">
                    <Mail className="w-4 h-4 text-emerald-500 flex-shrink-0" />
                    <span className="flex-1 text-[13px] font-bold text-gray-800 dark:text-gray-100 break-all select-all">
                      {recent.email}
                    </span>
                    <CopyButton label="login" value={recent.email} />
                  </div>
                  <div className="flex items-center space-x-2.5 rounded-lg bg-white/70 dark:bg-gray-900/40 px-3 py-1.5">
                    <KeyRound className="w-4 h-4 text-emerald-500 flex-shrink-0" />
                    <span className="flex-1 text-[11px] font-semibold text-gray-500 dark:text-gray-400">
                      {t('loginPage.passwordShownOnce')}
                    </span>
                  </div>
                </div>
              ) : (
                <p className="text-[11px] text-emerald-700/70 dark:text-emerald-300/60 font-medium">
                  {t('loginPage.credentialsHiddenHint')}
                </p>
              )}
            </div>
          )}
        </div>
      </main>

      {/* Telegram/QR paneli lg dan boshlab sahifaning chap chekkasida suzib
          turadi (kartaga emas, sahifaga nisbatan — shunda ekran kengaysa ham
          chap burchakda qoladi). Kichik ekranda oddiy oqimda, kartadan keyin. */}
      <div className="relative z-10 mt-6 flex justify-center lg:mt-[229px] lg:absolute lg:left-6 lg:top-1/2 lg:-translate-y-[40%] xl:left-10">
        <TelegramBotPanel />
      </div>

      {/* Departament va mualliflik izohi — profil kartasidagi footer bilan bir xil matn */}
      <footer className="relative z-10 mt-3 w-full px-4">
        <div className="flex items-center justify-center gap-4">
          <span className="h-px w-12 sm:w-20 bg-slate-200 dark:bg-slate-700/70" />
          <p className="text-center text-xs sm:text-sm font-bold text-slate-600 dark:text-slate-300">
            {t('profileCard.footerDepartment')}
          </p>
          <span className="h-px w-12 sm:w-20 bg-slate-200 dark:bg-slate-700/70" />
        </div>
        <p className="mt-1.5 text-center text-[11px] font-medium text-slate-400 dark:text-slate-500">
          {t('profileCard.footerCredit1')} {t('profileCard.footerCredit2')} {t('profileCard.footerCredit3')}
        </p>
        <p className="mt-1 text-center text-[10px] font-medium text-slate-400 dark:text-slate-600">
          {t('profileCard.copyright', { year: String(new Date().getFullYear()) })}
        </p>
      </footer>
    </div>
  );
};
