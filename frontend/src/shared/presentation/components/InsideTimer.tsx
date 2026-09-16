import React, { useEffect, useState } from 'react';

/**
 * Tashrifchi ichkarida turgan vaqt.
 *
 * "Kirdi" bosilgan paytdan yuradi va "Chiqdi" bosilganda to'xtaydi — qorovul
 * posti mehmon qancha vaqt ichkarida ekanini sahifani yangilamasdan ko'rib
 * turadi.
 *
 * Tick komponentning O'ZIDA: har soniyada faqat shu raqam qayta chiziladi,
 * ro'yxat yoki tafsilot sahifasi emas. Ko'z tegmayotgan varaqda soat
 * yurgizilmaydi (`visibilitychange`), aks holda fon varaqlari ham har soniyada
 * uyg'onardi.
 */
export const InsideTimer: React.FC<{ enteredAt: string | null; exitedAt: string | null }> = React.memo(
  ({ enteredAt, exitedAt }) => {
    const [now, setNow] = useState(Date.now());

    useEffect(() => {
      if (!enteredAt || exitedAt) return;

      const tick = () => {
        if (document.visibilityState === 'visible') setNow(Date.now());
      };
      const interval = window.setInterval(tick, 1000);
      document.addEventListener('visibilitychange', tick);

      return () => {
        window.clearInterval(interval);
        document.removeEventListener('visibilitychange', tick);
      };
    }, [enteredAt, exitedAt]);

    if (!enteredAt) return null;

    const end = exitedAt ? new Date(exitedAt).getTime() : now;
    const seconds = Math.max(0, Math.floor((end - new Date(enteredAt).getTime()) / 1000));
    const days = Math.floor(seconds / 86400);
    const hh = Math.floor((seconds % 86400) / 3600).toString().padStart(2, '0');
    const mm = Math.floor((seconds % 3600) / 60).toString().padStart(2, '0');
    const ss = (seconds % 60).toString().padStart(2, '0');
    const clock = `${hh}:${mm}:${ss}`;

    return <span className="font-mono tabular-nums">{days > 0 ? `${days}d ${clock}` : clock}</span>;
  },
);
