import React from 'react';
import { Send, ScanLine } from 'lucide-react';
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
 * O'lchami QAT'IY 220×350 px. Matn o'lchamlari shunga moslangan: eng uzun
 * til (rus) da ham kontent sig'ishi kerak. `overflow-hidden` — tarjima
 * uzayib ketsa karta cho'zilmaydi, o'lcham saqlanadi.
 */
export const TelegramBotPanel: React.FC = () => {
  const t = useT();

  return (
    <aside className="w-[220px] h-[350px] p-3 flex flex-col overflow-hidden bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-100 dark:border-gray-700 transition-all">
      <div className="text-center">
        <div className="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-sky-500 text-white shadow-sm mb-1.5">
          <Send className="w-4 h-4" />
        </div>
        <h2 className="text-xs font-bold leading-tight text-gray-900 dark:text-gray-100">
          {t('loginPage.botTitle')}
        </h2>
        <p className="mt-1 text-[10px] leading-snug text-gray-500 dark:text-gray-400">
          {t('loginPage.botDescription')}
        </p>
      </div>

      {/* QR doim oq fonda qoladi — qorong'i mavzuda ham skanerlanishi uchun.
          O'lchami qat'iy: karta balandligi matn qatorlariga qarab o'zgarmasin. */}
      <div className="mt-2 mx-auto rounded bg-white p-1 border border-gray-200 dark:border-gray-600">
        <img
          src={qrImage}
          alt={t('loginPage.botQrAlt')}
          width={580}
          height={580}
          className="w-[148px] h-[148px] block"
        />
      </div>

      <p className="mt-1.5 flex items-start justify-center gap-1 text-[9px] font-semibold leading-tight text-gray-400 dark:text-gray-500">
        <ScanLine className="w-2.5 h-2.5 flex-shrink-0 mt-px" />
        <span>{t('loginPage.botScanHint')}</span>
      </p>

      {/* Bot nomi — bosilmaydi. Havola emas, shunchaki yozuv, shuning uchun
          hover/cursor effektlari ham yo'q: bosiladigandek ko'rinmasligi kerak. */}
      <div className="mt-auto w-full flex items-center justify-center gap-1.5 px-2 py-1.5 rounded-lg bg-sky-500 text-white font-bold text-[11px] leading-tight shadow-sm select-all">
        <Send className="w-3.5 h-3.5" />
        <span>@{BOT_USERNAME}</span>
      </div>
    </aside>
  );
};
