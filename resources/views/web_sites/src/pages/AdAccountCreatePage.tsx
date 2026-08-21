import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { CheckSquare, ArrowLeft, Phone, MessageSquareText, Loader2, AlertCircle, CheckCircle2, Smartphone, KeyRound, Fingerprint, Hash, UserCheck, RefreshCw, Link2, MailCheck, Info } from 'lucide-react';
import { useT } from '@/shared/presentation/i18n/i18n';
import { LanguageSwitcher } from '@/shared/presentation/i18n/LanguageSwitcher';

type Step = 'pinfl' | 'bxm' | 'phone' | 'code' | 'decision' | 'creating' | 'linking' | 'linked' | 'resetting' | 'done';

// Pochta (AD) yaratilish jarayoni progressi
const CREATION_STAGES = [
  { key: 'adAccount.stage1', label: 'Ma\'lumotlar tekshirilmoqda' },
  { key: 'adAccount.stage2', label: 'Akkaunt mavjudligi tekshirilmoqda' },
  { key: 'adAccount.stage3', label: 'Active Directory hisobi yaratilmoqda' },
  { key: 'adAccount.stage4', label: 'Guruhga qo\'shilmoqda' },
  { key: 'adAccount.stage5', label: 'Pochta qutisi ochilmoqda' },
];

type CreatedAccount = {
  username: string;
  email: string;
  password: string;
  ou?: string;
  group_dn?: string;
};

const CreatingProgress: React.FC<{
  pinfl: string;
  phone: string;
  bxmCode: string;
  onCreated: (account: CreatedAccount) => void;
  onError: (message: string) => void;
}> = ({ pinfl, phone, bxmCode, onCreated, onError }) => {
  const t = useT();
  const [stage, setStage] = useState(0);
  const [state, setState] = useState<'running' | 'done' | 'error'>('running');
  const [attempt, setAttempt] = useState(0);

  useEffect(() => {
    if (state !== 'running') return;
    const digits = phone.replace(/\D/g, '');
    const normalized = digits.length === 9 ? `+998${digits}` : `+${digits}`;

    let cancelled = false;
    (async () => {
      try {
        const res = await axiosClient.post('/ad-account/exchange', {
          pinfl,
          phone: normalized,
          bxm_code: bxmCode,
        });
        if (cancelled) return;
        setStage(CREATION_STAGES.length);
        setState('done');
        const a = res.data?.account;
        onCreated({
          username: a?.username ?? '',
          email: a?.email ?? '',
          password: a?.password ?? '',
          ou: a?.ou,
          group_dn: a?.group_dn,
        });
      } catch (err) {
        if (cancelled) return;
        setState('error');
        onError(t('common.errorGeneric'));
      }
    })();

    return () => {
      cancelled = true;
    };
    // attempt: "Qayta urinish" tugmasi bosilganda qayta ishga tushiradi
  }, [state, attempt, pinfl, phone, bxmCode, onCreated, onError]);

  // Real zapros davom etayotganda bosqichlar progressi (visual)
  useEffect(() => {
    if (state !== 'running') return;
    if (stage >= CREATION_STAGES.length - 1) return;
    const t = window.setTimeout(() => setStage((s) => Math.min(s + 1, CREATION_STAGES.length - 1)), 900);
    return () => window.clearTimeout(t);
  }, [stage, state]);

  if (state === 'error') {
    return (
      <div className="space-y-5 py-2">
        <div className="text-center">
          <div className="w-16 h-16 rounded-full mx-auto flex items-center justify-center border border-red-500/30 bg-red-50 dark:bg-red-950/40 text-red-500">
            <AlertCircle className="w-8 h-8" />
          </div>
          <h2 className="mt-4 text-lg font-extrabold text-gray-900 dark:text-gray-100">{t('adAccount.createFailed')}</h2>
        </div>
        <button
          type="button"
          onClick={() => {
            setState('running');
            setStage(0);
            setAttempt((a) => a + 1);
          }}
          className="w-full py-3 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-bold text-sm shadow-md transition-all flex items-center justify-center space-x-2"
        >
          <Loader2 className="w-4 h-4" />
          <span>{t('common.retry')}</span>
        </button>
      </div>
    );
  }

  const finished = state === 'done';

  return (
    <div className="space-y-5 py-2">
      <div className="text-center">
        <div
          className={`w-16 h-16 rounded-full mx-auto flex items-center justify-center border transition-all ${
            finished
              ? 'bg-success-50 dark:bg-success-700/20 text-success-500 border-success-500/30'
              : 'bg-brand-50 dark:bg-brand-700/20 text-brand-500 border-brand-500/30 animate-pulse'
          }`}
        >
          {finished ? <CheckCircle2 className="w-8 h-8" /> : <Loader2 className="w-8 h-8 animate-spin" />}
        </div>
        <h2 className="mt-4 text-lg font-extrabold text-gray-900 dark:text-gray-100">
          {finished ? t('adAccount.created') : t('adAccount.creating')}
        </h2>
      </div>

      <div className="space-y-3 pt-2">
        {CREATION_STAGES.map((s, i) => {
          const isDone = i < stage || finished;
          const isActive = i === stage && !finished;
          return (
            <div key={s.key} className="flex items-center space-x-3">
              <div
                className={`w-6 h-6 rounded-full flex items-center justify-center flex-shrink-0 transition-all ${
                  isDone
                    ? 'bg-success-500 text-white'
                    : isActive
                    ? 'bg-brand-600 text-white animate-pulse'
                    : 'bg-slate-200 dark:bg-slate-700 text-transparent'
                }`}
              >
                {isDone ? (
                  <CheckCircle2 className="w-4 h-4" />
                ) : isActive ? (
                  <Loader2 className="w-3.5 h-3.5 animate-spin" />
                ) : (
                  <Loader2 className="w-3.5 h-3.5" />
                )}
              </div>
              <span
                className={`text-sm transition-colors ${
                  isDone
                    ? 'text-gray-900 dark:text-gray-100 font-semibold'
                    : isActive
                    ? 'text-brand-700 dark:text-brand-300 font-semibold'
                    : 'text-gray-400 dark:text-gray-500'
                }`}
              >
                {t(s.key)}
              </span>            </div>
          );
        })}
      </div>
    </div>
  );
};

// Pochtani boshqa BXM ga biriktirish jarayoni (rotatsiya)
const LinkingProgress: React.FC<{
  pinfl: string;
  phone: string;
  bxmCode: string;
  onLinked: () => void;
  onError: (message: string) => void;
}> = ({ pinfl, phone, bxmCode, onLinked, onError }) => {
  const t = useT();
  const [state, setState] = useState<'running' | 'done' | 'error'>('running');
  const [attempt, setAttempt] = useState(0);

  useEffect(() => {
    if (state !== 'running') return;
    const digits = phone.replace(/\D/g, '');
    const normalized = digits.length === 9 ? `+998${digits}` : `+${digits}`;

    let cancelled = false;
    (async () => {
      try {
        await axiosClient.post('/ad-account/link-bxm', {
          pinfl,
          phone: normalized,
          bxm_code: bxmCode,
        });
        if (cancelled) return;
        setState('done');
        onLinked();
      } catch (err) {
        if (cancelled) return;
        setState('error');
        const anyErr = err as { response?: { data?: { message?: string } }; message?: string };
        onError(anyErr?.response?.data?.message || anyErr?.message || t('common.errorGeneric'));
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [state, attempt, pinfl, phone, bxmCode, onLinked, onError]);

  if (state === 'error') {
    return (
      <div className="space-y-5 py-2">
        <div className="text-center">
          <div className="w-16 h-16 rounded-full mx-auto flex items-center justify-center border border-red-500/30 bg-red-50 dark:bg-red-950/40 text-red-500">
            <AlertCircle className="w-8 h-8" />
          </div>
          <h2 className="mt-4 text-lg font-extrabold text-gray-900 dark:text-gray-100">{t('adAccount.linkFailed')}</h2>
        </div>
        <button
          type="button"
          onClick={() => {
            setState('running');
            setAttempt((a) => a + 1);
          }}
          className="w-full py-3 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-bold text-sm shadow-md transition-all flex items-center justify-center space-x-2"
        >
          <Loader2 className="w-4 h-4" />
          <span>{t('common.retry')}</span>
        </button>
      </div>
    );
  }

  return (
    <div className="space-y-5 py-2">
      <div className="text-center">
        <div className="w-16 h-16 rounded-full mx-auto flex items-center justify-center border border-brand-500/30 bg-brand-50 dark:bg-brand-700/20 text-brand-500 animate-pulse">
          <Loader2 className="w-8 h-8 animate-spin" />
        </div>
        <h2 className="mt-4 text-lg font-extrabold text-gray-900 dark:text-gray-100">{t('adAccount.linking')}</h2>
        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{t('adAccount.linkingHint')}</p>
      </div>
    </div>
  );
};

// Pochta yaratilgan — parolni almashtirish jarayoni
const ResetProgress: React.FC<{
  pinfl: string;
  phone: string;
  onReset: (account: CreatedAccount) => void;
  onError: (message: string) => void;
}> = ({ pinfl, phone, onReset, onError }) => {
  const t = useT();
  const [state, setState] = useState<'running' | 'done' | 'error'>('running');
  const [attempt, setAttempt] = useState(0);

  useEffect(() => {
    if (state !== 'running') return;
    const digits = phone.replace(/\D/g, '');
    const normalized = digits.length === 9 ? `+998${digits}` : `+${digits}`;

    let cancelled = false;
    (async () => {
      try {
        const res = await axiosClient.post('/ad-account/reset-password', {
          pinfl,
          phone: normalized,
        });
        if (cancelled) return;
        setState('done');
        const a = res.data?.account;
        onReset({
          username: a?.username ?? '',
          email: a?.email ?? '',
          password: a?.password ?? '',
          ou: a?.ou,
          group_dn: a?.group_dn,
        });
      } catch (err) {
        if (cancelled) return;
        setState('error');
        const anyErr = err as { response?: { data?: { message?: string } }; message?: string };
        onError(anyErr?.response?.data?.message || anyErr?.message || t('common.errorGeneric'));
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [state, attempt, pinfl, phone, onReset, onError]);

  if (state === 'error') {
    return (
      <div className="space-y-5 py-2">
        <div className="text-center">
          <div className="w-16 h-16 rounded-full mx-auto flex items-center justify-center border border-red-500/30 bg-red-50 dark:bg-red-950/40 text-red-500">
            <AlertCircle className="w-8 h-8" />
          </div>
          <h2 className="mt-4 text-lg font-extrabold text-gray-900 dark:text-gray-100">{t('adAccount.resetFailed')}</h2>
        </div>
        <button
          type="button"
          onClick={() => {
            setState('running');
            setAttempt((a) => a + 1);
          }}
          className="w-full py-3 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-bold text-sm shadow-md transition-all flex items-center justify-center space-x-2"
        >
          <Loader2 className="w-4 h-4" />
          <span>{t('common.retry')}</span>
        </button>
      </div>
    );
  }

  return (
    <div className="space-y-5 py-2">
      <div className="text-center">
        <div className="w-16 h-16 rounded-full mx-auto flex items-center justify-center border border-brand-500/30 bg-brand-50 dark:bg-brand-700/20 text-brand-500 animate-pulse">
          <Loader2 className="w-8 h-8 animate-spin" />
        </div>
        <h2 className="mt-4 text-lg font-extrabold text-gray-900 dark:text-gray-100">{t('adAccount.resetting')}</h2>
        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{t('adAccount.resettingHint')}</p>
      </div>
    </div>
  );
};

// Employee ma'lumotlari (API dan kelgan, BXM bosqichida ko'rsatiladi)
type EmployeeInfo = {
  first_name?: string;
  last_name?: string;
  middle_name?: string;
  department?: string;
  position?: string;
  bxm_code?: string;
  state?: string;
  condition_name?: string;
};

export const AdAccountCreatePage: React.FC = () => {
  const t = useT();
  const getErrorMessage = (e: unknown): string => {
    const anyErr = e as { response?: { data?: { message?: string } }; message?: string };
    return anyErr?.response?.data?.message || anyErr?.message || t('common.errorGeneric');
  };
  const [step, setStep] = useState<Step>('pinfl');
  const [pinfl, setPinfl] = useState('');
  const [bxmCode, setBxmCode] = useState('');
  const [phone, setPhone] = useState('');
  const [code, setCode] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [isChecking, setIsChecking] = useState(false);
  const [isSending, setIsSending] = useState(false);
  const [isVerifying, setIsVerifying] = useState(false);
  // API dan kelgan xodim ma'lumotlari
  const [employee, setEmployee] = useState<EmployeeInfo | null>(null);
  // Xodimga pochta (AD) allaqachon yaratilganmi (Exchange employeeID bo'yicha tekshiriladi)
  const [hasExchangeAccount, setHasExchangeAccount] = useState(false);
  // Rotatsiya: BXM mos kelmadi va pochta yaratilgan — boshqa BXM ga biriktirish taklifi
  const [isRotated, setIsRotated] = useState(false);
  // BXM tasdiqlanganidan keyin ishlatiladigan (API dagi) to'g'ri BXM kodi
  const [confirmedBxm, setConfirmedBxm] = useState('');
  // Yaratilgan pochta akkaunti (done ekranida ko'rsatiladi)
  const [account, setAccount] = useState<CreatedAccount | null>(null);
  // done ekrani parol almashtirishdan keyin chiqqanmi (sarlavha farqi uchun)
  const [isResetDone, setIsResetDone] = useState(false);
  // Qayta SMS yuborish mumkin bo'ladigan vaqt (unix ms) va joriy soat
  const [resendAt, setResendAt] = useState<number | null>(null);
  const [clock, setClock] = useState(() => Date.now());

  // Sahifa ochilganda SSO tokenni oldindan olib keshlash —
  // SMS yuborish paytida token kutish kerak bo'lmaydi.
  useEffect(() => {
    axiosClient.get('/ad-account/prepare').catch(() => {
      // Token olib bo'lmasa ham sahifa ishlashda davom etadi —
      // send-code paytida qayta uriniladi.
    });
  }, []);

  // Countdown: har soniyada qolgan vaqtni yangilaydi, tugagach to'xtaydi
  useEffect(() => {
    if (resendAt === null) return;
    const tick = () => {
      setClock(Date.now());
      if (Date.now() >= resendAt) setResendAt(null);
    };
    const t = window.setInterval(tick, 1000);
    return () => window.clearInterval(t);
  }, [resendAt]);

  const secondsLeft = resendAt !== null ? Math.max(0, Math.ceil((resendAt - clock) / 1000)) : 0;
  const waitingResend = secondsLeft > 0;

  const formatCountdown = (s: number) =>
    `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;

  // ── 1-bosqich: PINFL tekshirish ────────────────────────────────────────
  const handleCheckPinfl = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    if (pinfl.replace(/\D/g, '').length !== 14) {
      setError(t('adAccount.pinflError'));
      return;
    }
    setIsChecking(true);
    try {
      const res = await axiosClient.post('/ad-account/check-employee', {
        pinfl: pinfl.replace(/\D/g, ''),
      });
      setEmployee(res.data?.employee ?? null);
      // Exchange'da pochta yaratilganmi — employeeID (PINFL) orqali tekshiriladi
      setHasExchangeAccount(res.data?.has_exchange_account === true);
      setIsRotated(false);
      setError(null);
      setStep('bxm');
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setIsChecking(false);
    }
  };

  // ── 2-bosqich: BXM kodini tekshirish ───────────────────────────────────
  const handleCheckBxm = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    if (bxmCode.trim().length < 3) {
      setError(t('adAccount.bxmError'));
      return;
    }
    setIsChecking(true);
    try {
      const res = await axiosClient.post('/ad-account/check-bxm', {
        pinfl: pinfl.replace(/\D/g, ''),
        bxm_code: bxmCode.replace(/\D/g, ''),
      });
      const rotated = res.data?.rotated === true;
      setIsRotated(rotated);
      // To'g'ri kod — darhol telefon bosqichiga o'tiladi.
      // Noto'g'ri kod 422 qaytaradi — xato BXM bosqichida ko'rsatiladi.
      if (res.data?.matched === true) {
        setConfirmedBxm(res.data?.bxm_code ?? bxmCode.replace(/\D/g, ''));
        setHasExchangeAccount(res.data?.has_exchange_account === true);
        setError(null);
        setStep('phone');
        return;
      }
      // Mos kelmasa (200 va matched=false endi qaytmaydi) — xavfsizlik uchun
      setError(res.data?.message ?? getErrorMessage({}));
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setIsChecking(false);
    }
  };

  // ── 3-bosqich: Telefon raqamini tekshirish va SMS yuborish ────────────
  const requestCode = async () => {
    setError(null);
    setIsSending(true);
    try {
      const digits = phone.replace(/\D/g, '');
      const normalized = digits.length === 9 ? `+998${digits}` : `+${digits}`;
      const res = await axiosClient.post('/ad-account/send-code', {
        pinfl: pinfl.replace(/\D/g, ''),
        phone: normalized,
      });
      if (res.data?.already_sent) {
        const after = res.data?.resend_after;
        setResendAt(after ? Number(after) * 1000 : null);
        setError(t('adAccount.alreadySent'));
        return;
      }
      setError(null);
      setResendAt(null);
      setStep('code');
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setIsSending(false);
    }
  };

  const handleSendCode = (e: React.FormEvent) => {
    e.preventDefault();
    void requestCode();
  };

  // Telefon: "998" prefiksi avtomatik — foydalanuvchi faqat qolgan 9 ta raqamni kiritadi
  const handlePhoneChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    let digits = e.target.value.replace(/\D/g, '');
    if (digits.startsWith('998')) {
      digits = digits.slice(3);
    } else if (digits.length <= 3) {
      // Bacspace prefiksga kirib o'chirdi ("998" → "99"/"9") — qoldiq tozalanadi,
      // aks holda "99" qoldiq raqam bo'lib qolib ketardi.
      digits = '';
    }
    setPhone(digits.slice(0, 9));
  };

  // "998 90 000 00 00" — guruhlar orasida bo'sh joy (3-2-3-2-2)
  const formatPhoneDisplay = (digits: string) => {
    const d = digits.replace(/\D/g, '');
    if (!d) return '';
    const groups = [d.slice(0, 3), d.slice(3, 5), d.slice(5, 8), d.slice(8, 10), d.slice(10, 12)].filter(Boolean);
    return groups.join(' ');
  };

  // Prefiks ("998") ustiga bosilganda cursor matn oxiriga o'tadi
  const handlePhoneKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
    const el = e.currentTarget;
    if (el.selectionStart !== null && el.selectionStart < 3) {
      e.preventDefault();
      el.setSelectionRange(el.value.length, el.value.length);
    }
  };

  // ── 4-bosqich: SMS kodini tasdiqlash ───────────────────────────────────
  const handleVerifyCode = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setIsVerifying(true);
    try {
      const digits = phone.replace(/\D/g, '');
      const normalized = digits.length === 9 ? `+998${digits}` : `+${digits}`;
      await axiosClient.post('/ad-account/verify-code', { phone: normalized, code });
      // Telefon tasdiqlangach qaror ekrani chiqadi:
      //  - pochta yaratilmagan → yaratish
      //  - pochta yaratilgan + BXM mos → parol almashtirish
      //  - rotatsiya (BXM mos emas, pochta bor) → boshqa BXM ga biriktirish
      setStep('decision');
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setIsVerifying(false);
    }
  };

  // ── Yakuniy: Exchange'da pochta yaratish natijalari ────────────────────
  const handleAccountCreated = (acc: CreatedAccount) => {
    setAccount(acc);
    setIsResetDone(false);
    setStep('done');
  };

  const handleCreationError = (message: string) => {
    setError(message);
  };

  // ── Rotatsiya: pochta boshqa BXM ga biriktirilgach — "Biriktirildi" ekrani ──
  const handleLinked = () => {
    setStep('linked');
  };

  // ── Parol almashtirilgach — yangi login/parol done ekranida ─────────────
  const handleReset = (acc: CreatedAccount) => {
    setAccount(acc);
    setIsResetDone(true);
    setStep('done');
  };

  const employeeFullName = employee
    ? [employee.last_name, employee.first_name, employee.middle_name].filter(Boolean).join(' ')
    : '';

  const stepIndexes: Record<string, number> = { pinfl: 0, bxm: 1, phone: 2, code: 3 };
  const stepKeys = ['pinfl', 'bxm', 'phone', 'code'] as const;

  const isProgressStep = step === 'creating' || step === 'linking' || step === 'linked' || step === 'resetting' || step === 'done' || step === 'decision';

  return (
    <div className="min-h-screen flex flex-col justify-center items-center p-4 bg-gradient-to-br from-gray-50 via-brand-50/20 to-gray-100 dark:from-gray-900 dark:via-gray-900 dark:to-gray-950">
      <div className="w-full max-w-md">
        {/* Header */}
        <div className="flex items-center justify-center space-x-3 mb-8">
          <div className="w-12 h-12 rounded-xl bg-brand-600 flex items-center justify-center text-white shadow-lg">
            <CheckSquare className="w-7 h-7" />
          </div>
          <span className="text-3xl font-extrabold bg-gradient-to-r from-brand-600 to-brand-400 bg-clip-text text-transparent">
            TaskFlow
          </span>
        </div>

        <div className="absolute top-4 right-4">
          <LanguageSwitcher />
        </div>

        <div className="w-full p-8 bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 transition-all space-y-6">
          <div className="text-center">
            <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">{t('adAccount.title')}</h1>
            <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
              {t('adAccount.subtitle')}
            </p>
          </div>

          {/* Step indicator */}
          {!isProgressStep && (
            <div className="flex items-center justify-center space-x-2">
              {stepKeys.map((s, idx) => {
                const stepIndex = idx;
                const currentIndex = stepIndexes[step] ?? 0;
                const finished = false;
                const isDone = finished || currentIndex > stepIndex;
                const isCurrent = currentIndex === stepIndex && !finished;
                return (
                  <React.Fragment key={s}>
                    <div
                      className={`w-8 h-8 rounded-full flex items-center justify-center text-xs font-black transition-all ${
                        isCurrent
                          ? 'bg-brand-600 text-white shadow-md'
                          : isDone
                          ? 'bg-success-500 text-white'
                          : 'bg-slate-200 dark:bg-slate-700 text-slate-500 dark:text-slate-300'
                      }`}
                    >
                      {isDone && !isCurrent ? <CheckCircle2 className="w-4 h-4" /> : stepIndex + 1}
                    </div>
                    {idx < 3 && (
                      <div className={`h-0.5 w-10 rounded ${isDone ? 'bg-success-500' : 'bg-slate-200 dark:bg-slate-700'}`} />
                    )}
                  </React.Fragment>
                );
              })}
            </div>
          )}

          {error && (
            <div className="p-4 rounded-lg bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800 flex items-start space-x-3">
              <AlertCircle className="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" />
              <p className="text-sm text-red-700 dark:text-red-300">{error}</p>
            </div>
          )}

          {/* Step 1: PINFL */}
          {step === 'pinfl' && (
            <form onSubmit={handleCheckPinfl} className="space-y-5">
              <div>
                <label className="block text-sm font-bold text-gray-700 dark:text-gray-200 mb-2">
                  {t('adAccount.pinflLabel')}
                </label>
                <div className="flex items-center space-x-3 p-3 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 focus-within:ring-2 focus-within:ring-brand-500 transition-all">
                  <Fingerprint className="w-5 h-5 text-brand-500 flex-shrink-0" />
                  <input
                    type="text"
                    inputMode="numeric"
                    maxLength={14}
                    value={pinfl}
                    onChange={(e) => setPinfl(e.target.value.replace(/\D/g, ''))}
                    placeholder={t('adAccount.pinflPlaceholder')}
                    className="w-full bg-transparent text-sm font-black tracking-[0.2em] text-gray-900 dark:text-gray-100 placeholder:text-gray-400 focus:outline-none"
                  />
                </div>
                <p className="mt-2 text-[11px] text-gray-400">
                  {t('adAccount.pinflHint')}
                </p>
              </div>

              <button
                type="submit"
                disabled={isChecking || pinfl.length !== 14}
                className="w-full py-3 rounded-xl bg-brand-600 hover:bg-brand-700 active:bg-brand-800 text-white font-bold text-sm shadow-md transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center space-x-2"
              >
                {isChecking ? <Loader2 className="w-4 h-4 animate-spin" /> : <UserCheck className="w-4 h-4" />}
                <span>{isChecking ? t('common.checking') : t('adAccount.check')}</span>
              </button>
            </form>
          )}

          {/* Step 2: BXM kodi */}
          {step === 'bxm' && (
            <form onSubmit={handleCheckBxm} className="space-y-5">
              <div>
                <label className="block text-sm font-bold text-gray-700 dark:text-gray-200 mb-2">
                  {t('adAccount.bxmLabel')}
                </label>
                <div className="flex items-center space-x-3 p-3 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 focus-within:ring-2 focus-within:ring-brand-500 transition-all">
                  <Hash className="w-5 h-5 text-brand-500 flex-shrink-0" />
                  <input
                    type="text"
                    inputMode="numeric"
                    maxLength={10}
                    value={bxmCode}
                    onChange={(e) => setBxmCode(e.target.value.replace(/\D/g, ''))}
                    placeholder={t('adAccount.bxmPlaceholder')}
                    className="w-full bg-transparent text-sm font-black tracking-[0.2em] text-gray-900 dark:text-gray-100 placeholder:text-gray-400 focus:outline-none"
                  />
                </div>
                <p className="mt-2 text-[11px] text-gray-400">
                  {t('adAccount.bxmHint')}
                </p>
              </div>

              <button
                type="submit"
                disabled={isChecking || bxmCode.length < 3}
                className="w-full py-3 rounded-xl bg-brand-600 hover:bg-brand-700 active:bg-brand-800 text-white font-bold text-sm shadow-md transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center space-x-2"
              >
                {isChecking ? <Loader2 className="w-4 h-4 animate-spin" /> : <CheckCircle2 className="w-4 h-4" />}
                <span>{isChecking ? t('common.checking') : t('adAccount.confirm')}</span>
              </button>
            </form>
          )}

          {/* Step 3: Telefon raqam */}
          {step === 'phone' && (
            <form onSubmit={handleSendCode} className="space-y-5">
              <div>
                <label className="block text-sm font-bold text-gray-700 dark:text-gray-200 mb-2">
                  {t('adAccount.phoneLabel')}
                </label>
                <div className="flex items-center p-3 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 focus-within:ring-2 focus-within:ring-brand-500 transition-all">
                  <Phone className="w-5 h-5 text-brand-500 flex-shrink-0 mr-3" />
                  <input
                    type="tel"
                    value={formatPhoneDisplay(`998${phone}`)}
                    onChange={handlePhoneChange}
                    onKeyDown={handlePhoneKeyDown}
                    onFocus={(e) => e.currentTarget.setSelectionRange(e.currentTarget.value.length, e.currentTarget.value.length)}
                    placeholder="998 90 000 00 00"
                    className="w-full bg-transparent text-sm font-semibold text-gray-900 dark:text-gray-100 placeholder:text-gray-400 focus:outline-none"
                  />
                </div>
                <p className="mt-2 text-[11px] text-gray-400">
                  {t('adAccount.phoneHint')}
                </p>
              </div>

              <button
                type="submit"
                disabled={isSending || phone.trim().length < 9 || waitingResend}
                className="w-full py-3 rounded-xl bg-brand-600 hover:bg-brand-700 active:bg-brand-800 text-white font-bold text-sm shadow-md transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center space-x-2"
              >
                {isSending ? <Loader2 className="w-4 h-4 animate-spin" /> : <Smartphone className="w-4 h-4" />}
                <span>
                  {waitingResend
                    ? t('adAccount.resendCountdown', { time: formatCountdown(secondsLeft) })
                    : isSending
                    ? t('common.sending')
                    : t('adAccount.confirm')}
                </span>
              </button>
            </form>
          )}

          {/* Step 4: SMS code */}
          {step === 'code' && (
            <form onSubmit={handleVerifyCode} className="space-y-5">
              <div>
                <label className="block text-sm font-bold text-gray-700 dark:text-gray-200 mb-2">
                  {t('adAccount.codeLabel')}
                </label>
                <div className="flex items-center space-x-3 p-3 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 focus-within:ring-2 focus-within:ring-brand-500 transition-all">
                  <KeyRound className="w-5 h-5 text-brand-500 flex-shrink-0" />
                  <input
                    type="text"
                    inputMode="numeric"
                    maxLength={5}
                    value={code}
                    onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))}
                    placeholder="•••••"
                    className="w-full bg-transparent text-sm font-black tracking-[0.5em] text-gray-900 dark:text-gray-100 placeholder:text-gray-400 focus:outline-none"
                  />
                </div>
                <p className="mt-2 text-[11px] text-gray-400 flex items-center space-x-1">
                  <MessageSquareText className="w-3 h-3" />
                  <span>{t('adAccount.codeSentTo', { phone })}</span>
                </p>
              </div>

              <button
                type="submit"
                disabled={isVerifying || code.length !== 5}
                className="w-full py-3 rounded-xl bg-brand-600 hover:bg-brand-700 active:bg-brand-800 text-white font-bold text-sm shadow-md transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center space-x-2"
              >
                {isVerifying ? <Loader2 className="w-4 h-4 animate-spin" /> : <CheckCircle2 className="w-4 h-4" />}
                <span>{t('adAccount.confirm')}</span>
              </button>

              {/* SMS kelmagan bo'lsa qayta yuborish */}
              <div className="text-center pt-1">
                <button
                  type="button"
                  onClick={() => void requestCode()}
                  disabled={isSending || waitingResend}
                  className="inline-flex items-center space-x-1.5 text-xs font-bold text-brand-600 hover:text-brand-700 dark:text-brand-400 dark:hover:text-brand-300 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <MessageSquareText className="w-3.5 h-3.5" />
                  <span>
                    {isSending
                      ? t('common.sending')
                      : waitingResend
                      ? t('adAccount.smsNotArrivedCountdown', { time: formatCountdown(secondsLeft) })
                      : t('adAccount.smsNotArrived')}
                  </span>
                </button>
              </div>
            </form>
          )}

          {/* Step 5: Qaror — telefon tasdiqlangach */}
          {step === 'decision' && (
            <div className="space-y-5 py-2">
              <div className="text-center">
                <div
                  className={`w-16 h-16 rounded-full mx-auto flex items-center justify-center border ${
                    hasExchangeAccount
                      ? isRotated
                        ? 'bg-amber-50 dark:bg-amber-700/20 text-amber-500 border-amber-500/30'
                        : 'bg-success-50 dark:bg-success-700/20 text-success-500 border-success-500/30'
                      : 'bg-brand-50 dark:bg-brand-700/20 text-brand-500 border-brand-500/30'
                  }`}
                >
                  {hasExchangeAccount ? (
                    isRotated ? <Link2 className="w-8 h-8" /> : <MailCheck className="w-8 h-8" />
                  ) : (
                    <UserCheck className="w-8 h-8" />
                  )}
                </div>
                <h2 className="mt-4 text-lg font-extrabold text-gray-900 dark:text-gray-100">
                  {hasExchangeAccount
                    ? isRotated
                      ? t('adAccount.decisionRotatedTitle')
                      : t('adAccount.decisionMailExistsTitle')
                    : t('adAccount.decisionCreateTitle')}
                </h2>
                <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                  {hasExchangeAccount
                    ? isRotated
                      ? t('adAccount.decisionRotatedSubtitle', { bxm: confirmedBxm })
                      : t('adAccount.decisionMailExistsSubtitle')
                    : t('adAccount.decisionCreateSubtitle')}
                </p>
              </div>

              {hasExchangeAccount ? (
                isRotated ? (
                  <>
                    <div className="p-4 rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/40 space-y-2">
                      <div className="flex items-start space-x-2">
                        <Info className="w-4 h-4 text-amber-500 mt-0.5 flex-shrink-0" />
                        <p className="text-sm text-amber-700 dark:text-amber-300">{t('adAccount.decisionRotatedInfo')}</p>
                      </div>
                    </div>
                    <button
                      type="button"
                      onClick={() => setStep('linking')}
                      className="w-full py-3 rounded-xl bg-brand-600 hover:bg-brand-700 active:bg-brand-800 text-white font-bold text-sm shadow-md transition-all flex items-center justify-center space-x-2"
                    >
                      <Link2 className="w-4 h-4" />
                      <span>{t('adAccount.linkToBxm')}</span>
                    </button>
                  </>
                ) : (
                  <>
                    <div className="p-4 rounded-xl border border-success-200 dark:border-success-800 bg-success-50 dark:bg-success-950/40 space-y-2">
                      <div className="flex items-start space-x-2">
                        <MailCheck className="w-4 h-4 text-success-500 mt-0.5 flex-shrink-0" />
                        <p className="text-sm text-success-700 dark:text-success-300">{t('adAccount.decisionMailExistsInfo')}</p>
                      </div>
                    </div>
                    <button
                      type="button"
                      onClick={() => setStep('resetting')}
                      className="w-full py-3 rounded-xl bg-brand-600 hover:bg-brand-700 active:bg-brand-800 text-white font-bold text-sm shadow-md transition-all flex items-center justify-center space-x-2"
                    >
                      <RefreshCw className="w-4 h-4" />
                      <span>{t('adAccount.resetPassword')}</span>
                    </button>
                  </>
                )
              ) : (
                <button
                  type="button"
                  onClick={() => setStep('creating')}
                  className="w-full py-3 rounded-xl bg-brand-600 hover:bg-brand-700 active:bg-brand-800 text-white font-bold text-sm shadow-md transition-all flex items-center justify-center space-x-2"
                >
                  <CheckCircle2 className="w-4 h-4" />
                  <span>{t('adAccount.createNow')}</span>
                </button>
              )}
            </div>
          )}

          {/* Step 6: Pochta yaratish jarayoni */}
          {step === 'creating' && (
            <CreatingProgress
              pinfl={pinfl.replace(/\D/g, '')}
              phone={phone}
              bxmCode={confirmedBxm || bxmCode.replace(/\D/g, '')}
              onCreated={handleAccountCreated}
              onError={handleCreationError}
            />
          )}

          {/* Step 7: Pochtani boshqa BXM ga biriktirish (rotatsiya) */}
          {step === 'linking' && (
            <LinkingProgress
              pinfl={pinfl.replace(/\D/g, '')}
              phone={phone}
              bxmCode={confirmedBxm || bxmCode.replace(/\D/g, '')}
              onLinked={handleLinked}
              onError={handleCreationError}
            />
          )}

          {/* Step 7.5: Biriktirildi — parol almashtirish taklifi */}
          {step === 'linked' && (
            <div className="space-y-5 py-2">
              <div className="text-center">
                <div className="w-16 h-16 rounded-full mx-auto flex items-center justify-center border border-success-500/30 bg-success-50 dark:bg-success-700/20 text-success-500">
                  <Link2 className="w-8 h-8" />
                </div>
                <h2 className="mt-4 text-lg font-extrabold text-gray-900 dark:text-gray-100">{t('adAccount.linkedTitle')}</h2>
                <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">{t('adAccount.linkedSubtitle')}</p>
              </div>
              <button
                type="button"
                onClick={() => setStep('resetting')}
                className="w-full py-3 rounded-xl bg-brand-600 hover:bg-brand-700 active:bg-brand-800 text-white font-bold text-sm shadow-md transition-all flex items-center justify-center space-x-2"
              >
                <RefreshCw className="w-4 h-4" />
                <span>{t('adAccount.resetPassword')}</span>
              </button>
            </div>
          )}

          {/* Step 8: Parolni almashtirish (pochta allaqachon yaratilgan) */}
          {step === 'resetting' && (
            <ResetProgress
              pinfl={pinfl.replace(/\D/g, '')}
              phone={phone}
              onReset={handleReset}
              onError={handleCreationError}
            />
          )}

          {/* Step 9: Done */}
          {step === 'done' && account && (
            <div className="space-y-5 py-2">
              <div className="text-center">
                <div className="w-16 h-16 rounded-full bg-success-50 dark:bg-success-700/20 text-success-500 mx-auto flex items-center justify-center border border-success-500/30">
                  <CheckCircle2 className="w-8 h-8" />
                </div>
                <h2 className="mt-4 text-lg font-extrabold text-gray-900 dark:text-gray-100">
                  {isResetDone ? t('adAccount.resetDoneTitle') : t('adAccount.doneTitle')}
                </h2>
                <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                  {isResetDone ? t('adAccount.resetDoneSubtitle') : t('adAccount.doneSubtitle')}
                </p>
              </div>

              <div className="space-y-3">
                <div className="rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 p-4">
                  <p className="text-xs font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">{t('adAccount.doneLoginLabel')}</p>
                  <p className="mt-1 text-base font-black text-gray-900 dark:text-gray-100 break-all">{account.email}</p>
                </div>
                <div className="rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/40 p-4">
                  <p className="text-xs font-bold uppercase tracking-wide text-amber-600 dark:text-amber-400">{t('adAccount.donePasswordLabel')}</p>
                  <p className="mt-1 text-base font-black text-gray-900 dark:text-gray-100 break-all">{account.password}</p>
                </div>
              </div>

              <Link
                to="/login"
                className="block w-full py-3 rounded-xl bg-brand-600 hover:bg-brand-700 text-white font-bold text-sm text-center shadow-md transition-all"
              >
                {t('adAccount.goToLogin')}
              </Link>
            </div>
          )}

        </div>

        {/* Back to login */}
        <div className="mt-6 text-center">
          <Link
            to="/login"
            className="inline-flex items-center space-x-1.5 text-xs font-bold text-slate-500 hover:text-brand-600 dark:text-slate-400 dark:hover:text-brand-400 transition-colors"
          >
            <ArrowLeft className="w-3.5 h-3.5" />
            <span>{t('adAccount.backToLogin')}</span>
          </Link>
        </div>
      </div>
    </div>
  );
};