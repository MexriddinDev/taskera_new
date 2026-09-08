import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import {
    Activity,
    AlarmClockCheck,
    Award,
    BarChart3,
    CheckCircle2,
    Clock,
    Maximize,
    Minimize,
    Monitor,
    RefreshCw,
    Star,
    Timer,
    TrendingUp,
} from 'lucide-react';
import {
    Area,
    AreaChart,
    CartesianGrid,
    Cell,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { useT } from '@/shared/presentation/i18n/i18n';

/** Jonli soat grafiklar joylashgan ota sahifani har soniyada qayta render qilmaydi. */
const LiveClock = React.memo(() => {
    const [time, setTime] = useState(() => new Date().toLocaleTimeString());

    useEffect(() => {
        const timer = window.setInterval(() => {
            if (document.visibilityState === 'visible') setTime(new Date().toLocaleTimeString());
        }, 1000);
        return () => window.clearInterval(timer);
    }, []);

    return <span className="text-xs font-mono text-slate-500 dark:text-slate-400">{time}</span>;
});

const AutoRefreshButton: React.FC<{ loading: boolean; onRefresh: () => void }> = React.memo(({ loading, onRefresh }) => {
    const [countdown, setCountdown] = useState(30);
    const countdownRef = useRef(30);

    useEffect(() => {
        const timer = window.setInterval(() => {
            if (document.visibilityState !== 'visible') return;
            countdownRef.current -= 1;
            if (countdownRef.current <= 0) {
                countdownRef.current = 30;
                onRefresh();
            }
            setCountdown(countdownRef.current);
        }, 1000);
        return () => window.clearInterval(timer);
    }, [onRefresh]);

    const refreshNow = () => {
        countdownRef.current = 30;
        setCountdown(30);
        onRefresh();
    };

    return (
        <button
            type="button"
            onClick={refreshNow}
            disabled={loading}
            className="px-3.5 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 text-xs font-semibold flex items-center gap-2 transition-colors disabled:opacity-60"
        >
            <RefreshCw className={`w-3.5 h-3.5 ${loading ? 'animate-spin' : ''}`} />
            <span>{countdown}s</span>
        </button>
    );
});

// ============================================================================
// TYPES — kept 1:1 with the /tickets/executive-monitoring API contract
// ============================================================================

interface TeamMember {
    userId: number;
    name: string;
    username: string;
    avatarUrl: string;
    done: number;
    inProgress: number;
    rating: number;
}

interface TeamMetric {
    teamId: number;
    teamName: string;
    assignedCount: number;
    completedCount: number;
    inProgressCount: number;
    avgSpentMinutes: number;
    slaPercent: number;
    members?: TeamMember[];
}

interface SpecialistItem {
    userId: number;
    name: string;
    username: string;
    avatarUrl: string;
    done: number;
    inProgress: number;
    avgSpentMinutes: number;
    clientRating: number;
}

interface UnassignedTicket {
    id: number;
    ticketNumber: string;
    todo: string;
    category: string;
    createdAt: string;
    priority: string;
}

interface HourlySpike {
    hour: string;
    count: number;
}

interface TrendPoint {
    date: string;
    short_date: string;
    day_name: string;
    count: number;
}

interface MonitoringData {
    kpis: {
        totalTickets: number;
        todayCompleted: number;
        openUnassigned: number;
        avgResolutionMinutes: number;
        avgRating: number;
        slaCompliancePercent: number;
    };
    teamMetrics: TeamMetric[];
    topSpecialists: SpecialistItem[];
    lowRatedSpecialists: SpecialistItem[];
    unassignedQueue: UnassignedTicket[];
    hourlySpikes: HourlySpike[];
    trend?: TrendPoint[];
    period?: string;
    categoryDistribution?: { key: string; name: string; value: number; percent: number; color: string }[];
}

// ============================================================================
// DESIGN TOKENS — one restrained accent family per group, reused everywhere
// (chart series, filter tabs, donut, legends) so the eye only has to learn
// the mapping once.
// ============================================================================

type GroupKey = 'software' | 'hardware' | 'network' | 'banking';

const GROUP_COLORS: Record<GroupKey, string> = {
    software: '#6366f1', // indigo-500
    hardware: '#0ea5e9', // sky-500
    network: '#f59e0b', // amber-500
    banking: '#8b5cf6', // violet-500
};

const cardClass =
    'bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm';

/** Vaqt filtri — UsersPage dagi davrlar bilan bir xil, tarjimalari ham o'sha. */
const PERIOD_LABELS: Record<string, string> = {
    today: 'usersPage.periodToday',
    week: 'usersPage.periodWeek',
    month: 'usersPage.periodMonth',
    quarter: 'usersPage.periodQuarter',
    year: 'usersPage.periodYear',
    all: 'usersPage.periodAll',
};

/** Daqiqani "6s 26d" ko'rinishiga keltiradi — bo'limlar jadvalidagidek. */
const formatMinutes = (minutes?: number | null): string => {
    if (!minutes || minutes <= 0) return '—';
    if (minutes < 60) return `${minutes} daq`;
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;
    if (hours < 24) return rest > 0 ? `${hours}s ${rest}d` : `${hours}s`;
    const days = Math.floor(hours / 24);
    return `${days}k ${hours % 24}s`;
};

export const MonitoringPage: React.FC = () => {
    const t = useT();
    const [data, setData] = useState<MonitoringData | null>(null);
    const [loading, setLoading] = useState(true);
    const [isTvMode, setIsTvMode] = useState(false);
    // Vaqt filtri — statistika sahifasidagi davrlar bilan bir xil.
    const [period, setPeriod] = useState<string>('month');

    const fetchMonitoringData = useCallback(async () => {
        setLoading(true);
        try {
            const res = await axiosClient.get('/tickets/executive-monitoring', { params: { period } });
            setData(res.data);
        } catch (e) {
            console.error('Failed to fetch executive monitoring data', e);
        } finally {
            setLoading(false);
        }
    }, [period]);

    useEffect(() => {
        fetchMonitoringData();
    }, [fetchMonitoringData]);

    const toggleFullscreen = () => {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(() => {});
            setIsTvMode(true);
        } else {
            document.exitFullscreen?.().catch(() => {});
            setIsTvMode(false);
        }
    };

    const kpis = data?.kpis ?? {
        totalTickets: 0,
        todayCompleted: 0,
        openUnassigned: 0,
        avgResolutionMinutes: 0,
        avgRating: 5.0,
        slaCompliancePercent: 100,
    };

    const teamMetrics = data?.teamMetrics ?? [];
    const topSpecialists = data?.topSpecialists ?? [];
    const unassignedQueue = data?.unassignedQueue ?? [];
    const hourlySpikes = data?.hourlySpikes ?? [];
    const trend = data?.trend ?? [];

    // Guruhlar jadvalidagi "Ulushi" ustuni uchun umumiy son.
    const totalAssigned = useMemo(
        () => teamMetrics.reduce((sum, team) => sum + team.assignedCount, 0),
        [teamMetrics]
    );

    const categoryDistribution = useMemo(() => {
        if (data?.categoryDistribution && data.categoryDistribution.length > 0) {
            return data.categoryDistribution;
        }
        const keys = Object.keys(GROUP_COLORS) as GroupKey[];
        const entries = teamMetrics.map((team, idx) => ({
            key: keys[idx] ?? `g${idx}`,
            name: team.teamName,
            value: team.completedCount,
            percent: 0,
            color: GROUP_COLORS[keys[idx]] ?? '#94a3b8',
        }));
        const sum = entries.reduce((a, b) => a + b.value, 0) || 1;
        entries.forEach((e) => {
            e.percent = Math.round((e.value / sum) * 100);
        });
        return entries.sort((a, b) => b.value - a.value);
    }, [data, teamMetrics]);

    return (
        <div className="w-full min-h-screen bg-gray-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 p-6 sm:p-10 space-y-6 font-sans">
            {/* HEADER ------------------------------------------------------------ */}
            <div className={`w-full ${cardClass} p-5 flex flex-wrap items-center justify-between gap-4`}>
                <div className="flex items-center gap-4">
                    <div className="w-11 h-11 rounded-xl bg-indigo-600 text-white flex items-center justify-center flex-shrink-0">
                        <Monitor className="w-5 h-5" />
                    </div>
                    <div>
                        <div className="flex items-center gap-2 mb-0.5">
              <span className="inline-flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400">
                <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" />
                {t('monitoring.liveBadge')}
              </span>
                            <LiveClock />
                        </div>
                        <h1 className="text-xl font-bold tracking-tight text-slate-900 dark:text-white">
                            {t('monitoring.title')}
                        </h1>
                        <p className="text-xs text-slate-500 dark:text-slate-400">
                            {t('monitoring.subtitle')}
                        </p>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    <select
                        value={period}
                        onChange={(e) => setPeriod(e.target.value)}
                        className="px-3 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold outline-none focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 transition-all cursor-pointer"
                    >
                        {Object.keys(PERIOD_LABELS).map((key) => (
                            <option key={key} value={key}>
                                {t(PERIOD_LABELS[key])}
                            </option>
                        ))}
                    </select>
                    <AutoRefreshButton loading={loading} onRefresh={fetchMonitoringData} />
                    <button
                        onClick={toggleFullscreen}
                        className="p-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white transition-colors"
                        title={t('monitoring.tvMode')}
                    >
                        {isTvMode ? <Minimize className="w-4 h-4" /> : <Maximize className="w-4 h-4" />}
                    </button>
                </div>
            </div>

            {/* KPI ROW ------------------------------------------------------------ */}
            <div className="grid grid-cols-2 lg:grid-cols-6 gap-4">
                <KpiCard icon={BarChart3} label={t('monitoring.kpiTotal')} value={kpis.totalTickets} suffix={t('monitoring.unitCount')} />
                <KpiCard
                    icon={CheckCircle2}
                    label={t('monitoring.kpiTodayClosed')}
                    value={kpis.todayCompleted}
                    suffix={t('monitoring.unitCount')}
                    accent="text-emerald-600 dark:text-emerald-400"
                />
                <KpiCard
                    icon={AlarmClockCheck}
                    label={t('monitoring.kpiWaiting')}
                    value={kpis.openUnassigned}
                    suffix={t('monitoring.unitCount')}
                    accent="text-amber-600 dark:text-amber-400"
                />
                <KpiCard icon={Timer} label={t('monitoring.kpiAvgResolution')} value={kpis.avgResolutionMinutes} suffix={t('monitoring.unitMinutes')} />
                <KpiCard
                    icon={TrendingUp}
                    label={t('monitoring.kpiSla')}
                    value={kpis.slaCompliancePercent}
                    suffix="%"
                    accent="text-indigo-600 dark:text-indigo-400"
                />
                <KpiCard
                    icon={Star}
                    label={t('monitoring.kpiRating')}
                    value={kpis.avgRating}
                    suffix="/ 5"
                    accent="text-purple-600 dark:text-purple-400"
                />
            </div>

            {/* ZAYAVKALAR DINAMIKASI ---------------------------------------------
                Grafik "Foydalanuvchilar va bo'limlar statistikasi" sahifasidagi
                trend bilan bir xil: bir xil turdagi maydon (AreaChart), bir xil
                rang va bir xil `short_date` o'qi. Ilgari bu yerda hafta kunlari
                kesimidagi ustunli grafik turardi — u vaqt filtri bilan mos
                kelmasdi (davr bir kun ham, bir yil ham bo'lishi mumkin). */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div className={`lg:col-span-2 ${cardClass} p-6 space-y-4`}>
                    <div>
                        <h2 className="text-sm font-bold text-slate-900 dark:text-white">
                            {t('monitoring.trendTitle')}
                        </h2>
                        <p className="text-xs text-slate-500 dark:text-slate-400">
                            {t('monitoring.trendSubtitle')}
                        </p>
                    </div>

                    <ResponsiveContainer width="100%" height={260}>
                        <AreaChart data={trend}>
                            <defs>
                                <linearGradient id="monitoringTrendFill" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="5%" stopColor="#0ea5e9" stopOpacity={0.35} />
                                    <stop offset="95%" stopColor="#0ea5e9" stopOpacity={0} />
                                </linearGradient>
                            </defs>
                            <CartesianGrid strokeDasharray="3 3" stroke="#94a3b8" strokeOpacity={0.2} />
                            <XAxis dataKey="short_date" tick={{ fontSize: 11 }} stroke="#94a3b8" />
                            <YAxis allowDecimals={false} tick={{ fontSize: 11 }} stroke="#94a3b8" width={30} />
                            <Tooltip
                                contentStyle={{ borderRadius: 12, fontSize: 12, fontWeight: 700 }}
                                formatter={(value: any) => [value, t('usersPage.ticketsCount')]}
                            />
                            <Area type="monotone" dataKey="count" stroke="#0ea5e9" strokeWidth={2} fill="url(#monitoringTrendFill)" />
                        </AreaChart>
                    </ResponsiveContainer>
                </div>
                {/* Category donut */}
                <div className={`${cardClass} p-6 flex flex-col`}>
                    <h2 className="text-sm font-bold text-slate-900 dark:text-white mb-0.5">{t('monitoring.categoryTitle')}</h2>
                    <p className="text-xs text-slate-500 dark:text-slate-400 mb-2">{t('monitoring.categorySubtitle')}</p>

                    <div className="flex-1 flex items-center justify-center relative h-48">
                        <ResponsiveContainer width="100%" height="100%">
                            <PieChart>
                                <Pie
                                    data={categoryDistribution}
                                    dataKey="value"
                                    nameKey="name"
                                    innerRadius={58}
                                    outerRadius={80}
                                    paddingAngle={2}
                                    strokeWidth={0}
                                    isAnimationActive={false}
                                >
                                    {categoryDistribution.map((entry) => (
                                        <Cell key={entry.key} fill={entry.color} />
                                    ))}
                                </Pie>
                                <Tooltip
                                    formatter={(value: any, name: any) => [`${value} ${t('monitoring.unitCount')}`, name]}
                                    contentStyle={{ borderRadius: 12, border: '1px solid rgba(148,163,184,0.3)', fontSize: 12 }}
                                />
                            </PieChart>
                        </ResponsiveContainer>
                        <div className="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                            <span className="text-xl font-bold text-slate-900 dark:text-white">{kpis.totalTickets}</span>
                            <span className="text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase">{t('monitoring.total')}</span>
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-2 pt-3 mt-2 border-t border-slate-100 dark:border-slate-800">
                        {categoryDistribution.map((entry) => (
                            <div key={entry.key} className="flex items-center gap-2 text-xs">
                                <span className="w-2.5 h-2.5 rounded-full flex-shrink-0" style={{ backgroundColor: entry.color }} />
                                <span className="text-slate-600 dark:text-slate-300 truncate">
                  {entry.name} <span className="text-slate-400 dark:text-slate-500">{entry.percent}%</span>
                </span>
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            {/* GURUHLAR JADVALI ----------------------------------------------------
                Jadval "Foydalanuvchilar va bo'limlar statistikasi" sahifasidagi
                bo'limlar jadvali bilan bir xil ko'rinishda: bir xil sarlavha
                uslubi, ulush ustuni progress chizig'i bilan va bir xil rangli
                holat "tabletka"lari. Ilgari bu yerda kartochkalar to'plami
                turardi va ikki ekran bir-biriga o'xshamas edi. */}
            <div>
                <h2 className="text-sm font-bold text-slate-900 dark:text-white mb-3 px-1">{t('monitoring.teamTitle')}</h2>

                {teamMetrics.length === 0 ? (
                    <div className={`${cardClass} p-6 text-center text-xs font-semibold text-slate-400 dark:text-slate-500`}>
                        {t('monitoring.noTeams')}
                    </div>
                ) : (
                    <div className={`${cardClass} overflow-hidden`}>
                        <div className="overflow-x-auto">
                            <table className="w-full text-left text-xs">
                                <thead>
                                <tr className="bg-slate-50 dark:bg-slate-900/40 text-slate-400 font-bold uppercase tracking-wider">
                                    <th className="py-3 px-4">{t('monitoring.teamNameColumn')}</th>
                                    <th className="py-3 px-4 text-center">{t('usersPage.ticketsCount')}</th>
                                    <th className="py-3 px-4 text-center">{t('usersPage.share')}</th>
                                    <th className="py-3 px-4 text-center">{t('monitoring.membersColumn')}</th>
                                    <th className="py-3 px-4 text-center">{t('usersPage.statusInProgress')}</th>
                                    <th className="py-3 px-4 text-center">{t('usersPage.statusResolved')}</th>
                                    <th className="py-3 px-4 text-center">{t('usersPage.avgResolution')}</th>
                                    <th className="py-3 px-4 text-center">{t('monitoring.kpiSla')}</th>
                                </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100 dark:divide-slate-800 font-medium text-slate-700 dark:text-slate-200">
                                {teamMetrics.map((team) => {
                                    const share = totalAssigned > 0 ? Math.round((team.assignedCount / totalAssigned) * 100) : 0;
                                    const topMembers = (team.members || []).slice(0, 3);

                                    return (
                                        <tr key={team.teamId} className="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                                            <td className="py-3 px-4">
                                                <div className="font-extrabold text-slate-900 dark:text-slate-100 truncate max-w-[280px]">{team.teamName}</div>
                                                <div className="flex items-center gap-1 mt-1">
                                                    {topMembers.map((mem) => (
                                                        <img
                                                            key={mem.userId}
                                                            src={mem.avatarUrl}
                                                            alt={mem.name}
                                                            title={mem.name + ' — ' + mem.done}
                                                            className="w-5 h-5 rounded-full object-cover"
                                                        />
                                                    ))}
                                                    {topMembers.length === 0 && (
                                                        <span className="text-[11px] text-slate-400">{t('monitoring.noMembers')}</span>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="py-3 px-4 text-center font-black text-brand-600 dark:text-brand-400">{team.assignedCount}</td>
                                            <td className="py-3 px-4">
                                                <div className="flex items-center gap-2">
                                                    <div className="flex-1 h-1.5 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden min-w-[50px]">
                                                        <div className="h-full rounded-full bg-brand-500" style={{ width: `${Math.min(share, 100)}%` }} />
                                                    </div>
                                                    <span className="text-[11px] font-black text-slate-500 dark:text-slate-400 w-10 text-right">{share}%</span>
                                                </div>
                                            </td>
                                            <td className="py-3 px-4 text-center font-bold">{(team.members || []).length}</td>
                                            <td className="py-3 px-4 text-center">
                                                <span className="px-2 py-0.5 rounded-full bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300 font-extrabold">
                                                    {team.inProgressCount}
                                                </span>
                                            </td>
                                            <td className="py-3 px-4 text-center">
                                                <span className="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 font-extrabold">
                                                    {team.completedCount}
                                                </span>
                                            </td>
                                            <td className="py-3 px-4 text-center font-mono text-slate-500 dark:text-slate-400">{formatMinutes(team.avgSpentMinutes)}</td>
                                            <td className="py-3 px-4 text-center">
                                                <span
                                                    className={`px-2 py-0.5 rounded-full font-extrabold ${
                                                        (team.slaPercent ?? 0) >= 95
                                                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                                                            : 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300'
                                                    }`}
                                                >
                                                    {team.slaPercent === null ? '—' : `${team.slaPercent}%`}
                                                </span>
                                            </td>
                                        </tr>
                                    );
                                })}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>

            {/* HOURLY INCIDENT VELOCITY -------------------------------------------- */}
            <div className={`${cardClass} p-6 space-y-4`}>
                <div>
                    <h2 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <Activity className="w-4 h-4 text-indigo-500" />
                        {t('monitoring.hourlyTitle')}
                    </h2>
                    <p className="text-xs text-slate-500 dark:text-slate-400">{t('monitoring.hourlySubtitle')}</p>
                </div>
                <div className="h-56 w-full">
                    <ResponsiveContainer width="100%" height="100%">
                        <AreaChart data={hourlySpikes} margin={{ top: 8, right: 8, left: -16, bottom: 0 }}>
                            <defs>
                                <linearGradient id="hourlyFill" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stopColor="#6366f1" stopOpacity={0.35} />
                                    <stop offset="100%" stopColor="#6366f1" stopOpacity={0} />
                                </linearGradient>
                            </defs>
                            <CartesianGrid strokeDasharray="3 3" vertical={false} className="stroke-slate-200 dark:stroke-slate-800" />
                            <XAxis dataKey="hour" tick={{ fontSize: 11, fill: 'currentColor' }} className="text-slate-500 dark:text-slate-400" axisLine={false} tickLine={false} />
                            <YAxis tick={{ fontSize: 11, fill: 'currentColor' }} className="text-slate-500 dark:text-slate-400" axisLine={false} tickLine={false} width={28} />
                            <Tooltip contentStyle={{ borderRadius: 12, border: '1px solid rgba(148,163,184,0.3)', fontSize: 12 }} />
                            <Area type="monotone" dataKey="count" name={t('monitoring.requests')} stroke="#6366f1" strokeWidth={2} fill="url(#hourlyFill)" />
                        </AreaChart>
                    </ResponsiveContainer>
                </div>
            </div>

            {/* LEADERBOARD + UNASSIGNED QUEUE --------------------------------------- */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* Leaderboard */}
                <div className={`${cardClass} p-6 space-y-4`}>
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <Award className="w-4 h-4 text-amber-500" />
                            <h2 className="text-sm font-bold text-slate-900 dark:text-white">{t('monitoring.topEmployees')}</h2>
                        </div>
                        <span className="text-[11px] font-semibold text-slate-400 dark:text-slate-500">{t('monitoring.top5')}</span>
                    </div>

                    <div className="space-y-2">
                        {topSpecialists.map((spec, idx) => (
                            <Link
                                key={spec.userId}
                                to={`/team-workload?user=${spec.userId}`}
                                title={t('monitoring.viewEmployee')}
                                aria-label={`${spec.name} — ${t('monitoring.viewEmployee')}`}
                                className="-mx-2 flex items-center justify-between gap-3 rounded-xl px-2 py-2 border-b border-slate-100 dark:border-slate-800 last:border-0 transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/60"
                            >
                                <div className="flex items-center gap-3 min-w-0">
                  <span
                      className={`w-6 h-6 rounded-md text-[11px] font-bold flex items-center justify-center flex-shrink-0 ${
                          idx === 0
                              ? 'bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-400'
                              : 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400'
                      }`}
                  >
                    {idx + 1}
                  </span>
                                    <img src={spec.avatarUrl} alt={spec.name} className="w-8 h-8 rounded-full object-cover flex-shrink-0" />
                                    <div className="min-w-0">
                                        <p className="text-xs font-semibold text-slate-800 dark:text-slate-100 truncate">{spec.name}</p>
                                        <p className="text-[11px] text-slate-400 dark:text-slate-500">@{spec.username}</p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-3 flex-shrink-0 text-xs">
                                    <span className="font-semibold text-emerald-600 dark:text-emerald-400">{spec.done} {t('monitoring.unitCount')}</span>
                                    <span className="flex items-center gap-1 text-purple-600 dark:text-purple-400 font-semibold">
                    <Star className="w-3 h-3 fill-current" />
                                        {spec.clientRating}
                  </span>
                                </div>
                            </Link>
                        ))}
                    </div>
                </div>

                {/* Unassigned queue */}
                <div className={`${cardClass} p-6 space-y-4`}>
                    <div className="flex items-center gap-2">
                        <Clock className="w-4 h-4 text-amber-500" />
                        <h2 className="text-sm font-bold text-slate-900 dark:text-white">{t('monitoring.unassignedTitle')}</h2>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        {unassignedQueue.map((ticket) => (
                            <Link
                                key={ticket.id}
                                to={`/task/${ticket.id}`}
                                className="p-3 rounded-xl border border-slate-100 dark:border-slate-800 hover:border-indigo-300 dark:hover:border-indigo-700 transition-colors space-y-1.5"
                            >
                                <div className="flex items-center justify-between">
                                    <span className="text-[11px] font-bold text-indigo-600 dark:text-indigo-400 font-mono">#{ticket.ticketNumber}</span>
                                    <span className="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-400">
                    {ticket.priority}
                  </span>
                                </div>
                                <p className="text-xs font-medium text-slate-700 dark:text-slate-200 truncate" title={ticket.todo}>
                                    {ticket.todo}
                                </p>
                                <div className="flex items-center justify-between text-[10px] text-slate-400 dark:text-slate-500 font-mono">
                                    <span>{ticket.category}</span>
                                    <span>{ticket.createdAt}</span>
                                </div>
                            </Link>
                        ))}
                        {unassignedQueue.length === 0 && (
                            <p className="text-xs text-slate-400 dark:text-slate-500 italic text-center py-6 sm:col-span-2">
                                {t('monitoring.noUnassigned')}
                            </p>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
};

// ============================================================================
// KPI CARD — single reusable component instead of six near-identical blocks
// ============================================================================

const KpiCard: React.FC<{
    icon: React.ComponentType<{ className?: string }>;
    label: string;
    value: number | string;
    suffix?: string;
    accent?: string;
}> = ({ icon: Icon, label, value, suffix, accent = 'text-slate-900 dark:text-white' }) => (
    <div className={`${cardClass} p-4`}>
        <div className="flex items-center justify-between mb-2">
            <span className="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide">{label}</span>
            <Icon className="w-4 h-4 text-slate-300 dark:text-slate-600" />
        </div>
        <div className="flex items-baseline gap-1">
            <span className={`text-2xl font-bold ${accent}`}>{value}</span>
            {suffix && <span className="text-xs font-medium text-slate-400 dark:text-slate-500">{suffix}</span>}
        </div>
    </div>
);
