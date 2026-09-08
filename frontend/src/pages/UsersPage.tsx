import React, { useState, useEffect, useMemo } from 'react';
import {
  UserCheck,
  Building2,
  Search,
  RefreshCw,
  TrendingUp,
  Ticket as TicketIcon,
  CheckCircle2,
  Clock,
  X,
  ArrowUpDown,
  AlertTriangle,
} from 'lucide-react';
import { AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';
import { Link } from 'react-router-dom';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { EmptyState } from '@/shared/presentation/components/EmptyState';
import { useT } from '@/shared/presentation/i18n/i18n';

interface RequesterRow {
  user_id: number;
  username: string;
  full_name: string;
  image: string | null;
  email: string | null;
  phone: string | null;
  department_id: number | null;
  department_name: string;
  branch_name: string;
  position_name: string;
  total_tickets: number;
  open_tickets: number;
  in_progress_tickets: number;
  resolved_tickets: number;
  rejected_tickets: number;
  last_ticket_at: string | null;
  last_ticket_no: string | null;
  last_ticket_subject: string | null;
}

interface DepartmentRow {
  department_id: number;
  department_name: string;
  branch_name: string;
  total_tickets: number;
  percentage: number;
  active_users_count: number;
  open_tickets: number;
  in_progress_tickets: number;
  resolved_tickets: number;
  rejected_tickets: number;
  resolution_rate: number;
  avg_resolution_formatted: string;
  last_ticket_at: string | null;
}

interface DepartmentStatsResponse {
  kpis: {
    total_tickets: number;
    total_departments: number;
    total_requesters: number;
    total_open: number;
    total_in_progress: number;
    total_resolved: number;
    total_rejected: number;
    resolution_rate: number;
    top_department: { id: number; name: string; count: number; percentage: number } | null;
  };
  departments: DepartmentRow[];
  trend: { date: string; short_date: string; day_name: string; count: number }[];
}

interface DepartmentTicketRow {
  id: number;
  ticket_no: string;
  subject: string;
  status_name: string | null;
  status_color: string | null;
  priority_name: string | null;
  created_at: string;
  requester_first_name: string | null;
  requester_last_name: string | null;
  requester_username: string | null;
}

interface LookupItem {
  id: number;
  name: string;
}

type TabKey = 'users' | 'departments';

const PERIOD_LABELS: Record<string, string> = {
  today: 'usersPage.periodToday',
  week: 'usersPage.periodWeek',
  month: 'usersPage.periodMonth',
  quarter: 'usersPage.periodQuarter',
  year: 'usersPage.periodYear',
  all: 'usersPage.periodAll',
};

const PERIODS = Object.keys(PERIOD_LABELS);

const formatDate = (value?: string | null): string =>
  value ? new Date(value).toLocaleDateString('uz-UZ', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '—';

const avatarUrl = (name: string, image?: string | null): string =>
  image || `https://ui-avatars.com/api/?name=${encodeURIComponent(name || 'User')}&size=128&bold=true&background=0D8ABC&color=fff`;

/** Foydalanuvchilar va bo'limlar kesimida zayavka statistikasi. */
export const UsersPage: React.FC = () => {
  const t = useT();

  // Bo'limlar statistikasi birinchi turadi — ekran shundan boshlanadi.
  const [tab, setTab] = useState<TabKey>('departments');
  const [period, setPeriod] = useState<string>('month');
  const [branchId, setBranchId] = useState<string>('all');
  const [departmentId, setDepartmentId] = useState<string>('all');
  const [searchInput, setSearchInput] = useState('');
  const [search, setSearch] = useState('');

  const [branches, setBranches] = useState<LookupItem[]>([]);
  const [departments, setDepartments] = useState<LookupItem[]>([]);

  const [users, setUsers] = useState<RequesterRow[]>([]);
  const [usersMeta, setUsersMeta] = useState({ current_page: 1, last_page: 1, total: 0 });
  const [page, setPage] = useState(1);
  const [sortBy, setSortBy] = useState('total_tickets');
  const [sortOrder, setSortOrder] = useState<'asc' | 'desc'>('desc');

  const [stats, setStats] = useState<DepartmentStatsResponse | null>(null);

  const [loading, setLoading] = useState(true);
  const [isError, setIsError] = useState(false);

  // Drill-down: tanlangan bo'limning oxirgi zayavkalari
  const [openDept, setOpenDept] = useState<DepartmentRow | null>(null);
  const [deptTickets, setDeptTickets] = useState<DepartmentTicketRow[]>([]);
  const [deptTicketsLoading, setDeptTicketsLoading] = useState(false);

  // Qidiruvni debounce qilamiz — har harfda so'rov yuborilmasin.
  useEffect(() => {
    const timer = setTimeout(() => {
      setSearch(searchInput.trim());
      setPage(1);
    }, 400);
    return () => clearTimeout(timer);
  }, [searchInput]);

  useEffect(() => {
    axiosClient
      .get<{ data: LookupItem[] }>('/branches', { params: { per_page: 100 } })
      .then((res) => setBranches(res.data?.data || []))
      .catch(() => setBranches([]));

    axiosClient
      .get<{ data: LookupItem[] }>('/departments')
      .then((res) => setDepartments(res.data?.data || []))
      .catch(() => setDepartments([]));
  }, []);

  const fetchData = () => {
    setLoading(true);
    setIsError(false);

    const request =
      tab === 'users'
        ? axiosClient
            .get('/users/requester-stats', {
              params: {
                period,
                branch_id: branchId,
                department_id: departmentId,
                search: search || undefined,
                sort_by: sortBy,
                sort_order: sortOrder,
                page,
                per_page: 20,
              },
            })
            .then((res) => {
              setUsers(res.data?.data || []);
              setUsersMeta({
                current_page: res.data?.meta?.current_page || 1,
                last_page: res.data?.meta?.last_page || 1,
                total: res.data?.meta?.total || 0,
              });
            })
        : axiosClient
            .get<DepartmentStatsResponse>('/users/department-stats', {
              params: { period, branch_id: branchId, search: search || undefined },
            })
            .then((res) => setStats(res.data));

    request.catch(() => setIsError(true)).finally(() => setLoading(false));
  };

  useEffect(() => {
    fetchData();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [tab, period, branchId, departmentId, search, sortBy, sortOrder, page]);

  const openDepartment = (dept: DepartmentRow) => {
    setOpenDept(dept);
    setDeptTickets([]);
    setDeptTicketsLoading(true);
    axiosClient
      .get<{ data: DepartmentTicketRow[] }>(`/users/department/${dept.department_id}/tickets`, {
        params: { limit: 15 },
      })
      .then((res) => setDeptTickets(res.data?.data || []))
      .catch(() => setDeptTickets([]))
      .finally(() => setDeptTicketsLoading(false));
  };

  const toggleSort = (column: string) => {
    if (sortBy === column) {
      setSortOrder((prev) => (prev === 'desc' ? 'asc' : 'desc'));
    } else {
      setSortBy(column);
      setSortOrder('desc');
    }
    setPage(1);
  };

  const kpis = stats?.kpis;

  const kpiCards = useMemo(
    () => [
      { label: t('usersPage.kpiTotalTickets'), value: String(kpis?.total_tickets ?? 0), icon: TicketIcon, tone: 'text-brand-600 dark:text-brand-400' },
      { label: t('usersPage.kpiActiveRequesters'), value: String(kpis?.total_requesters ?? 0), icon: UserCheck, tone: 'text-indigo-600 dark:text-indigo-400' },
      { label: t('usersPage.kpiTotalDepartments'), value: String(kpis?.total_departments ?? 0), icon: Building2, tone: 'text-amber-600 dark:text-amber-400' },
      { label: t('usersPage.kpiResolutionRate'), value: `${kpis?.resolution_rate ?? 0}%`, icon: CheckCircle2, tone: 'text-emerald-600 dark:text-emerald-400' },
    ],
    [kpis, t]
  );

  const selectClass =
    'px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/60 text-slate-900 dark:text-slate-100 text-xs sm:text-sm font-bold outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all cursor-pointer';

  return (
    <div className="w-full px-4 sm:px-8 lg:px-12 py-6 space-y-5">
      {/* Sarlavha */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <div className="w-11 h-11 rounded-2xl bg-brand-50 dark:bg-brand-950/50 flex items-center justify-center text-brand-600 dark:text-brand-400">
            <UserCheck className="w-6 h-6" />
          </div>
          <div>
            <h1 className="text-lg sm:text-xl font-black text-slate-900 dark:text-slate-100">{t('usersPage.title')}</h1>
            <p className="text-xs sm:text-sm font-semibold text-slate-500 dark:text-slate-400">{t('usersPage.subtitle')}</p>
          </div>
        </div>

        <button
          type="button"
          onClick={fetchData}
          disabled={loading}
          className="inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold hover:bg-slate-50 dark:hover:bg-slate-700/50 disabled:opacity-50 transition-all cursor-pointer"
        >
          <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
          {t('usersPage.refresh')}
        </button>
      </div>

      {/* Tablar */}
      <div className="flex items-center gap-2 border-b border-slate-200 dark:border-slate-700">
        {(['departments', 'users'] as TabKey[]).map((key) => (
          <button
            key={key}
            type="button"
            onClick={() => {
              setTab(key);
              setPage(1);
            }}
            className={`px-4 py-2.5 -mb-px text-xs sm:text-sm font-black border-b-2 transition-colors cursor-pointer ${
              tab === key
                ? 'border-brand-500 text-brand-600 dark:text-brand-400'
                : 'border-transparent text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200'
            }`}
          >
            {key === 'users' ? t('usersPage.tabRequesters') : t('usersPage.tabDepartments')}
          </button>
        ))}
      </div>

      {/* Filtrlar */}
      <div className="flex flex-col lg:flex-row gap-3">
        <div className="relative flex-1">
          <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
            <Search className="w-4 h-4" />
          </div>
          <input
            type="text"
            value={searchInput}
            onChange={(e) => setSearchInput(e.target.value)}
            placeholder={tab === 'users' ? t('usersPage.searchUserPlaceholder') : t('usersPage.searchPlaceholder')}
            className="w-full pl-9 pr-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/60 text-slate-900 dark:text-slate-100 text-xs sm:text-sm font-semibold outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-all"
          />
        </div>

        <select
          value={period}
          onChange={(e) => {
            setPeriod(e.target.value);
            setPage(1);
          }}
          className={selectClass}
        >
          {PERIODS.map((p) => (
            <option key={p} value={p}>
              {t(PERIOD_LABELS[p])}
            </option>
          ))}
        </select>

        <select
          value={branchId}
          onChange={(e) => {
            setBranchId(e.target.value);
            setPage(1);
          }}
          className={selectClass}
        >
          <option value="all">{t('usersPage.allBranches')}</option>
          {branches.map((b) => (
            <option key={b.id} value={b.id}>
              {b.name}
            </option>
          ))}
        </select>

        {tab === 'users' && (
          <select
            value={departmentId}
            onChange={(e) => {
              setDepartmentId(e.target.value);
              setPage(1);
            }}
            className={selectClass}
          >
            <option value="all">{t('usersPage.allDepartments')}</option>
            {departments.map((d) => (
              <option key={d.id} value={d.id}>
                {d.name}
              </option>
            ))}
          </select>
        )}
      </div>

      {isError && (
        <div className="flex items-center gap-3 p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900 text-rose-700 dark:text-rose-300">
          <AlertTriangle className="w-5 h-5 shrink-0" />
          <span className="text-xs sm:text-sm font-bold">{t('common.errorGeneric')}</span>
          <button type="button" onClick={fetchData} className="ml-auto text-xs font-black underline cursor-pointer">
            {t('common.retry')}
          </button>
        </div>
      )}

      {loading && (
        <div className="flex items-center justify-center min-h-[30vh]" role="status" aria-live="polite">
          <div className="w-8 h-8 border-4 border-brand-500 border-t-transparent rounded-full animate-spin" aria-hidden="true" />
        </div>
      )}

      {/* FOYDALANUVCHILAR TABI */}
      {!loading &&
        !isError &&
        tab === 'users' &&
        (users.length === 0 ? (
          <EmptyState title={t('usersPage.noData')} description={t('usersPage.noDataDesc')} />
        ) : (
          <div className="bg-white dark:bg-slate-800/90 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs">
                <thead>
                  <tr className="bg-slate-50 dark:bg-slate-900/40 text-slate-400 font-bold uppercase tracking-wider">
                    <th className="py-3 px-4">
                      <button
                        type="button"
                        onClick={() => toggleSort('first_name')}
                        className="inline-flex items-center gap-1 cursor-pointer hover:text-slate-600 dark:hover:text-slate-200"
                      >
                        {t('usersPage.userFullName')} <ArrowUpDown className="w-3 h-3" />
                      </button>
                    </th>
                    <th className="py-3 px-4">
                      <button
                        type="button"
                        onClick={() => toggleSort('department_name')}
                        className="inline-flex items-center gap-1 cursor-pointer hover:text-slate-600 dark:hover:text-slate-200"
                      >
                        {t('usersPage.departmentName')} <ArrowUpDown className="w-3 h-3" />
                      </button>
                    </th>
                    <th className="py-3 px-4 text-center">
                      <button
                        type="button"
                        onClick={() => toggleSort('total_tickets')}
                        className="inline-flex items-center gap-1 cursor-pointer hover:text-slate-600 dark:hover:text-slate-200"
                      >
                        {t('usersPage.totalSubmitted')} <ArrowUpDown className="w-3 h-3" />
                      </button>
                    </th>
                    <th className="py-3 px-4 text-center">{t('usersPage.statusOpen')}</th>
                    <th className="py-3 px-4 text-center">{t('usersPage.statusInProgress')}</th>
                    <th className="py-3 px-4 text-center">{t('usersPage.statusResolved')}</th>
                    <th className="py-3 px-4">{t('usersPage.lastTicket')}</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 dark:divide-slate-700/60 font-medium text-slate-700 dark:text-slate-200">
                  {users.map((u) => (
                    <tr key={u.user_id} className="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                      <td className="py-3 px-4">
                        <div className="flex items-center gap-3">
                          <img src={avatarUrl(u.full_name, u.image)} alt="" className="w-8 h-8 rounded-full object-cover shrink-0" />
                          <div className="min-w-0">
                            <div className="font-extrabold text-slate-900 dark:text-slate-100 truncate">{u.full_name}</div>
                            <div className="text-[11px] text-slate-400 truncate">{u.position_name}</div>
                          </div>
                        </div>
                      </td>
                      <td className="py-3 px-4">
                        <div className="font-bold truncate max-w-[220px]">{u.department_name}</div>
                        <div className="text-[11px] text-slate-400 truncate">{u.branch_name}</div>
                      </td>
                      <td className="py-3 px-4 text-center font-black text-brand-600 dark:text-brand-400">{u.total_tickets}</td>
                      <td className="py-3 px-4 text-center">
                        <span className="px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300 font-extrabold">
                          {u.open_tickets}
                        </span>
                      </td>
                      <td className="py-3 px-4 text-center">
                        <span className="px-2 py-0.5 rounded-full bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300 font-extrabold">
                          {u.in_progress_tickets}
                        </span>
                      </td>
                      <td className="py-3 px-4 text-center">
                        <span className="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 font-extrabold">
                          {u.resolved_tickets}
                        </span>
                      </td>
                      <td className="py-3 px-4">
                        {u.last_ticket_no ? (
                          <>
                            <div className="font-bold text-brand-600 dark:text-brand-400">{u.last_ticket_no}</div>
                            <div className="text-[11px] text-slate-400 truncate max-w-[220px]">{u.last_ticket_subject}</div>
                          </>
                        ) : (
                          <span className="text-slate-400">—</span>
                        )}
                        <div className="text-[11px] text-slate-400 font-mono">{formatDate(u.last_ticket_at)}</div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            {/* Sahifalash */}
            <div className="flex items-center justify-between gap-3 px-4 py-3 border-t border-slate-100 dark:border-slate-700/60">
              <span className="text-[11px] font-bold text-slate-400">
                {t('usersPage.pageInfo', {
                  page: usersMeta.current_page,
                  pages: usersMeta.last_page,
                  count: usersMeta.total,
                })}
              </span>
              <div className="flex items-center gap-2">
                <button
                  type="button"
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                  disabled={usersMeta.current_page <= 1}
                  className="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-xs font-bold disabled:opacity-40 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors cursor-pointer"
                >
                  {t('dashboard.previous')}
                </button>
                <button
                  type="button"
                  onClick={() => setPage((p) => Math.min(usersMeta.last_page, p + 1))}
                  disabled={usersMeta.current_page >= usersMeta.last_page}
                  className="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-xs font-bold disabled:opacity-40 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors cursor-pointer"
                >
                  {t('dashboard.next')}
                </button>
              </div>
            </div>
          </div>
        ))}

      {/* BO'LIMLAR TABI */}
      {!loading && !isError && tab === 'departments' && stats && (
        <div className="space-y-5">
          {/* KPI kartalar */}
          <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
            {kpiCards.map((card) => (
              <div key={card.label} className="bg-white dark:bg-slate-800/90 rounded-2xl p-4 border border-slate-200 dark:border-slate-700 shadow-sm">
                <div className="flex items-center justify-between">
                  <span className="text-[11px] font-bold uppercase tracking-wider text-slate-400">{card.label}</span>
                  <card.icon className={`w-4 h-4 ${card.tone}`} />
                </div>
                <div className="mt-2 text-2xl font-black text-slate-900 dark:text-slate-100">{card.value}</div>
              </div>
            ))}
          </div>

          {kpis?.top_department && (
            <div className="flex items-center gap-3 p-4 rounded-2xl bg-brand-50 dark:bg-brand-950/40 border border-brand-200 dark:border-brand-900">
              <TrendingUp className="w-5 h-5 text-brand-600 dark:text-brand-400 shrink-0" />
              <span className="text-xs sm:text-sm font-bold text-brand-800 dark:text-brand-200">
                {t('usersPage.kpiTopDepartment')}: {kpis.top_department.name} — {kpis.top_department.count} (
                {kpis.top_department.percentage}%)
              </span>
            </div>
          )}

          {/* Trend grafigi */}
          {stats.trend.length > 0 && (
            <div className="bg-white dark:bg-slate-800/90 rounded-2xl p-4 sm:p-5 border border-slate-200 dark:border-slate-700 shadow-sm">
              <h3 className="text-sm font-black text-slate-900 dark:text-slate-100 mb-3">{t('usersPage.trendTitle')}</h3>
              <ResponsiveContainer width="100%" height={220}>
                <AreaChart data={stats.trend}>
                  <defs>
                    <linearGradient id="usersTrendFill" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="5%" stopColor="#0ea5e9" stopOpacity={0.35} />
                      <stop offset="95%" stopColor="#0ea5e9" stopOpacity={0} />
                    </linearGradient>
                  </defs>
                  <CartesianGrid strokeDasharray="3 3" stroke="#94a3b8" strokeOpacity={0.2} />
                  <XAxis dataKey="short_date" tick={{ fontSize: 11 }} stroke="#94a3b8" />
                  <YAxis allowDecimals={false} tick={{ fontSize: 11 }} stroke="#94a3b8" width={30} />
                  <Tooltip contentStyle={{ borderRadius: 12, fontSize: 12, fontWeight: 700 }} formatter={(value) => [value, t('usersPage.ticketsCount')]} />
                  <Area type="monotone" dataKey="count" stroke="#0ea5e9" strokeWidth={2} fill="url(#usersTrendFill)" />
                </AreaChart>
              </ResponsiveContainer>
            </div>
          )}

          {/* Bo'limlar jadvali */}
          {stats.departments.length === 0 ? (
            <EmptyState title={t('usersPage.noData')} description={t('usersPage.noDataDesc')} />
          ) : (
            <div className="bg-white dark:bg-slate-800/90 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
              <div className="overflow-x-auto">
                <table className="w-full text-left text-xs">
                  <thead>
                    <tr className="bg-slate-50 dark:bg-slate-900/40 text-slate-400 font-bold uppercase tracking-wider">
                      <th className="py-3 px-4">{t('usersPage.departmentName')}</th>
                      <th className="py-3 px-4 text-center">{t('usersPage.ticketsCount')}</th>
                      <th className="py-3 px-4 text-center">{t('usersPage.share')}</th>
                      <th className="py-3 px-4 text-center">{t('usersPage.kpiActiveRequesters')}</th>
                      <th className="py-3 px-4 text-center">{t('usersPage.statusOpen')}</th>
                      <th className="py-3 px-4 text-center">{t('usersPage.statusResolved')}</th>
                      <th className="py-3 px-4 text-center">{t('usersPage.avgResolution')}</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100 dark:divide-slate-700/60 font-medium text-slate-700 dark:text-slate-200">
                    {stats.departments.map((d) => (
                      <tr
                        key={`${d.department_id}-${d.department_name}`}
                        onClick={() => openDepartment(d)}
                        className="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors cursor-pointer"
                      >
                        <td className="py-3 px-4">
                          <div className="font-extrabold text-slate-900 dark:text-slate-100 truncate max-w-[280px]">{d.department_name}</div>
                          <div className="text-[11px] text-slate-400 truncate">{d.branch_name}</div>
                        </td>
                        <td className="py-3 px-4 text-center font-black text-brand-600 dark:text-brand-400">{d.total_tickets}</td>
                        <td className="py-3 px-4">
                          <div className="flex items-center gap-2">
                            <div className="flex-1 h-1.5 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden min-w-[50px]">
                              <div className="h-full rounded-full bg-brand-500" style={{ width: `${Math.min(d.percentage, 100)}%` }} />
                            </div>
                            <span className="text-[11px] font-black text-slate-500 dark:text-slate-400 w-10 text-right">{d.percentage}%</span>
                          </div>
                        </td>
                        <td className="py-3 px-4 text-center font-bold">{d.active_users_count}</td>
                        <td className="py-3 px-4 text-center">
                          <span className="px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300 font-extrabold">
                            {d.open_tickets}
                          </span>
                        </td>
                        <td className="py-3 px-4 text-center">
                          <span className="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 font-extrabold">
                            {d.resolved_tickets}
                          </span>
                        </td>
                        <td className="py-3 px-4 text-center font-mono text-slate-500 dark:text-slate-400">{d.avg_resolution_formatted}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}
        </div>
      )}

      {/* Bo'lim zayavkalari paneli */}
      {openDept && (
        <div className="fixed inset-0 z-50 flex justify-end bg-slate-900/40 backdrop-blur-sm" onClick={() => setOpenDept(null)}>
          <div
            className="w-full max-w-lg h-full bg-white dark:bg-slate-800 shadow-2xl flex flex-col"
            onClick={(e) => e.stopPropagation()}
            role="dialog"
            aria-modal="true"
          >
            <div className="flex items-start justify-between gap-3 p-5 border-b border-slate-200 dark:border-slate-700">
              <div className="min-w-0">
                <h3 className="text-sm font-black text-slate-900 dark:text-slate-100 truncate">{openDept.department_name}</h3>
                <p className="text-xs font-semibold text-slate-400">{t('usersPage.ticketsModalSub')}</p>
              </div>
              <button
                type="button"
                onClick={() => setOpenDept(null)}
                className="p-1.5 rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors cursor-pointer"
                aria-label={t('usersPage.close')}
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="flex-1 overflow-y-auto p-5 space-y-2">
              {deptTicketsLoading && (
                <div className="flex items-center justify-center py-10">
                  <div className="w-6 h-6 border-4 border-brand-500 border-t-transparent rounded-full animate-spin" />
                </div>
              )}

              {!deptTicketsLoading && deptTickets.length === 0 && (
                <p className="text-center text-xs font-bold text-slate-400 py-10">{t('usersPage.noData')}</p>
              )}

              {deptTickets.map((ticket) => (
                <Link
                  key={ticket.id}
                  to={`/task/${ticket.id}`}
                  className="block p-3 rounded-xl border border-slate-200 dark:border-slate-700 hover:border-brand-400 dark:hover:border-brand-600 transition-colors"
                >
                  <div className="flex items-center justify-between gap-2">
                    <span className="text-xs font-black text-brand-600 dark:text-brand-400">{ticket.ticket_no}</span>
                    {ticket.status_name && (
                      <span
                        className="px-2 py-0.5 rounded-full text-[10px] font-extrabold text-white"
                        style={{ backgroundColor: ticket.status_color || '#64748b' }}
                      >
                        {ticket.status_name}
                      </span>
                    )}
                  </div>
                  <p className="mt-1 text-xs font-bold text-slate-800 dark:text-slate-100 line-clamp-2">{ticket.subject}</p>
                  <div className="mt-1.5 flex items-center gap-3 text-[11px] font-semibold text-slate-400">
                    <span className="truncate">
                      {[ticket.requester_first_name, ticket.requester_last_name].filter(Boolean).join(' ') || ticket.requester_username || '—'}
                    </span>
                    <span className="inline-flex items-center gap-1 shrink-0">
                      <Clock className="w-3 h-3" />
                      {formatDate(ticket.created_at)}
                    </span>
                  </div>
                </Link>
              ))}
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
