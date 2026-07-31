import React, { useEffect, useMemo, useState } from 'react';
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
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Legend,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';

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

interface WeeklyGroupPerf {
    day: string;
    key: string;
    hardware: number;
    software: number;
    network: number;
    banking: number;
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
    weeklyGroupPerformance?: WeeklyGroupPerf[];
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

const GROUP_LABELS: Record<GroupKey, string> = {
    software: 'Software',
    hardware: 'Hardware',
    network: 'Network',
    banking: 'Banking',
};

const cardClass =
    'bg-white dark:bg-slate-900/90 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-sm';

export const MonitoringPage: React.FC = () => {
    const [data, setData] = useState<MonitoringData | null>(null);
    const [loading, setLoading] = useState(true);
    const [isTvMode, setIsTvMode] = useState(false);
    const [countdown, setCountdown] = useState(30);
    const [currentTime, setCurrentTime] = useState(new Date().toLocaleTimeString());
    const [selectedGroupFilter, setSelectedGroupFilter] = useState<'all' | GroupKey>('all');

    const fetchMonitoringData = async () => {
        setLoading(true);
        try {
            const res = await axiosClient.get('/tickets/executive-monitoring');
            setData(res.data);
        } catch (e) {
            console.error('Failed to fetch executive monitoring data', e);
        } finally {
            setLoading(false);
            setCountdown(30);
        }
    };

    useEffect(() => {
        const clockTimer = setInterval(() => setCurrentTime(new Date().toLocaleTimeString()), 1000);
        return () => clearInterval(clockTimer);
    }, []);

    useEffect(() => {
        fetchMonitoringData();
        const interval = setInterval(() => {
            setCountdown((prev) => {
                if (prev <= 1) {
                    fetchMonitoringData();
                    return 30;
                }
                return prev - 1;
            });
        }, 1000);
        return () => clearInterval(interval);
    }, []);

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
    const weeklyGroupPerf = data?.weeklyGroupPerformance ?? [];

    const dynamicGroupLabels = useMemo(() => {
        const labels: Record<string, string> = { ...GROUP_LABELS };
        if (data?.teamMetrics && data.teamMetrics.length > 0) {
            const keys: GroupKey[] = ['hardware', 'software', 'network', 'banking'];
            data.teamMetrics.forEach((team, idx) => {
                if (keys[idx]) {
                    labels[keys[idx]] = team.teamName;
                }
            });
        }
        return labels;
    }, [data]);

    // Which group each series belongs to, driving both bar visibility and legend.
    const visibleGroups: GroupKey[] =
        selectedGroupFilter === 'all'
            ? ['software', 'hardware', 'network', 'banking']
            : [selectedGroupFilter];

    const categoryDistribution = useMemo(() => {
        if (data?.categoryDistribution && data.categoryDistribution.length > 0) {
            return data.categoryDistribution;
        }
        const totals: Record<GroupKey, number> = { software: 0, hardware: 0, network: 0, banking: 0 };
        teamMetrics.forEach((team) => {
            const key = (Object.keys(GROUP_COLORS) as GroupKey[]).find((g) =>
                team.teamName.toLowerCase().includes(g),
            );
            if (key) {
                totals[key] += team.assignedCount;
            }
        });
        const sum = Object.values(totals).reduce((a, b) => a + b, 0) || 1;
        return (Object.keys(totals) as GroupKey[]).map((key) => ({
            key,
            name: GROUP_LABELS[key],
            value: totals[key],
            percent: Math.round((totals[key] / sum) * 100),
            color: GROUP_COLORS[key],
        }));
    }, [data, teamMetrics]);

    const bestDay = useMemo(() => {
        if (weeklyGroupPerf.length === 0) return null;
        return weeklyGroupPerf.reduce((best, cur) => {
            const curTotal = cur.software + cur.hardware + cur.network + cur.banking;
            const bestTotal = best.software + best.hardware + best.network + best.banking;
            return curTotal > bestTotal ? cur : best;
        });
    }, [weeklyGroupPerf]);

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
                Jonli monitoring
              </span>
                            <span className="text-xs font-mono text-slate-500 dark:text-slate-400">{currentTime}</span>
                        </div>
                        <h1 className="text-xl font-bold tracking-tight text-slate-900 dark:text-white">
                            Operatsion boshqaruv paneli
                        </h1>
                        <p className="text-xs text-slate-500 dark:text-slate-400">
                            Guruhlar, xodimlar va SLA ko'rsatkichlari — bir joyda
                        </p>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    <button
                        onClick={fetchMonitoringData}
                        className="px-3.5 py-2 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 text-xs font-semibold flex items-center gap-2 transition-colors"
                    >
                        <RefreshCw className={`w-3.5 h-3.5 ${loading ? 'animate-spin' : ''}`} />
                        <span>{countdown}s</span>
                    </button>
                    <button
                        onClick={toggleFullscreen}
                        className="p-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white transition-colors"
                        title="TV rejimi"
                    >
                        {isTvMode ? <Minimize className="w-4 h-4" /> : <Maximize className="w-4 h-4" />}
                    </button>
                </div>
            </div>

            {/* KPI ROW ------------------------------------------------------------ */}
            <div className="grid grid-cols-2 lg:grid-cols-6 gap-4">
                <KpiCard icon={BarChart3} label="Jami murojaat" value={kpis.totalTickets} suffix="ta" />
                <KpiCard
                    icon={CheckCircle2}
                    label="Bugun yopilgan"
                    value={kpis.todayCompleted}
                    suffix="ta"
                    accent="text-emerald-600 dark:text-emerald-400"
                />
                <KpiCard
                    icon={AlarmClockCheck}
                    label="Kutayotgan"
                    value={kpis.openUnassigned}
                    suffix="ta"
                    accent="text-amber-600 dark:text-amber-400"
                />
                <KpiCard icon={Timer} label="O'rtacha bajarish" value={kpis.avgResolutionMinutes} suffix="daq" />
                <KpiCard
                    icon={TrendingUp}
                    label="SLA bajarilish"
                    value={kpis.slaCompliancePercent}
                    suffix="%"
                    accent="text-indigo-600 dark:text-indigo-400"
                />
                <KpiCard
                    icon={Star}
                    label="Mijoz bahosi"
                    value={kpis.avgRating}
                    suffix="/ 5"
                    accent="text-purple-600 dark:text-purple-400"
                />
            </div>

            {/* WEEKLY PERFORMANCE + CATEGORY DONUT --------------------------------- */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {/* Weekly grouped bar chart */}
                <div className={`lg:col-span-2 ${cardClass} p-6 space-y-4`}>
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 className="text-sm font-bold text-slate-900 dark:text-white">
                                Haftalik guruh unumdorligi
                            </h2>
                            <p className="text-xs text-slate-500 dark:text-slate-400">
                                Kunlar kesimida yopilgan zayavkalar soni
                                {bestDay && (
                                    <span className="text-emerald-600 dark:text-emerald-400 font-semibold">
                    {' '}
                                        · eng yaxshi kun: {bestDay.day}
                  </span>
                                )}
                            </p>
                        </div>
                        <div className="flex items-center bg-slate-100 dark:bg-slate-800 p-1 rounded-lg text-xs font-semibold">
                            {(['all', 'software', 'hardware', 'network', 'banking'] as const).map((g) => (
                                <button
                                    key={g}
                                    onClick={() => setSelectedGroupFilter(g)}
                                    className={`px-2.5 py-1.5 rounded-md transition-colors ${
                                        selectedGroupFilter === g
                                            ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm'
                                            : 'text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-slate-200'
                                    }`}
                                >
                                    {g === 'all' ? 'Barchasi' : (dynamicGroupLabels[g] || GROUP_LABELS[g])}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="h-72 w-full">
                        <ResponsiveContainer width="100%" height="100%">
                            <BarChart data={weeklyGroupPerf} barGap={4} margin={{ top: 8, right: 8, left: -16, bottom: 0 }}>
                                <CartesianGrid strokeDasharray="3 3" vertical={false} className="stroke-slate-200 dark:stroke-slate-800" />
                                <XAxis dataKey="day" tick={{ fontSize: 11, fill: 'currentColor' }} className="text-slate-500 dark:text-slate-400" axisLine={false} tickLine={false} />
                                <YAxis tick={{ fontSize: 11, fill: 'currentColor' }} className="text-slate-500 dark:text-slate-400" axisLine={false} tickLine={false} width={28} />
                                <Tooltip
                                    contentStyle={{
                                        borderRadius: 12,
                                        border: '1px solid rgba(148,163,184,0.3)',
                                        fontSize: 12,
                                    }}
                                />
                                <Legend
                                    formatter={(value: any) => <span className="text-xs text-slate-600 dark:text-slate-300">{dynamicGroupLabels[value] || value}</span>}
                                    iconType="circle"
                                    iconSize={8}
                                />
                                {visibleGroups.map((g) => (
                                    <Bar key={g} dataKey={g} name={dynamicGroupLabels[g] || GROUP_LABELS[g]} fill={GROUP_COLORS[g]} radius={[4, 4, 0, 0]} maxBarSize={28} />
                                ))}
                            </BarChart>
                        </ResponsiveContainer>
                    </div>
                </div>

                {/* Category donut */}
                <div className={`${cardClass} p-6 flex flex-col`}>
                    <h2 className="text-sm font-bold text-slate-900 dark:text-white mb-0.5">Kategoriyalar ulushi</h2>
                    <p className="text-xs text-slate-500 dark:text-slate-400 mb-2">Muammolar turi taqsimoti</p>

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
                                >
                                    {categoryDistribution.map((entry) => (
                                        <Cell key={entry.key} fill={entry.color} />
                                    ))}
                                </Pie>
                                <Tooltip
                                    formatter={(value: any, name: any) => [`${value} ta`, name]}
                                    contentStyle={{ borderRadius: 12, border: '1px solid rgba(148,163,184,0.3)', fontSize: 12 }}
                                />
                            </PieChart>
                        </ResponsiveContainer>
                        <div className="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                            <span className="text-xl font-bold text-slate-900 dark:text-white">{kpis.totalTickets}</span>
                            <span className="text-[10px] font-semibold text-slate-500 dark:text-slate-400 uppercase">jami</span>
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

            {/* TEAM PERFORMANCE CARDS ---------------------------------------------- */}
            <div>
                <h2 className="text-sm font-bold text-slate-900 dark:text-white mb-3 px-1">Guruhlar bo'yicha unumdorlik</h2>
                <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                    {teamMetrics.map((team) => {
                        const maxMemberDone = Math.max(...(team.members || []).map((m) => m.done), 1);
                        const isHighSla = team.slaPercent >= 95;

                        return (
                            <div key={team.teamId} className={`${cardClass} p-5 space-y-4`}>
                                <div className="flex items-start justify-between">
                                    <div>
                                        <h3 className="font-bold text-sm text-slate-900 dark:text-white">{team.teamName}</h3>
                                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                            {team.assignedCount} ta / <span className="text-emerald-600 dark:text-emerald-400 font-semibold">{team.completedCount} yopilgan</span>
                                        </p>
                                    </div>
                                    <span
                                        className={`px-2 py-0.5 rounded-md text-[11px] font-bold ${
                                            isHighSla
                                                ? 'bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-400'
                                                : 'bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-400'
                                        }`}
                                    >
                    SLA {team.slaPercent}%
                  </span>
                                </div>

                                <div className="space-y-2.5">
                                    {(team.members || []).map((mem, idx) => (
                                        <div key={mem.userId} className="flex items-center gap-2.5">
                                            <img src={mem.avatarUrl} alt={mem.name} className="w-7 h-7 rounded-full object-cover flex-shrink-0" />
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-center justify-between text-xs mb-1">
                          <span className="font-semibold text-slate-700 dark:text-slate-200 truncate">
                            {mem.name}
                              {idx === 0 && <span className="ml-1 text-amber-500">★</span>}
                          </span>
                                                    <span className="text-slate-400 dark:text-slate-500 font-mono text-[11px]">{mem.done}</span>
                                                </div>
                                                <div className="w-full h-1.5 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                                                    <div
                                                        className="h-full rounded-full bg-indigo-500"
                                                        style={{ width: `${Math.max((mem.done / maxMemberDone) * 100, 8)}%` }}
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                    {(!team.members || team.members.length === 0) && (
                                        <p className="text-xs text-slate-400 dark:text-slate-500 italic text-center py-2">
                                            Xodimlar tayinlanmagan
                                        </p>
                                    )}
                                </div>

                                <div className="flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400 pt-3 border-t border-slate-100 dark:border-slate-800">
                                    <span>Jarayonda: <strong className="text-slate-700 dark:text-slate-300">{team.inProgressCount}</strong></span>
                                    <span>O'rtacha: <strong className="text-slate-700 dark:text-slate-300">{team.avgSpentMinutes} daq</strong></span>
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>

            {/* HOURLY INCIDENT VELOCITY -------------------------------------------- */}
            <div className={`${cardClass} p-6 space-y-4`}>
                <div>
                    <h2 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <Activity className="w-4 h-4 text-indigo-500" />
                        Soatlik murojaat intensivligi
                    </h2>
                    <p className="text-xs text-slate-500 dark:text-slate-400">09:00–18:00 oralig'ida tushgan murojaatlar</p>
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
                            <Area type="monotone" dataKey="count" name="Murojaatlar" stroke="#6366f1" strokeWidth={2} fill="url(#hourlyFill)" />
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
                            <h2 className="text-sm font-bold text-slate-900 dark:text-white">TOP xodimlar</h2>
                        </div>
                        <span className="text-[11px] font-semibold text-slate-400 dark:text-slate-500">TOP 5</span>
                    </div>

                    <div className="space-y-2">
                        {topSpecialists.map((spec, idx) => (
                            <div key={spec.userId} className="flex items-center justify-between gap-3 py-2 border-b border-slate-100 dark:border-slate-800 last:border-0">
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
                                    <span className="font-semibold text-emerald-600 dark:text-emerald-400">{spec.done} ta</span>
                                    <span className="flex items-center gap-1 text-purple-600 dark:text-purple-400 font-semibold">
                    <Star className="w-3 h-3 fill-current" />
                                        {spec.clientRating}
                  </span>
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                {/* Unassigned queue */}
                <div className={`${cardClass} p-6 space-y-4`}>
                    <div className="flex items-center gap-2">
                        <Clock className="w-4 h-4 text-amber-500" />
                        <h2 className="text-sm font-bold text-slate-900 dark:text-white">Biriktirilmagan navbat</h2>
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
                                Biriktirilmagan zayavkalar yo'q
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
