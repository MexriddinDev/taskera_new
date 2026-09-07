import React from 'react';
import { Send } from 'lucide-react';
import { useT } from '@/shared/presentation/i18n/i18n';
import qrImage from '@/assets/telegram-bot-qr.png';

// Bot nomi .env orqali almashtiriladi — bot o'zgarsa kodga tegish shart emas.
// QR rasm ham yangilanishi kerakligini unutmaslik uchun izoh: rasm
// src/assets/telegram-bot-qr.png da.
const BOT_USERNAME = import.meta.env.VITE_TELEGRAM_BOT_USERNAME || 'XBHELPBOT';

/**
 * Login sahifasidagi Telegram bot paneli.
 *
 * Maqsadi: kompyuterida muammo bo'lib saytga kira olmayotgan xodim zayavkani
 * baribir yubora olsin — telefonidan QR ni skanerlab botga o'tadi.
 *
 * Kengligi QAT'IY 240px, balandligi kontent bo'yicha. QR 145px —
 * manba rasm 580px, ya'ni aniq 1/4 kichraytirish: modullar tiniq qoladi
 * va kod telefon kamerasi bilan ishonchli o'qiladi.
 */
export const TelegramBotPanel: React.FC = () => {
  const t = useT();

  return (
    <aside className="w-full max-w-[280px] lg:w-[240px] p-4 flex flex-col bg-white dark:bg-slate-900/70 rounded-3xl shadow-xl shadow-slate-200/50 dark:shadow-black/30 border border-slate-200 dark:border-slate-700/70 backdrop-blur-sm transition-all">
      <div className="flex items-start gap-2.5">
        <span className="w-8 h-8 flex-shrink-0 rounded-full bg-sky-500 text-white flex items-center justify-center">
          <Send className="w-4 h-4" />
        </span>
        <div className="min-w-0">
          <h2 className="text-[13px] font-bold leading-tight text-slate-900 dark:text-white">
            {t('loginPage.botTitle')}
          </h2>
          <p className="mt-1 text-[11px] leading-snug text-slate-500 dark:text-slate-400">
            {t('loginPage.botDescription')}
          </p>
        </div>
      </div>

      {/* QR doim oq fonda qoladi — qorong'i mavzuda ham skanerlanishi uchun */}
      <div className="mt-3 mx-auto rounded-xl bg-white p-2">
        <img
          src={qrImage}
          alt={t('loginPage.botQrAlt')}
          width={580}
          height={580}
          className="w-[145px] h-[145px] block"
        />
      </div>

      {/* Bot nomi — bosilmaydi. Havola emas, shunchaki yozuv, shuning uchun
          hover/cursor effektlari ham yo'q: bosiladigandek ko'rinmasligi kerak. */}
      <div className="mt-3 mx-auto flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-sky-500/15 text-sky-600 dark:text-sky-300 font-bold text-[11px] leading-tight select-all">
        <Send className="w-3 h-3 flex-shrink-0" />
        <span>@{BOT_USERNAME.toLowerCase()}</span>
      </div>
    </aside>
  );
};
