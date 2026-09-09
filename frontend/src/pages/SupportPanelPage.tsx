import React, { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import {
  ArrowLeft,
  CalendarRange,
  ChevronLeft,
  ChevronRight,
  Headphones,
  Search,
  Star,
  X,
} from 'lucide-react';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { useT } from '@/shared/presentation/i18n/i18n';
import { EmptyState } from '@/shared/presentation/components/EmptyState';
import { StaffFilterStrip, type EmployeeAvatar } from '@/modules/tasks/infrastructure/presentation/components/StaffFilterStrip';

interface LastTicket {
  id: number;
  ticket_no: string;
  subject: string;
  created_at: string;
}

interface StaffRow {
  user_id: number;
  username: string;
  name: string;
  image: string | null;
  department_name: string | null;
  branch_name: string | null;
  total_tickets: number;
  open_tickets: number;
  in_progress_tickets: number;
  resolved_tickets: number;
  rejected_tickets: number;
  sla_tracked: number;
  sla_breached: number;
  sla_compliance: number | null;
  avg_resolution_minutes: number | null;
  avg_rating: number | null;
  last_ticket: LastTicket | null;
}

interface AgentTicket {
  id: number;
  ticket_no: string;
  subject: string;
  status_id: number;
  status_name: string | null;
  priority_name: string | null;
  requester_username: string | null;
  created_at: string;
  resolved_at: string | null;
  due_at: string | null;
  client_rating: number | null;
  spent_minutes: number | null;
  sla_breached: boolean;
}

interface Meta {
  total: number;
  per_page: number;
  current_page: number;
  last_page: number;
}

const emptyMeta: Meta = { total: 0, per_page: 20, current_page: 1, last_page: 1 };

const avatarFallback = (name: string) =>
  `https://ui-avatars.com/api/?name=${encodeURIComponent(name || 'User')}&size=128&bold=true&background=0D8ABC&color=fff`;

const fieldClass =
  'rounded-xl border border-slate-200 bg-slate-50/60 px-3 py-2 text-xs font-semibold text-slate-900 outline-none transition-all focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 dark:border-slate-700 dark:bg-slate-900/40 dark:text-slate-100 dark:[&::-webkit-calendar-picker-indicator]:invert';
const cardClass = 'rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800/60';

const dateText = (value?: string | null) => (value ? new Date(value.replace(' ', 'T')).toLocaleDateString() : '—');

/**
 * Support paneli — xodimlar kesimida zayavkalar va SLA ko'rsatkichlari.
 * Xodim tanlansa, uning zayavkalari ro'yxat ko'rinishida ochiladi.
 */
export const SupportPanelPage: React.FC = () => {
  const t = useT();

  const [staff, setStaff] = useState<StaffRow[]>([]);
  const [staffMeta, setStaffMeta] = useState<Meta>(emptyMeta);
  // Xodim URL orqali ham tanlanadi (`/support-panel?user=12`) — monitoringdagi
  // "Top xodimlar" ro'yxatidan shu manzilga o'tiladi va havola ulashiladi.
  const [searchParams, setSearchParams] = useSearchParams();
  const userParam = Number(searchParams.get('user'));
  const [selectedUserId, setSelectedUserId] = useState<number | null>(userParam > 0 ? userParam : null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [refresh, setRefresh] = useState(0);
  const reload = useCallback(() => setRefresh((n) => n + 1), []);

  // Umumiy (xodimlar jadvali) filtri
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);

  // Tanlangan xodim zayavkalari uchun ichki filtr
  const [ticketStatus, setTicketStatus] = useState<'all' | 'in_progress' | 'done' | 'rejected'>('all');
  const [ticketSearch, setTicketSearch] = useState('');
  const [ticketPage, setTicketPage] = useState(1);
  const [tickets, setTickets] = useState<AgentTicket[]>([]);
  const [ticketsMeta, setTicketsMeta] = useState<Meta>(emptyMeta);
  const [ticketsLoading, setTicketsLoading] = useState(false);

  const selected = useMemo(() => staff.find((row) => row.user_id === selectedUserId) ?? null, [staff, selectedUserId]);

  // Aktiv zayavkalar = ochiq + jarayonda; avatar bo'sh bo'lsa strip bosh
  // harflardan o'zi yasaydi (dashboarddagi bilan bir xil xatti-harakat).
  const employees = useMemo<EmployeeAvatar[]>(
    () => staff.map((row) => ({
      userId: row.user_id,
      name: row.name,
      username: row.username,
      activeCount: row.open_tickets + row.in_progress_tickets,
      avatarUrl: row.image || '',
    })),
    [staff],
  );

  useEffect(() => {
    const controller = new AbortController();
    const timer = window.setTimeout(() => {
      setLoading(true);
      axiosClient
        .get('/support-panel/staff', {
          params: {
            start_date: dateFrom || undefined,
            end_date: dateTo || undefined,
            search: search || undefined,
            page,
          },
          signal: controller.signal,
        })
        .then((res) => {
          setStaff(res.data.data || []);
          setStaffMeta(res.data.meta || emptyMeta);
          setError('');
        })
        .catch((e: any) => {
          if (controller.signal.aborted) return;
          setError(e.response?.data?.message || t('supportPanel.loadError'));
        })
        .finally(() => {
          if (!controller.signal.aborted) setLoading(false);
        });
    }, 250);

    return () => {
      window.clearTimeout(timer);
      controller.abort();
    };
  }, [dateFrom, dateTo, search, page, refresh, t]);

  useEffect(() => {
    if (selectedUserId === null) return;

    const controller = new AbortController();
    const timer = window.setTimeout(() => {
      setTicketsLoading(true);
      axiosClient
        .get(`/support-panel/staff/${selectedUserId}/tickets`, {
          params: {
            start_date: dateFrom || undefined,
            end_date: dateTo || undefined,
            status: ticketStatus === 'all' ? undefined : ticketStatus,
            search: ticketSearch || undefined,
            page: ticketPage,
          },
          signal: controller.signal,
        })
        .then((res) => {
          setTickets(res.data.data || []);
          setTicketsMeta(res.data.meta || emptyMeta);
        })
        .catch(() => {})
        .finally(() => {
          if (!controller.signal.aborted) setTicketsLoading(false);
        });
    }, 250);

    return () => {
      window.clearTimeout(timer);
      controller.abort();
    };
  }, [selectedUserId, dateFrom, dateTo, ticketStatus, ticketSearch, ticketPage, refresh]);

  // Brauzerning "orqaga" tugmasi bilan holat mos yuradi.
  useEffect(() => {
    setSelectedUserId(userParam > 0 ? userParam : null);
  }, [userParam]);

  const selectStaff = (userId: number | null) => {
    setSelectedUserId(userId);
    setSearchParams(userId ? { user: String(userId) } : {}, { replace: true });
    setTicketStatus('all');
    setTicketSearch('');
    setTicketPage(1);
  };

  const statusTabs: { id: 'all' | 'in_progress' | 'done' | 'rejected'; label: string }[] = [
    { id: 'all', label: t('supportPanel.statusAll') },
    { id: 'in_progress', label: t('supportPanel.statusInProgress') },
    { id: 'done', label: t('supportPanel.statusDone') },
    { id: 'rejected', label: t('supportPanel.statusRejected') },
  ];

  return (
    <div className="w-full space-y-5 px-4 py-6 sm:px-8">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="flex items-center gap-3 text-2xl font-extrabold text-slate-900 dark:text-slate-100">
            <Headphones className="h-7 w-7 text-brand-500" />
            {t('supportPanel.title')}
          </h1>
          <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">{t('supportPanel.subtitle')}</p>
        </div>
      </header>

      {/* Xodim tanlash chizig'i — boshqaruv paneli va jamoa yuklamasidagi bilan
          bir xil komponent: dumaloq avatar, tagida ism, o'ngda aktiv zayavkalar soni. */}
      <StaffFilterStrip
        employees={employees}
        selectedUserId={selectedUserId}
        onSelect={selectStaff}
        onRefresh={reload}
        isRefreshing={loading}
      />

      {/* Sana filtri va qidiruv */}
      <section className={`${cardClass} space-y-3 p-4`}>
        <span className="flex items-center gap-2 text-xs font-extrabold uppercase tracking-wide text-slate-500 dark:text-slate-400">
          <CalendarRange className="h-4 w-4 text-brand-500" />
          {t('supportPanel.dateFilter')}
        </span>

        <div className="flex flex-wrap items-center gap-2 sm:gap-3">
          <div className="flex items-center gap-2">
            <span className="whitespace-nowrap text-[11px] font-bold text-slate-500 dark:text-slate-400">{t('supportPanel.dateFrom')}</span>
            <input
              type="date"
              aria-label={t('supportPanel.dateFrom')}
              value={dateFrom}
              max={dateTo || undefined}
              onChange={(e) => { setDateFrom(e.target.value); setPage(1); setTicketPage(1); }}
              className={fieldClass}
            />
          </div>
          <div className="flex items-center gap-2">
            <span className="whitespace-nowrap text-[11px] font-bold text-slate-500 dark:text-slate-400">{t('supportPanel.dateTo')}</span>
            <input
              type="date"
              aria-label={t('supportPanel.dateTo')}
              value={dateTo}
              min={dateFrom || undefined}
              onChange={(e) => { setDateTo(e.target.value); setPage(1); setTicketPage(1); }}
              className={fieldClass}
            />
          </div>

          <div className="relative min-w-[14rem] flex-1">
            <Search className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
            <input
              aria-label={t('supportPanel.searchEmployee')}
              placeholder={t('supportPanel.searchEmployee')}
              value={search}
              onChange={(e) => { setSearch(e.target.value); setPage(1); }}
              className={`${fieldClass} w-full !pl-9`}
            />
          </div>

          {(dateFrom || dateTo || search) && (
            <button
              type="button"
              onClick={() => { setDateFrom(''); setDateTo(''); setSearch(''); setPage(1); setTicketPage(1); }}
              className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 transition-colors hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
            >
              <X className="h-3.5 w-3.5" />
              {t('supportPanel.clear')}
            </button>
          )}
        </div>
      </section>

      {error && (
        <div role="alert" className="rounded-2xl border border-error-300 bg-error-50 p-4 text-sm font-semibold text-error-600 dark:border-error-800 dark:bg-error-950/30 dark:text-error-300">
          {error}
          <button type="button" className="ml-3 underline" onClick={reload}>{t('supportPanel.retry')}</button>
        </div>
      )}

      {/* Xodimlar jadvali */}
      {selectedUserId === null && (
        <section className={`${cardClass} overflow-hidden`}>
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="bg-slate-100 text-[11px] uppercase text-slate-500 dark:bg-slate-800/70 dark:text-slate-400">
                <tr>
                  {['supportPanel.thEmployee', 'supportPanel.thDepartment', 'supportPanel.thTotal',
                    'supportPanel.thInProgress', 'supportPanel.thDone', 'supportPanel.thSla', 'supportPanel.thLast'].map((key) => (
                    <th key={key} className="whitespace-nowrap px-4 py-3 font-bold">{t(key)}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                {staff.map((row) => (
                  <tr
                    key={row.user_id}
                    onClick={() => selectStaff(row.user_id)}
                    className="cursor-pointer transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/60"
                  >
                    <td className="px-4 py-3">
                      <div className="flex items-center gap-3">
                        <img src={row.image || avatarFallback(row.name)} alt={row.name} className="h-9 w-9 rounded-full object-cover" />
                        <div className="min-w-0">
                          <p className="truncate text-xs font-bold text-slate-800 dark:text-slate-100">{row.name}</p>
                          <p className="truncate text-[11px] text-slate-400">@{row.username}</p>
                        </div>
                      </div>
                    </td>
                    <td className="px-4 py-3 text-xs text-slate-600 dark:text-slate-300">
                      {row.department_name || t('supportPanel.noDepartment')}
                      <p className="text-[11px] text-slate-400">{row.branch_name || '—'}</p>
                    </td>
                    <td className="px-4 py-3 font-black text-slate-800 dark:text-slate-100">{row.total_tickets}</td>
                    <td className="px-4 py-3 font-bold text-sky-600 dark:text-sky-400">{row.in_progress_tickets}</td>
                    <td className="px-4 py-3 font-bold text-emerald-600 dark:text-emerald-400">{row.resolved_tickets}</td>
                    <td className="px-4 py-3">
                      {row.sla_tracked === 0 ? (
                        <span className="text-[11px] text-slate-400">{t('supportPanel.slaNone')}</span>
                      ) : (
                        <div className="space-y-1">
                          <span className={`text-xs font-black ${(row.sla_compliance ?? 0) >= 90 ? 'text-emerald-600 dark:text-emerald-400' : (row.sla_compliance ?? 0) >= 75 ? 'text-amber-600 dark:text-amber-400' : 'text-red-500'}`}>
                            {row.sla_compliance}%
                          </span>
                          <p className="text-[11px] text-slate-400">
                            {t('supportPanel.slaBreached', { count: row.sla_breached, total: row.sla_tracked })}
                          </p>
                        </div>
                      )}
                      {row.avg_rating !== null && (
                        <p className="mt-1 flex items-center gap-1 text-[11px] font-bold text-purple-500">
                          <Star className="h-3 w-3 fill-current" />
                          {row.avg_rating}
                        </p>
                      )}
                    </td>
                    <td className="px-4 py-3 text-xs">
                      {row.last_ticket ? (
                        <>
                          <span className="font-mono font-bold text-brand-500">{row.last_ticket.ticket_no}</span>
                          <p className="max-w-[16rem] truncate text-slate-500 dark:text-slate-400">{row.last_ticket.subject}</p>
                          <p className="text-[11px] text-slate-400">{dateText(row.last_ticket.created_at)}</p>
                        </>
                      ) : (
                        <span className="text-slate-400">—</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {!loading && staff.length === 0 && (
            <EmptyState title={t('supportPanel.emptyStaff')} description={t('supportPanel.emptyStaffDesc')} />
          )}

          <footer className="flex items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 text-xs text-slate-500 dark:border-slate-800">
            <span>{t('supportPanel.pageInfo', { page: staffMeta.current_page, pages: staffMeta.last_page, count: staffMeta.total })}</span>
            <div className="flex items-center gap-2">
              <button
                type="button"
                aria-label={t('supportPanel.prevPage')}
                disabled={page <= 1 || loading}
                onClick={() => setPage((p) => p - 1)}
                className="rounded-lg border border-slate-200 p-2 disabled:opacity-40 dark:border-slate-700"
              >
                <ChevronLeft className="h-4 w-4" />
              </button>
              <button
                type="button"
                aria-label={t('supportPanel.nextPage')}
                disabled={page >= staffMeta.last_page || loading}
                onClick={() => setPage((p) => p + 1)}
                className="rounded-lg border border-slate-200 p-2 disabled:opacity-40 dark:border-slate-700"
              >
                <ChevronRight className="h-4 w-4" />
              </button>
            </div>
          </footer>
        </section>
      )}

      {/* Bitta xodim zayavkalari */}
      {selectedUserId !== null && (
        <section className={`${cardClass} overflow-hidden`}>
          <header className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 p-4 dark:border-slate-800">
            <div className="flex items-center gap-3">
              <button
                type="button"
                onClick={() => selectStaff(null)}
                className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 transition-colors hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
              >
                <ArrowLeft className="h-4 w-4" />
                {t('supportPanel.back')}
              </button>
              {selected && (
                <div className="flex items-center gap-3">
                  <img src={selected.image || avatarFallback(selected.name)} alt={selected.name} className="h-10 w-10 rounded-full object-cover" />
                  <div>
                    <p className="text-sm font-extrabold text-slate-900 dark:text-slate-100">{selected.name}</p>
                    <p className="text-[11px] text-slate-400">
                      {t('supportPanel.agentSummary', {
                        total: selected.total_tickets,
                        done: selected.resolved_tickets,
                        sla: selected.sla_compliance === null ? '—' : `${selected.sla_compliance}%`,
                      })}
                    </p>
                  </div>
                </div>
              )}
            </div>

            <div className="flex flex-wrap items-center gap-2">
              {statusTabs.map((tab) => (
                <button
                  key={tab.id}
                  type="button"
                  onClick={() => { setTicketStatus(tab.id); setTicketPage(1); }}
                  className={`rounded-full px-3.5 py-1.5 text-xs font-bold transition-all ${
                    ticketStatus === tab.id
                      ? 'bg-brand-500 text-white shadow-sm'
                      : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300'
                  }`}
                >
                  {tab.label}
                </button>
              ))}
              <div className="relative">
                <Search className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                <input
                  aria-label={t('supportPanel.searchTicket')}
                  placeholder={t('supportPanel.searchTicket')}
                  value={ticketSearch}
                  onChange={(e) => { setTicketSearch(e.target.value); setTicketPage(1); }}
                  className={`${fieldClass} !pl-9`}
                />
              </div>
            </div>
          </header>

          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="bg-slate-100 text-[11px] uppercase text-slate-500 dark:bg-slate-800/70 dark:text-slate-400">
                <tr>
                  {['supportPanel.thTicket', 'supportPanel.thSubject', 'supportPanel.thRequester', 'supportPanel.thStatus',
                    'supportPanel.thCreated', 'supportPanel.thResolved', 'supportPanel.thSlaState', 'supportPanel.thRating'].map((key) => (
                    <th key={key} className="whitespace-nowrap px-4 py-3 font-bold">{t(key)}</th>
                  ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-200 dark:divide-slate-800">
                {tickets.map((ticket) => (
                  <tr key={ticket.id} className="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/60">
                    <td className="px-4 py-3">
                      <Link to={`/task/${ticket.id}`} className="font-mono text-xs font-bold text-brand-500 hover:underline">
                        {ticket.ticket_no}
                      </Link>
                    </td>
                    <td className="max-w-[20rem] truncate px-4 py-3 text-xs text-slate-700 dark:text-slate-200">{ticket.subject}</td>
                    <td className="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">@{ticket.requester_username || '—'}</td>
                    <td className="px-4 py-3 text-xs font-bold text-slate-600 dark:text-slate-300">{ticket.status_name || '—'}</td>
                    <td className="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">{dateText(ticket.created_at)}</td>
                    <td className="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">{dateText(ticket.resolved_at)}</td>
                    <td className="px-4 py-3 text-xs">
                      {ticket.due_at === null ? (
                        <span className="text-slate-400">—</span>
                      ) : ticket.sla_breached ? (
                        <span className="rounded-full bg-red-100 px-2 py-1 font-bold text-red-600 dark:bg-red-950/50 dark:text-red-300">
                          {t('supportPanel.slaLate')}
                        </span>
                      ) : (
                        <span className="rounded-full bg-emerald-100 px-2 py-1 font-bold text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">
                          {t('supportPanel.slaOnTime')}
                        </span>
                      )}
                    </td>
                    <td className="px-4 py-3 text-xs">
                      {ticket.client_rating === null ? (
                        <span className="text-slate-400">—</span>
                      ) : (
                        <span className="flex items-center gap-1 font-bold text-purple-500">
                          <Star className="h-3 w-3 fill-current" />
                          {ticket.client_rating}
                        </span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {!ticketsLoading && tickets.length === 0 && (
            <EmptyState title={t('supportPanel.emptyTickets')} description={t('supportPanel.emptyTicketsDesc')} />
          )}

          <footer className="flex items-center justify-between gap-3 border-t border-slate-200 px-4 py-3 text-xs text-slate-500 dark:border-slate-800">
            <span>{t('supportPanel.pageInfo', { page: ticketsMeta.current_page, pages: ticketsMeta.last_page, count: ticketsMeta.total })}</span>
            <div className="flex items-center gap-2">
              <button
                type="button"
                aria-label={t('supportPanel.prevPage')}
                disabled={ticketPage <= 1 || ticketsLoading}
                onClick={() => setTicketPage((p) => p - 1)}
                className="rounded-lg border border-slate-200 p-2 disabled:opacity-40 dark:border-slate-700"
              >
                <ChevronLeft className="h-4 w-4" />
              </button>
              <button
                type="button"
                aria-label={t('supportPanel.nextPage')}
                disabled={ticketPage >= ticketsMeta.last_page || ticketsLoading}
                onClick={() => setTicketPage((p) => p + 1)}
                className="rounded-lg border border-slate-200 p-2 disabled:opacity-40 dark:border-slate-700"
              >
                <ChevronRight className="h-4 w-4" />
              </button>
            </div>
          </footer>
        </section>
      )}
    </div>
  );
};
