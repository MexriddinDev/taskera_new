import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { CheckSquare, ArrowLeft, Phone, MessageSquareText, Loader2, AlertCircle, CheckCircle2, Smartphone, KeyRound, Fingerprint, Hash } from 'lucide-react';

type Step = 'phone' | 'code' | 'details' | 'creating' | 'done';

const getErrorMessage = (e: unknown): string => {
  const anyErr = e as { response?: { data?: { message?: string } }; message?: string };
  return anyErr?.response?.data?.message || anyErr?.message || 'Xatolik yuz berdi. Qayta urinib ko\'ring.';
};

// Pochta (AD) yaratilish jarayoni progressi
const CREATION_STAGES = [
  { label: 'Ma\'lumotlar tekshirilmoqda', delay: 1200 },
  { label: 'Pochta manzili shakllantirilmoqda', delay: 1500 },
  { label: 'Active Directory hisobi yaratilmoqda', delay: 2000 },
  { label: 'Pochta va parol tayyorlanmoqda', delay: 1500 },
];

const CreatingProgress: React.FC<{ email: string; onDone: () => void }> = ({ email, onDone }) => {
  const [stage, setStage] = useState(0);

  useEffect(() => {
    if (stage >= CREATION_STAGES.length) {
      const t = window.setTimeout(onDone, 900);
      return () => window.clearTimeout(t);
    }
    const t = window.setTimeout(() => setStage(stage + 1), CREATION_STAGES[stage].delay);
    return () => window.clearTimeout(t);
  }, [stage, onDone]);

  const finished = stage >= CREATION_STAGES.length;

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
          {finished ? 'Pochta yaratildi!' : 'Pochta (AD) yaratilmoqda...'}
        </h2>
        {email && (
          <p className="mt-1 inline-block px-4 py-1.5 rounded-lg bg-gray-100 dark:bg-gray-700/60 text-sm font-black tracking-wide text-brand-700 dark:text-brand-300">
            {email}
          </p>
        )}
      </div>

      <div className="space-y-3 pt-2">
        {CREATION_STAGES.map((s, i) => {
          const isDone = i < stage;
          const isActive = i === stage && !finished;
          return (
            <div key={s.label} className="flex items-center space-x-3">
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
                {s.label}
              </span>
            </div>
          );
        })}
      </div>
    </div>
  );
};

export const AdAccountCreatePage: React.FC = () => {
  const [step, setStep] = useState<Step>('phone');
  const [phone, setPhone] = useState('');
  const [code, setCode] = useState('');
  const [pinfl, setPinfl] = useState('');
  const [bxmCode, setBxmCode] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [isSending, setIsSending] = useState(false);
  const [isVerifying, setIsVerifying] = useState(false);
  const [isChecking, setIsChecking] = useState(false);
  // Tekshiruv natijasi — yaratiladigan pochta va xodim ma'lumotlari
  const [accountInfo, setAccountInfo] = useState<{ email: string; employee?: Record<string, unknown> } | null>(null);
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

  // SMS yuborish / qayta yuborish so'rovi
  const requestCode = async () => {
    setError(null);
    setIsSending(true);
    try {
      const digits = phone.replace(/\D/g, '');
      const normalized = digits.length === 9 ? `+998${digits}` : `+${digits}`;
      const res = await axiosClient.post('/ad-account/send-code', { phone: normalized });
      if (res.data?.already_sent) {
        const after = res.data?.resend_after;
        setResendAt(after ? Number(after) * 1000 : null);
        setError('SMS allaqachon yuborilgan. Qayta yuborish tugmasi ochilishini kuting.');
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

  const handleVerifyCode = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    setIsVerifying(true);
    try {
      const digits = phone.replace(/\D/g, '');
      const normalized = digits.length === 9 ? `+998${digits}` : `+${digits}`;
      await axiosClient.post('/ad-account/verify-code', { phone: normalized, code });
      setStep('details');
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setIsVerifying(false);
    }
  };

  const handleSubmitDetails = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(null);
    if (pinfl.replace(/\D/g, '').length !== 14) {
      setError('PINFL (JShShIR) 14 ta raqamdan iborat bo\'lishi kerak.');
      return;
    }
    if (bxmCode.trim().length < 3) {
      setError('BXM kodini to\'g\'ri kiriting.');
      return;
    }
    setIsChecking(true);
    try {
      const digits = phone.replace(/\D/g, '');
      const normalized = digits.length === 9 ? `+998${digits}` : `+${digits}`;
      const res = await axiosClient.post('/ad-account/check-employee', {
        phone: normalized,
        pinfl: pinfl.replace(/\D/g, ''),
        bxm_code: bxmCode.replace(/\D/g, ''),
      });
      setAccountInfo({ email: res.data?.email ?? '', employee: res.data?.employee });
      setStep('creating');
    } catch (err) {
      setError(getErrorMessage(err));
    } finally {
      setIsChecking(false);
    }
  };

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

        <div className="w-full p-8 bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-100 dark:border-gray-700 transition-all space-y-6">
          <div className="text-center">
            <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Pochta (AD) yaratish</h1>
            <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
              Yangi ishga keldingizmi? Telefon raqamingizni tasdiqlash orqali pochta (AD) hisobingizni yarating.
            </p>
          </div>

          {/* Step indicator */}
          <div className="flex items-center justify-center space-x-2">
            {(['phone', 'code', 'details'] as const).map((s, idx) => {
              const stepIndex = ['phone', 'code', 'details'].indexOf(s);
              const finished = step === 'creating' || step === 'done';
              const currentIndex = ['phone', 'code', 'details'].indexOf(step as 'phone' | 'code' | 'details');
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
                  {idx < 2 && (
                    <div className={`h-0.5 w-10 rounded ${isDone ? 'bg-success-500' : 'bg-slate-200 dark:bg-slate-700'}`} />
                  )}
                </React.Fragment>
              );
            })}
          </div>

          {error && (
            <div className="p-4 rounded-lg bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-800 flex items-start space-x-3">
              <AlertCircle className="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" />
              <p className="text-sm text-red-700 dark:text-red-300">{error}</p>
            </div>
          )}

          {/* Step 1: Phone */}
          {step === 'phone' && (
            <form onSubmit={handleSendCode} className="space-y-5">
              <div>
                <label className="block text-sm font-bold text-gray-700 dark:text-gray-200 mb-2">
                  Telefon raqamingizni kiriting
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
                  Ushbu raqamga SMS orqali tasdiqlash kodi yuboriladi.
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
                    ? `Qayta yuborish (${formatCountdown(secondsLeft)})`
                    : isSending
                    ? 'Yuborilmoqda...'
                    : 'Tasdiqlash'}
                </span>
              </button>
            </form>
          )}

          {/* Step 2: SMS code */}
          {step === 'code' && (
            <form onSubmit={handleVerifyCode} className="space-y-5">
              <div>
                <label className="block text-sm font-bold text-gray-700 dark:text-gray-200 mb-2">
                  SMS orqali kelgan kodni kiriting
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
                  <span>Kod {phone} raqamiga yuborildi</span>
                </p>
              </div>

              <button
                type="submit"
                disabled={isVerifying || code.length !== 5}
                className="w-full py-3 rounded-xl bg-brand-600 hover:bg-brand-700 active:bg-brand-800 text-white font-bold text-sm shadow-md transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center space-x-2"
              >
                {isVerifying ? <Loader2 className="w-4 h-4 animate-spin" /> : <CheckCircle2 className="w-4 h-4" />}
                <span>Tasdiqlash</span>
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
                      ? 'Yuborilmoqda...'
                      : waitingResend
                      ? `SMS kelmadi? Qayta yuborish (${formatCountdown(secondsLeft)})`
                      : 'SMS kelmadi? Qayta yuborish'}
                  </span>
                </button>
              </div>
            </form>
          )}

          {/* Step 3: PINFL va BXM kodi */}
          {step === 'details' && (
            <form onSubmit={handleSubmitDetails} className="space-y-5">
              <div>
                <label className="block text-sm font-bold text-gray-700 dark:text-gray-200 mb-2">
                  PINFL (JShShIR) kiriting
                </label>
                <div className="flex items-center space-x-3 p-3 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 focus-within:ring-2 focus-within:ring-brand-500 transition-all">
                  <Fingerprint className="w-5 h-5 text-brand-500 flex-shrink-0" />
                  <input
                    type="text"
                    inputMode="numeric"
                    maxLength={14}
                    value={pinfl}
                    onChange={(e) => setPinfl(e.target.value.replace(/\D/g, ''))}
                    placeholder="14 xonali JShShIR"
                    className="w-full bg-transparent text-sm font-black tracking-[0.2em] text-gray-900 dark:text-gray-100 placeholder:text-gray-400 focus:outline-none"
                  />
                </div>
                <p className="mt-2 text-[11px] text-gray-400">
                  Shaxsiy guvohnoma (ID karta) orqasidagi 14 xonali PINFL raqamingiz.
                </p>
              </div>

              <div>
                <label className="block text-sm font-bold text-gray-700 dark:text-gray-200 mb-2">
                  BXM kodini kiriting
                </label>
                <div className="flex items-center space-x-3 p-3 rounded-xl border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 focus-within:ring-2 focus-within:ring-brand-500 transition-all">
                  <Hash className="w-5 h-5 text-brand-500 flex-shrink-0" />
                  <input
                    type="text"
                    inputMode="numeric"
                    maxLength={10}
                    value={bxmCode}
                    onChange={(e) => setBxmCode(e.target.value.replace(/\D/g, ''))}
                    placeholder="BXM kodi"
                    className="w-full bg-transparent text-sm font-black tracking-[0.2em] text-gray-900 dark:text-gray-100 placeholder:text-gray-400 focus:outline-none"
                  />
                </div>
                <p className="mt-2 text-[11px] text-gray-400">
                  Ish joyingiz bo'yicha BXM (bank / tashkilot) kodi.
                </p>
              </div>

              <button
                type="submit"
                disabled={isChecking || pinfl.length !== 14 || bxmCode.length < 3}
                className="w-full py-3 rounded-xl bg-brand-600 hover:bg-brand-700 active:bg-brand-800 text-white font-bold text-sm shadow-md transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center space-x-2"
              >
                {isChecking ? <Loader2 className="w-4 h-4 animate-spin" /> : <CheckCircle2 className="w-4 h-4" />}
                <span>{isChecking ? 'Tekshirilmoqda...' : 'Tasdiqlash'}</span>
              </button>
            </form>
          )}

          {/* Step 4: Pochta yaratish jarayoni */}
          {step === 'creating' && (
            <CreatingProgress email={accountInfo?.email ?? ''} onDone={() => setStep('done')} />
          )}

          {/* Step 5: Done */}
          {step === 'done' && (
            <div className="text-center space-y-4 py-4">
              <div className="w-16 h-16 rounded-full bg-success-50 dark:bg-success-700/20 text-success-500 mx-auto flex items-center justify-center border border-success-500/30">
                <CheckCircle2 className="w-8 h-8" />
              </div>
              <div>
                <h2 className="text-lg font-extrabold text-gray-900 dark:text-gray-100">
                  Ma'lumotlaringiz qabul qilindi!
                </h2>
                {accountInfo?.email && (
                  <p className="mt-3 inline-block px-5 py-2 rounded-xl bg-brand-50 dark:bg-brand-700/20 border border-brand-500/30 text-base font-black tracking-wide text-brand-700 dark:text-brand-300">
                    {accountInfo.email}
                  </p>
                )}
                <p className="mt-3 text-sm text-gray-500 dark:text-gray-400">
                  Telefon raqamingiz va ma'lumotlaringiz tasdiqlandi. Parol va batafsil ko'rsatmalar SMS orqali
                  yuboriladi.
                </p>
              </div>
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
            <span>Loginga qaytish</span>
          </Link>
        </div>
      </div>
    </div>
  );
};
