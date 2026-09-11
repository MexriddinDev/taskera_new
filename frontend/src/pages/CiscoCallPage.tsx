import React, { useCallback, useEffect, useState } from 'react';
import { Phone, PhoneOff, RefreshCw, ShieldCheck, Trash2, WifiOff } from 'lucide-react';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { useToastStore } from '@/shared/presentation/store/useToastStore';
import { useT } from '@/shared/presentation/i18n/i18n';

/** Finesse hisobi va uning jonli holati. Hisob saqlanmagan bo'lsa — null. */
interface FinesseAccount {
  login_id: string;
  extension: string | null;
  state: string | null;
  /** Finesse serveriga hozir ulanib bo'ldimi. */
  online: boolean;
  /** Holat READY — qo'ng'iroqqa tayyor. */
  ready: boolean;
  full_name: string | null;
  team_name: string | null;
  error: string | null;
  checked_at: string | null;
}

/** Holat sahifa ochiq turganda shu oraliqda yangilanadi. */
const POLL_MS = 30_000;

export const CiscoCallPage: React.FC = () => {
  const t = useT();
  const toast = useToastStore();

  const [account, setAccount] = useState<FinesseAccount | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [formError, setFormError] = useState('');
  const [loginId, setLoginId] = useState('');
  const [password, setPassword] = useState('');

  const load = useCallback(async (silent = false) => {
    if (!silent) setLoading(true);
    else setRefreshing(true);
    try {
      const res = await axiosClient.get<{ data: FinesseAccount | null }>('/finesse/account');
      setAccount(res.data?.data ?? null);
    } catch {
      setAccount(null);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  // Hisob saqlangandagina so'rov yuboriladi — bo'sh sahifa serverni bezovta qilmasin.
  useEffect(() => {
    if (!account) return;
    const id = setInterval(() => load(true), POLL_MS);
    return () => clearInterval(id);
  }, [account, load]);

  const save = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!loginId.trim() || !password) {
      setFormError(t('ciscoCall.requiredError'));
      return;
    }

    setSaving(true);
    setFormError('');
    try {
      const res = await axiosClient.post<{ data: FinesseAccount }>('/finesse/account', {
        login_id: loginId.trim(),
        password,
      });
      setAccount(res.data.data);
      setPassword('');
      toast.success(t('ciscoCall.saved'));
    } catch (error: any) {
      setFormError(error?.response?.data?.message || t('ciscoCall.saveError'));
    } finally {
      setSaving(false);
    }
  };

  const remove = async () => {
    try {
      await axiosClient.delete('/finesse/account');
      setAccount(null);
      setLoginId('');
      setPassword('');
      toast.success(t('ciscoCall.removed'));
    } catch (error: any) {
      toast.error(error?.response?.data?.message || t('ciscoCall.removeError'));
    }
  };

  const inputClass =
    'w-full px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/60 text-slate-900 dark:text-slate-100 text-sm font-semibold outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all';

  return (
    <div className="p-4 sm:p-6 lg:p-8 space-y-5 max-w-3xl">
      <header>
        <h1 className="text-xl sm:text-2xl font-black text-slate-900 dark:text-slate-100 flex items-center gap-2">
          <Phone className="w-5 h-5 text-brand-500" /> {t('ciscoCall.title')}
        </h1>
        <p className="text-xs sm:text-sm font-semibold text-slate-500 dark:text-slate-400 mt-1">
          {t('ciscoCall.subtitle')}
        </p>
      </header>

      {loading && (
        <div className="min-h-40 flex items-center justify-center">
          <RefreshCw className="w-7 h-7 animate-spin text-brand-500" />
        </div>
      )}

      {/* Saqlangan hisob — holat kartochkasi */}
      {!loading && account && (
        <div className="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/90 shadow-sm p-5 space-y-4">
          <div className="flex items-start justify-between gap-3">
            <div className="flex items-center gap-3">
              <span
                className={`w-11 h-11 rounded-2xl flex items-center justify-center flex-shrink-0 ${
                  account.ready
                    ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400'
                    : account.online
                      ? 'bg-amber-100 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400'
                      : 'bg-rose-100 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400'
                }`}
              >
                {account.online ? <Phone className="w-5 h-5" /> : <WifiOff className="w-5 h-5" />}
              </span>
              <div>
                <p className="font-black text-slate-900 dark:text-slate-100">
                  {account.full_name || account.login_id}
                </p>
                <p className="text-xs font-semibold text-slate-400 font-mono">{account.login_id}</p>
              </div>
            </div>

            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={() => load(true)}
                disabled={refreshing}
                className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-600 dark:text-slate-200"
              >
                <RefreshCw className={`w-3.5 h-3.5 ${refreshing ? 'animate-spin' : ''}`} />
                {t('ciscoCall.refresh')}
              </button>
              <button
                type="button"
                onClick={remove}
                title={t('ciscoCall.remove')}
                aria-label={t('ciscoCall.remove')}
                className="p-2 rounded-xl text-slate-400 hover:text-rose-600 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
              >
                <Trash2 className="w-4 h-4" />
              </button>
            </div>
          </div>

          <div className="grid sm:grid-cols-3 gap-3 text-xs">
            <div className="rounded-xl bg-slate-50 dark:bg-slate-900/50 p-3">
              <p className="font-black text-slate-400 uppercase tracking-wider mb-1">{t('ciscoCall.status')}</p>
              <p
                className={`font-black ${
                  account.ready
                    ? 'text-emerald-600 dark:text-emerald-400'
                    : account.online
                      ? 'text-amber-600 dark:text-amber-400'
                      : 'text-rose-500'
                }`}
              >
                {account.online ? (account.state ?? '—') : t('ciscoCall.offline')}
              </p>
            </div>
            <div className="rounded-xl bg-slate-50 dark:bg-slate-900/50 p-3">
              <p className="font-black text-slate-400 uppercase tracking-wider mb-1">{t('ciscoCall.extension')}</p>
              <p className="font-black font-mono text-slate-900 dark:text-slate-100">{account.extension || '—'}</p>
            </div>
            <div className="rounded-xl bg-slate-50 dark:bg-slate-900/50 p-3">
              <p className="font-black text-slate-400 uppercase tracking-wider mb-1">{t('ciscoCall.team')}</p>
              <p className="font-black text-slate-900 dark:text-slate-100">{account.team_name || '—'}</p>
            </div>
          </div>

          {account.error && (
            <p role="alert" className="text-xs font-bold text-rose-500 flex items-center gap-1.5">
              <PhoneOff className="w-3.5 h-3.5 flex-shrink-0" /> {account.error}
            </p>
          )}

          {account.online && !account.ready && (
            <p className="text-xs font-semibold text-amber-600 dark:text-amber-400">
              {t('ciscoCall.notReadyHint')}
            </p>
          )}
        </div>
      )}

      {/* Login/parol formasi — FAQAT hisob hali ulanmagan bo'lsa. Bir
          foydalanuvchida bitta Finesse hisobi bo'ladi; almashtirish kerak
          bo'lsa yuqoridagi savatcha bilan o'chirilib, qaytadan kiritiladi. */}
      {!loading && !account && (
        <form
          onSubmit={save}
          className="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800/90 shadow-sm p-5 space-y-4"
        >
          <h2 className="text-sm font-black text-slate-900 dark:text-slate-100">
            {t('ciscoCall.connectTitle')}
          </h2>

          <div className="grid sm:grid-cols-2 gap-3">
            <label className="block space-y-1.5">
              <span className="text-xs font-black text-slate-500">{t('ciscoCall.loginLabel')}</span>
              <input
                autoComplete="username"
                maxLength={128}
                className={inputClass}
                value={loginId}
                onChange={(event) => setLoginId(event.target.value)}
                placeholder="m.mirpulatov"
              />
            </label>
            <label className="block space-y-1.5">
              <span className="text-xs font-black text-slate-500">{t('ciscoCall.passwordLabel')}</span>
              <input
                type="password"
                autoComplete="current-password"
                maxLength={255}
                className={inputClass}
                value={password}
                onChange={(event) => setPassword(event.target.value)}
              />
            </label>
          </div>

          <p className="text-[11px] font-semibold text-slate-400 flex items-start gap-1.5">
            <ShieldCheck className="w-3.5 h-3.5 flex-shrink-0 mt-0.5 text-emerald-500" />
            {t('ciscoCall.securityNote')}
          </p>

          {formError && <p role="alert" className="text-xs font-bold text-rose-500">{formError}</p>}

          <button
            type="submit"
            disabled={saving}
            className="px-4 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-500 text-white text-xs font-bold disabled:opacity-50"
          >
            {saving ? t('ciscoCall.saving') : t('ciscoCall.save')}
          </button>
        </form>
      )}
    </div>
  );
};
