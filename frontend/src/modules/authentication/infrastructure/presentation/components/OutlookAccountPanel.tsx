import React from 'react';
import { Link } from 'react-router-dom';
import { ArrowRight } from 'lucide-react';
import { useT } from '@/shared/presentation/i18n/i18n';

/**
 * Kirish kartasi ichidagi "AD pochtangiz yo'qmi?" bloki.
 *
 * AD (Outlook) pochtasi bo'lmagan xodim tizimga umuman kira olmaydi —
 * shuning uchun pochta ochish yo'li login formasining darhol ostida turadi.
 * Tugma /ad-account sahifasiga olib boradi.
 *
 * Logotip rasm fayl emas, inline SVG: mavzu (light/dark) almashganda ham
 * bir xil ko'rinadi va qo'shimcha yuklanish talab qilmaydi.
 */

const OutlookLogo: React.FC<{ className?: string }> = ({ className }) => (
  <svg viewBox="0 0 48 48" fill="none" className={className} aria-hidden="true">
    {/* Orqadagi ochroq varaq — haqiqiy Outlook belgisidagi kabi */}
    <rect x="20" y="6" width="22" height="16" rx="2" fill="#28A8EA" />
    <rect x="20" y="22" width="22" height="16" rx="2" fill="#0078D4" />
    <rect x="20" y="14" width="22" height="12" fill="#1490DF" opacity="0.55" />
    {/* Asosiy plitka */}
    <rect x="4" y="10" width="26" height="28" rx="4" fill="#0364B8" />
    <ellipse cx="17" cy="24" rx="8.5" ry="9.5" fill="#fff" />
    <ellipse cx="17" cy="24" rx="4.2" ry="5.2" fill="#0364B8" />
  </svg>
);

export const OutlookAccountPanel: React.FC = () => {
  const t = useT();

  return (
    <div className="p-3 rounded-2xl border border-slate-200 dark:border-slate-700/70 bg-slate-50 dark:bg-slate-800/40">
      <div className="flex items-start gap-2.5">
        <OutlookLogo className="w-7 h-7 flex-shrink-0 mt-0.5" />
        <div className="min-w-0">
          <h2 className="text-[13px] font-bold text-slate-900 dark:text-white">
            {t('loginPage.outlookPromptTitle')}
          </h2>
          <p className="mt-0.5 text-[11px] leading-snug text-slate-500 dark:text-slate-400">
            {t('loginPage.outlookPromptText')}
          </p>
        </div>
      </div>

      {/* Strelka o'ng chekkada — matn tugma markazida qoladi */}
      <Link
        to="/ad-account"
        className="relative mt-2.5 flex items-center justify-center px-4 py-2 rounded-xl border border-brand-400/60 dark:border-brand-500/50 bg-white dark:bg-slate-900/40 text-brand-600 dark:text-brand-300 font-bold text-[13px] hover:bg-brand-50 dark:hover:bg-slate-800 transition-all"
      >
        <span>{t('loginPage.outlookCta')}</span>
        <ArrowRight className="w-4 h-4 absolute right-4 top-1/2 -translate-y-1/2" />
      </Link>
    </div>
  );
};
