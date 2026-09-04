import React from 'react';
import { Send, ScanLine } from 'lucide-react';
import { useT } from '@/shared/presentation/i18n/i18n';
import qrImage from '@/assets/telegram-bot-qr.png';

// Bot nomi .env orqali almashtiriladi — bot o'zgarsa kodga tegish shart emas.
// QR rasm ham yangilanishi kerakligini unutmaslik uchun izoh: rasm
// src/assets/telegram-bot-qr.png da.
const BOT_USERNAME = import.meta.env.VITE_TELEGRAM_BOT_USERNAME || 'XBHELPBOT';
const BOT_URL = `https://t.me/${BOT_USERNAME}`;

/**
 * Login sahifasidagi Telegram bot paneli.
 *
 * Maqsadi: kompyuterida muammo bo'lib saytga kira olmayotgan xodim zayavkani
 * baribir yubora olsin — telefonidan QR ni skanerlab botga o'tadi.
 */
export const TelegramBotPanel: React.FC = () => {
  const t = useT();

  return (
    <aside className="w-full max-w-md lg:max-w-[19rem] p-6 bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 transition-all">
      <div className="text-center">
        <div className="inline-flex items-center justify-center w-11 h-11 rounded-xl bg-sky-500 text-white shadow-lg mb-3">
          <Send className="w-5 h-5" />
        </div>
        <h2 className="text-base font-bold text-gray-900 dark:text-gray-100">
          {t('loginPage.botTitle')}
        </h2>
        <p className="mt-2 text-xs leading-relaxed text-gray-500 dark:text-gray-400">
          {t('loginPage.botDescription')}
        </p>
      </div>

      {/* QR doim oq fonda qoladi — qorong'i mavzuda ham skanerlanishi uchun */}
      <div className="mt-5 rounded-xl bg-white p-3 border border-gray-200 dark:border-gray-600">
        <img
          src={qrImage}
          alt={t('loginPage.botQrAlt')}
          width={528}
          height={528}
          className="w-full h-auto block"
        />
      </div>

      <p className="mt-3 flex items-center justify-center gap-1.5 text-[11px] font-semibold text-gray-400 dark:text-gray-500">
        <ScanLine className="w-3.5 h-3.5 flex-shrink-0" />
        <span>{t('loginPage.botScanHint')}</span>
      </p>

      <a
        href={BOT_URL}
        target="_blank"
        rel="noopener noreferrer"
        className="mt-4 w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-sky-500 hover:bg-sky-600 text-white font-bold text-sm shadow-sm hover:shadow-md transition-all"
      >
        <Send className="w-4 h-4" />
        <span>@{BOT_USERNAME}</span>
      </a>
    </aside>
  );
};
