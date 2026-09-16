import React, { useCallback, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { BadgeCheck, Search } from 'lucide-react';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { useT } from '@/shared/presentation/i18n/i18n';
import { EmptyState } from '@/shared/presentation/components/EmptyState';
import { PermitRequest, PermitStatusBadge } from '@/shared/presentation/components/PermitStatusBadge';
import { InsideTimer } from '@/shared/presentation/components/InsideTimer';

/**
 * Ichki xavfsizlik → Elektron ruxsatnomalar.
 *
 * Ro'yxatdan so'rov ochiladi va tafsilotda hamma narsa ko'rinadi: tashrifchi,
 * so'rov yuborgan xodim kartochkasi (kim chaqirgan) hamda kirish/chiqish
 * qaydi. "Kirdi" va "Chiqdi" tugmalarini qorovul posti bosadi.
 */
export const SecurityPermitsPage: React.FC = () => {
  const t = useT();
  const navigate = useNavigate();

  const [rows, setRows] = useState<PermitRequest[]>([]);
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState<'' | 'PENDING' | 'APPROVED' | 'REJECTED'>('');
  // Tashrif sanasi bo'yicha oraliq — brauzerning `datetime-local` shakli.
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');

  const load = useCallback(async (query: string, status: string, fromAt: string, toAt: string) => {
    try {
      const res = await axiosClient.get<{ data: PermitRequest[] }>('/permit-requests', {
        params: {
          ...(query ? { search: query } : {}),
          ...(status ? { status } : {}),
          ...(fromAt ? { from: fromAt } : {}),
          ...(toAt ? { to: toAt } : {}),
        },
      });
      setRows(res.data?.data ?? []);
    } catch {
      setRows([]);
    }
  }, []);

  useEffect(() => {
    const id = setTimeout(() => void load(search.trim(), statusFilter, from, to), 300);

    return () => clearTimeout(id);
  }, [search, statusFilter, from, to, load]);

  const field = 'w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/60 text-slate-900 dark:text-slate-100 text-sm font-semibold outline-none focus:ring-2 focus:ring-rose-500/20 focus:border-rose-500';

  const when = (value: string | null) => (value ? new Date(value).toLocaleString() : t('permitReq.notSet'));

  return (
    <div className="p-4 sm:p-6 lg:p-8 space-y-6">
      <header className="flex items-center gap-3">
        <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-rose-50 text-rose-600 dark:bg-rose-950/40 dark:text-rose-300">
          <BadgeCheck className="h-6 w-6" />
        </span>
        <div className="min-w-0">
          <h1 className="truncate text-xl font-extrabold text-slate-900 dark:text-slate-100">{t('permitReq.title')}</h1>
          <p className="truncate text-xs font-semibold text-slate-500 dark:text-slate-400">{t('permitReq.queue')}</p>
        </div>
      </header>

      <section className="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/40 p-4 sm:p-6">
        <div className="mb-4 space-y-3">
          <div className="flex flex-wrap items-center gap-3">
            <div className="relative min-w-0 flex-1 sm:max-w-md">
              <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
              <input
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder={t('permitReq.search')}
                className={`${field} pl-9`}
              />
            </div>

            {/* Holat bo'yicha filtr — navbat odatda "Kutilmoqda" bilan ishlanadi. */}
            <div className="flex flex-wrap gap-1.5">
              {([
                ['', t('permitReq.filterAll')],
                ['PENDING', t('permitReq.status.PENDING')],
                ['APPROVED', t('permitReq.status.APPROVED')],
                ['REJECTED', t('permitReq.status.REJECTED')],
              ] as const).map(([value, label]) => (
                <button
                  key={value || 'all'}
                  type="button"
                  onClick={() => setStatusFilter(value as typeof statusFilter)}
                  aria-pressed={statusFilter === value}
                  className={`rounded-xl px-3 py-2 text-xs font-bold transition-colors ${
                    statusFilter === value
                      ? 'bg-rose-500 text-white'
                      : 'border border-slate-200 text-slate-600 hover:border-rose-300 dark:border-slate-700 dark:text-slate-300 dark:hover:border-rose-700'
                  }`}
                >
                  {label}
                </button>
              ))}
            </div>
          </div>

          {/* Tashrif sanasi bo'yicha "dan — gacha". Bo'sh qoldirilgan chegara
              cheklamaydi: faqat "dan" yozilsa — o'sha sanadan keyingilari. */}
          <div className="flex flex-wrap items-center gap-2">
            <label className="text-xs font-bold text-slate-500 dark:text-slate-400" htmlFor="sp-from">
              {t('permitReq.dateFrom')}
            </label>
            <input
              id="sp-from"
              type="datetime-local"
              value={from}
              onChange={(e) => setFrom(e.target.value)}
              className={`${field} sm:w-56`}
            />
            <label className="text-xs font-bold text-slate-500 dark:text-slate-400" htmlFor="sp-to">
              {t('permitReq.dateTo')}
            </label>
            <input
              id="sp-to"
              type="datetime-local"
              value={to}
              onChange={(e) => setTo(e.target.value)}
              className={`${field} sm:w-56`}
            />
            {(from || to) && (
              <button
                type="button"
                onClick={() => { setFrom(''); setTo(''); }}
                className="rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 hover:border-rose-300 dark:border-slate-700 dark:text-slate-300 dark:hover:border-rose-700"
              >
                {t('permitReq.dateClear')}
              </button>
            )}
          </div>
        </div>

        {rows.length === 0 ? (
          <EmptyState title={t('permitReq.empty')} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full min-w-[960px] text-left text-sm">
              <thead>
                <tr className="text-xs font-bold uppercase tracking-wide text-slate-400">
                  <th className="pb-2">ID</th>
                  <th className="pb-2">FIO</th>
                  <th className="pb-2">{t('permitReq.employee')}</th>
                  <th className="pb-2">{t('permitReq.enteredAt')}</th>
                  <th className="pb-2">{t('permitReq.exitedAt')}</th>
                  <th className="pb-2">{t('permitReq.insideFor')}</th>
                  <th className="pb-2">{t('permitReq.status.PENDING')}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-800">
                {rows.map((row) => (
                  <tr
                    key={row.id}
                    onClick={() => navigate(`/security-permits/${row.id}`)}
                    className="cursor-pointer text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800/60"
                  >
                    <td className="py-2.5 font-mono font-semibold">{row.id}</td>
                    <td className="py-2.5 font-semibold">{row.full_name}</td>
                    <td className="py-2.5 font-semibold">{row.requester_card.name ?? row.requester ?? '—'}</td>
                    <td className="py-2.5 font-semibold">{when(row.entered_at)}</td>
                    <td className="py-2.5 font-semibold">{when(row.exited_at)}</td>
                    <td className={`py-2.5 font-semibold ${row.exited_at ? 'text-slate-500' : 'text-emerald-600 dark:text-emerald-400'}`}>
                      {row.entered_at ? <InsideTimer enteredAt={row.entered_at} exitedAt={row.exited_at} /> : t('permitReq.notSet')}
                    </td>
                    <td className="py-2.5"><PermitStatusBadge status={row.status} /></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>

    </div>
  );
};
