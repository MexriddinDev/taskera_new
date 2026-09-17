import React, { useCallback, useEffect, useState } from 'react';
import { Building2, RefreshCw } from 'lucide-react';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { useT } from '@/shared/presentation/i18n/i18n';

/**
 * Filiallar kesimida bajarilish monitoringi.
 *
 * Zayavka qaysi filialdan kelgani `tickets.bxm_code` da muhrlangan, filial esa
 * viloyatga bog'langan — shu sabab ro'yxat viloyat bo'yicha guruhlanadi.
 * Filiali aniqlanmagan zayavkalar ham ko'rsatiladi: ular yashirilsa umumiy son
 * mos kelmay qolardi.
 */
interface BranchStat {
  region_id: number | null;
  region_name: string;
  local_code: string | null;
  branch_code: string | null;
  branch_name: string;
  total: number;
  open: number;
  completed: number;
  breached: number;
  sla_percent: number;
}

const card = 'rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900';

interface BranchStatsResponse {
  data: BranchStat[];
  regions?: { id: number; name: string }[];
}

export const BranchMonitoringPage: React.FC = () => {
  const t = useT();
  const [rows, setRows] = useState<BranchStat[]>([]);
  const [regionsList, setRegionsList] = useState<{ id: string; name: string }[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setError('');
    try {
      const res = await axiosClient.get<BranchStatsResponse>('/regional-support/branch-stats');
      setRows(res.data.data);
      if (res.data.regions && res.data.regions.length > 0) {
        setRegionsList(res.data.regions.map((r) => ({ id: String(r.id), name: r.name })));
      }
    } catch (e) {
      setError(e instanceof Error ? e.message : t('regional.loadFailed'));
    } finally {
      setLoading(false);
    }
  }, [t]);

  useEffect(() => {
    void load();
  }, [load]);

  const [selectedRegionId, setSelectedRegionId] = useState<string>('');

  const regions = React.useMemo(() => {
    if (regionsList.length > 0) {
      return regionsList;
    }
    const map = new Map<string, string>();
    rows.forEach((r) => {
      if (r.region_id !== null && r.region_name) {
        map.set(String(r.region_id), r.region_name);
      }
    });
    return Array.from(map.entries()).map(([id, name]) => ({ id, name }));
  }, [regionsList, rows]);

  const filteredRows = React.useMemo(() => {
    if (!selectedRegionId) return rows;
    return rows.filter((r) => String(r.region_id) === selectedRegionId);
  }, [rows, selectedRegionId]);

  const summary = React.useMemo(() => {
    const totalBranches = filteredRows.length;
    const totalTickets = filteredRows.reduce((acc, r) => acc + r.total, 0);
    const openTickets = filteredRows.reduce((acc, r) => acc + r.open, 0);
    const completedTickets = filteredRows.reduce((acc, r) => acc + r.completed, 0);
    const breachedTickets = filteredRows.reduce((acc, r) => acc + r.breached, 0);
    const avgSla = totalTickets > 0
      ? Math.round(((totalTickets - breachedTickets) / totalTickets) * 100)
      : 100;

    return { totalBranches, totalTickets, openTickets, completedTickets, breachedTickets, avgSla };
  }, [filteredRows]);

  // Viloyat nomi faqat guruhning BIRINCHI qatorida chiziladi — takrorlangan
  // nom jadvalni o'qishni qiyinlashtiradi.
  const regionKey = (row: BranchStat) => `${row.region_id ?? 'none'}`;

  return (
    <main className="space-y-5 p-4 text-slate-900 dark:text-slate-100 md:p-6">
      <header className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="flex items-center gap-2 text-2xl font-bold">
            <Building2 className="h-6 w-6 text-brand-500" /> {t('regional.branchStatsTitle')}
          </h1>
          <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">{t('regional.branchStatsSubtitle')}</p>
        </div>
        <button
          type="button"
          disabled={loading}
          onClick={() => void load()}
          className="rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50"
        >
          <RefreshCw className={`mr-1 inline h-4 w-4 ${loading ? 'animate-spin' : ''}`} />
          {t('regional.refresh')}
        </button>
      </header>

      {/* Viloyat bo'yicha filter dropdown */}
      <div className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
        <label className="flex items-center gap-2 text-sm font-bold text-slate-700 dark:text-slate-300">
          <span>{t('regional.regionFilter')}:</span>
          <select
            value={selectedRegionId}
            onChange={(e) => setSelectedRegionId(e.target.value)}
            className="rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-bold text-slate-900 shadow-sm focus:border-brand-500 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
          >
            <option value="">{t('regional.allRegions')}</option>
            {regions.map((reg) => (
              <option key={reg.id} value={reg.id}>
                {reg.name}
              </option>
            ))}
          </select>
        </label>

        <div className="flex flex-wrap items-center gap-4 text-xs font-semibold text-slate-500 dark:text-slate-400">
          <span>Filiallar: <b className="text-slate-900 dark:text-slate-100">{summary.totalBranches}</b></span>
          <span>Jami: <b className="text-slate-900 dark:text-slate-100">{summary.totalTickets}</b></span>
          <span>Jarayonda: <b className="text-slate-900 dark:text-slate-100">{summary.openTickets}</b></span>
          <span>Bajarildi: <b className="text-emerald-600 dark:text-emerald-400">{summary.completedTickets}</b></span>
          <span>SLA: <b className="text-brand-600 dark:text-brand-400">{summary.avgSla}%</b></span>
        </div>
      </div>

      {error && <p className="rounded-xl bg-rose-50 p-3 text-sm font-semibold text-rose-600 dark:bg-rose-950/40">{error}</p>}

      <section className={card}>
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm">
            <thead>
              <tr className="border-b border-slate-200 text-xs uppercase tracking-wide text-slate-400 dark:border-slate-700">
                <th className="px-3 py-2">{t('regional.colRegion')}</th>
                <th className="px-3 py-2">{t('regional.colBranch')}</th>
                <th className="px-3 py-2 text-center">{t('regional.colTotal')}</th>
                <th className="px-3 py-2 text-center">{t('regional.colOpen')}</th>
                <th className="px-3 py-2 text-center">{t('regional.colCompleted')}</th>
                <th className="px-3 py-2 text-center">{t('regional.colBreached')}</th>
                <th className="px-3 py-2 text-center">{t('regional.colSla')}</th>
              </tr>
            </thead>
            <tbody>
              {filteredRows.map((row, index) => {
                const firstOfRegion = index === 0 || regionKey(filteredRows[index - 1]) !== regionKey(row);

                return (
                  <tr key={`${regionKey(row)}-${row.branch_code ?? 'none'}`} className="border-b border-slate-100 dark:border-slate-800">
                    <td className="px-3 py-2 font-semibold">
                      {firstOfRegion && <>
                        {row.region_name}
                        {row.local_code && <span className="ml-2 rounded-lg bg-slate-100 px-2 py-0.5 font-mono text-xs font-normal dark:bg-slate-800">{row.local_code}</span>}
                      </>}
                    </td>
                    <td className="px-3 py-2">
                      {row.branch_code && <span className="mr-2 font-mono text-xs text-slate-500">{row.branch_code}</span>}
                      {row.branch_name}
                    </td>
                    <td className="px-3 py-2 text-center font-bold">{row.total}</td>
                    <td className="px-3 py-2 text-center">{row.open}</td>
                    <td className="px-3 py-2 text-center text-emerald-600 dark:text-emerald-400">{row.completed}</td>
                    <td className="px-3 py-2 text-center text-rose-600 dark:text-rose-400">{row.breached}</td>
                    <td className="px-3 py-2 text-center">
                      <span className={`rounded-full px-2 py-0.5 font-extrabold ${
                        row.sla_percent >= 95
                          ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                          : 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300'
                      }`}>
                        {row.sla_percent}%
                      </span>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
        {!loading && !filteredRows.length && <p className="mt-3 text-sm text-slate-500">{t('regional.noBranchTickets')}</p>}
      </section>
    </main>
  );
};
