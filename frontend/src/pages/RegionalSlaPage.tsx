import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { AlertTriangle, ArrowLeft, Clock, RefreshCw } from 'lucide-react';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { useCan } from '@/shared/presentation/hooks/useCan';
import { useToastStore } from '@/shared/presentation/store/useToastStore';
import { useT } from '@/shared/presentation/i18n/i18n';
import { EmptyState } from '@/shared/presentation/components/EmptyState';

interface Region {
  id: number;
  name: string;
}

interface Team {
  id: number;
  name: string;
  region_id: number | null;
  republic_only: boolean;
  is_active: boolean;
}

interface Rule {
  id: number;
  team_id: number;
  /** null — respublika qoidasi (zaxira). */
  region_id: number | null;
  is_default: boolean;
  accept_minutes: number;
  work_minutes: number;
}

/** Bitta katakdagi tahrirlanayotgan qiymatlar. */
type Draft = Record<number, { accept: number; work: number }>;

/**
 * Viloyat SLA qoidalari.
 *
 * Guruh XIZMATNI bildiradi (Texnik guruh, NOC), viloyat esa MUDDATNI. Shu
 * sababli jadval hududlar bo'yicha yoziladi: har qatorda xizmat guruhi va
 * o'sha viloyatdagi ikkita muddati turadi.
 *
 * Respublika qoidalari bu yerda emas — ular "SLA va kategoriyalar"
 * sahifasida, chunki u yerda shablonlar va muhimlik bo'yicha qoidalar ham bor.
 */
export const RegionalSlaPage: React.FC = () => {
  const t = useT();
  const toast = useToastStore();
  const { user } = useCan();
  const superadmin = Boolean(user?.isSuperAdmin);

  const [regions, setRegions] = useState<Region[]>([]);
  const [teams, setTeams] = useState<Team[]>([]);
  const [rules, setRules] = useState<Rule[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState(false);
  const [draft, setDraft] = useState<Draft>({});
  const [saving, setSaving] = useState<number | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setLoadError(false);
    try {
      const [regionRes, teamRes, ruleRes] = await Promise.all([
        axiosClient.get<{ data: Region[] }>('/regions', { params: { per_page: 100, is_active: 1 } }),
        axiosClient.get<{ data: Team[] }>('/teams', { params: { per_page: 100, is_active: 1 } }),
        axiosClient.get<{ data: Rule[] }>('/sla-rules', { params: { per_page: 200 } }),
      ]);
      setRegions(regionRes.data?.data ?? []);
      setTeams(teamRes.data?.data ?? []);
      setRules(ruleRes.data?.data ?? []);
    } catch {
      setLoadError(true);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  /** Viloyat muddati beriladigan xizmat guruhlari: BI chetda (u respublikaniki). */
  const serviceTeams = useMemo(
    () => teams.filter((team) => team.is_active && team.region_id === null && !team.republic_only),
    [teams],
  );

  /** (guruh, hudud) -> qoida. Faqat "Default holat" qoidalari ko'rsatiladi. */
  const byCell = useMemo(() => {
    const map = new Map<string, Rule>();
    rules.filter((rule) => rule.is_default).forEach((rule) => {
      map.set(`${rule.team_id}:${rule.region_id ?? 0}`, rule);
    });

    return map;
  }, [rules]);

  const save = async (rule: Rule) => {
    const next = draft[rule.id] ?? { accept: rule.accept_minutes, work: rule.work_minutes };
    setSaving(rule.id);
    try {
      await axiosClient.put(`/sla-rules/${rule.id}`, { accept_minutes: next.accept, work_minutes: next.work });
      setRules((current) => current.map((item) => item.id === rule.id
        ? { ...item, accept_minutes: next.accept, work_minutes: next.work }
        : item));
      toast.success(t('regionalSla.saved'));
    } catch (error: any) {
      toast.error(error?.response?.data?.message || t('regionalSla.saveFailed'));
    } finally {
      setSaving(null);
    }
  };

  const input = 'w-24 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/60 px-2.5 py-2 text-sm font-semibold text-slate-900 dark:text-slate-100 outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 disabled:opacity-60';

  return (
    <div className="p-4 sm:p-6 lg:p-8 space-y-5">
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <Link to="/sla-policies" className="inline-flex items-center gap-1.5 text-xs font-bold text-slate-400 hover:text-brand-500">
            <ArrowLeft className="w-3.5 h-3.5" /> {t('nav.sla')}
          </Link>
          <h1 className="mt-1 flex items-center gap-2 text-xl sm:text-2xl font-black text-slate-900 dark:text-slate-100">
            <Clock className="w-5 h-5 text-brand-500" /> {t('regionalSla.title')}
          </h1>
          <p className="mt-1 text-xs sm:text-sm font-semibold text-slate-500 dark:text-slate-400">
            {t('regionalSla.subtitle')}
          </p>
        </div>
        <button
          type="button"
          onClick={() => void load()}
          disabled={loading}
          className="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-700 px-4 py-2 text-xs font-bold text-slate-600 dark:text-slate-200"
        >
          <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} /> {t('slaPolicies.refresh')}
        </button>
      </header>

      <div className="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-xs sm:text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
        <AlertTriangle className="w-5 h-5 shrink-0 mt-0.5" />
        <div>{t('regionalSla.hint')}</div>
      </div>

      {loadError && (
        <div className="flex items-center gap-3 rounded-2xl bg-rose-50 p-4 text-rose-600 dark:bg-rose-950/30">
          <AlertTriangle className="w-5 h-5" />
          {t('regionalSla.loadFailed')}
          <button type="button" className="ml-auto underline" onClick={() => void load()}>{t('slaPolicies.retry')}</button>
        </div>
      )}

      {loading && (
        <div className="flex min-h-52 items-center justify-center">
          <RefreshCw className="w-7 h-7 animate-spin text-brand-500" />
        </div>
      )}

      {!loading && !loadError && (serviceTeams.length === 0 || regions.length === 0) && (
        <EmptyState title={t('regionalSla.empty')} />
      )}

      {!loading && !loadError && serviceTeams.length > 0 && regions.map((region) => (
        <section key={region.id} className="rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900/40">
          <h2 className="border-b border-slate-100 px-5 py-3 text-sm font-extrabold text-slate-800 dark:border-slate-800 dark:text-slate-100">
            {region.name}
          </h2>
          <div className="overflow-x-auto">
            <table className="w-full min-w-[560px] text-left text-sm">
              <thead>
                <tr className="text-xs font-bold uppercase tracking-wide text-slate-400">
                  <th className="px-5 py-2">{t('regionalSla.colTeam')}</th>
                  <th className="py-2">{t('regionalSla.colAccept')}</th>
                  <th className="py-2">{t('regionalSla.colWork')}</th>
                  <th className="py-2" />
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                {serviceTeams.map((team) => {
                  const rule = byCell.get(`${team.id}:${region.id}`);
                  const values = rule
                    ? draft[rule.id] ?? { accept: rule.accept_minutes, work: rule.work_minutes }
                    : null;

                  return (
                    <tr key={team.id} className="text-slate-700 dark:text-slate-200">
                      <td className="px-5 py-2.5 font-semibold">{team.name}</td>
                      {rule && values ? (
                        <>
                          <td className="py-2.5">
                            <input
                              type="number"
                              min={1}
                              max={100000}
                              disabled={!superadmin}
                              className={input}
                              value={values.accept}
                              onChange={(e) => setDraft((c) => ({ ...c, [rule.id]: { ...values, accept: Number(e.target.value) } }))}
                            />
                          </td>
                          <td className="py-2.5">
                            <input
                              type="number"
                              min={1}
                              max={100000}
                              disabled={!superadmin}
                              className={input}
                              value={values.work}
                              onChange={(e) => setDraft((c) => ({ ...c, [rule.id]: { ...values, work: Number(e.target.value) } }))}
                            />
                          </td>
                          <td className="py-2.5 pr-5">
                            {superadmin && (
                              <button
                                type="button"
                                disabled={saving === rule.id}
                                onClick={() => void save(rule)}
                                className="rounded-xl bg-brand-600 px-4 py-2 text-xs font-bold text-white hover:bg-brand-500 disabled:opacity-50"
                              >
                                {t(saving === rule.id ? 'slaPolicies.saving' : 'slaPolicies.save')}
                              </button>
                            )}
                          </td>
                        </>
                      ) : (
                        // Viloyatda o'z qoidasi yo'q — zayavka respublika
                        // muddatiga tushadi.
                        <td className="py-2.5 text-xs font-semibold text-slate-400" colSpan={3}>
                          {t('regionalSla.republicFallback')}
                        </td>
                      )}
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </section>
      ))}
    </div>
  );
};
