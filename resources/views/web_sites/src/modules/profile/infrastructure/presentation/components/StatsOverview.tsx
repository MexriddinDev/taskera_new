import React, { useState, useEffect } from 'react';
import { CheckCircle2, Clock, Calendar, TrendingUp, Star, Repeat, BarChart3, Timer, Activity, Filter, ShieldCheck, Zap } from 'lucide-react';
import { AreaChart, Area, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { useCan } from '@/shared/presentation/hooks/useCan';

interface DailyTrendItem {
  date: string;
  dayName: string;
  shortDay: string;
  count: number;
}

interface ReassignmentLog {
  id: number;
  ticket_id: number;
  ticket_no: string;
  subject: string;
  from_username: string;
  to_username: string;
  reason: string;
  created_at: string;
}

interface StatsData {
  total: number;
  completed: number;
  rejected: number;
  open: number;
  inProgress: number;
  myTasks: number;
  todayCompleted: number;
  avgSpentMinutes?: number;
  avgTotalResolutionMinutes?: number;
  avgExecutionMinutes?: number;
  avgRating?: number | null;
  ratingCount?: number;
  ratingDistribution?: { star: number; count: number }[];
  speedBreakdown?: { under15: number; from15to30: number; from30to60: number; over60: number; total: number };
  dailyTrend?: DailyTrendItem[];
  peakDay?: string;
  maxClosedCount?: number;
}

export const StatsOverview: React.FC = () => {
  const [stats, setStats] = useState<StatsData | null>(null);
  const [reassignments, setReassignments] = useState<ReassignmentLog[]>([]);
  const [loading, setLoading] = useState(true);

  // Date Range Filter state
  const [activeRange, setActiveRange] = useState<'today' | 'week' | 'month' | 'all'>('month');
  const [startDate, setStartDate] = useState('');
  const [endDate, setEndDate] = useState('');

  const { user } = useCan();
  const isSuperAdmin = user?.role === 'Super Admin' || user?.username === 'admin' || user?.username === 'superadmin';

  const fetchStats = (range: 'today' | 'week' | 'month' | 'all' = activeRange) => {
    setLoading(true);
    axiosClient
      .get('/tickets/stats', { params: { period: range, range } })
      .then((res) => {
        if (res.data) setStats(res.data);
      })
      .catch(() => {})
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    fetchStats(activeRange);

    if (isSuperAdmin) {
      axiosClient
        .get('/tickets/monitoring')
        .then((res) => {
          if (res.data?.reassignments) {
            setReassignments(res.data.reassignments);
          }
        })
        .catch(() => {});
    }
  }, [activeRange, isSuperAdmin]);

  const handleRangeChange = (range: 'today' | 'week' | 'month' | 'all') => {
    setActiveRange(range);
    fetchStats(range);
  };

  const totalCompleted = stats?.completed ?? 0;
  const todayCompleted = stats?.todayCompleted ?? 0;
  const myTasks = stats?.myTasks ?? 0;
  const avgSpentMinutes = stats?.avgSpentMinutes ?? 1;
  const avgRating = stats?.avgRating ?? null;
  const ratingCount = stats?.ratingCount ?? 0;
  const ratingDistribution = stats?.ratingDistribution ?? [];
  const speed = stats?.speedBreakdown ?? null;
  const speedTotal = speed?.total ?? 0;
  const dailyTrend = stats?.dailyTrend || [];
  const maxCount = stats?.maxClosedCount || Math.max(...dailyTrend.map((d) => d.count), 1);
  const avgPerDay = dailyTrend.length > 0 ? Math.round(totalCompleted / dailyTrend.length) : 0;

  if (loading) {
    return (
      <div className="w-full p-16 text-center text-xs font-extrabold text-slate-400 animate-pulse space-y-3">
        <BarChart3 className="w-8 h-8 mx-auto text-brand-500 animate-bounce" />
        <p>Statistika va ko'rsatkichlar yuklanmoqda...</p>
      </div>
    );
  }

  // Chart data — same daily trend items fed into recharts
  const chartData = dailyTrend;

  return (
    <div className="w-full space-y-6">
      {/* 1. Date Range Filter Bar (Replaces bright purple banner) */}
      <div className="bg-white dark:bg-slate-800/90 rounded-3xl p-5 border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div className="flex items-center space-x-2">
          <Filter className="w-5 h-5 text-brand-500" />
          <h2 className="text-sm font-black text-slate-900 dark:text-slate-100">Vaqt Oralig'i Bo'yicha Analitika Filtri</h2>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          <button
            onClick={() => handleRangeChange('today')}
            className={`px-4 py-2 rounded-2xl text-xs font-extrabold transition-all cursor-pointer ${
              activeRange === 'today'
                ? 'bg-brand-500 text-white shadow-md shadow-brand-500/20'
                : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200'
            }`}
          >
            Bugun
          </button>
          <button
            onClick={() => handleRangeChange('week')}
            className={`px-4 py-2 rounded-2xl text-xs font-extrabold transition-all cursor-pointer ${
              activeRange === 'week'
                ? 'bg-brand-500 text-white shadow-md shadow-brand-500/20'
                : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200'
            }`}
          >
            Shu hafta
          </button>
          <button
            onClick={() => handleRangeChange('month')}
            className={`px-4 py-2 rounded-2xl text-xs font-extrabold transition-all cursor-pointer ${
              activeRange === 'month'
                ? 'bg-brand-500 text-white shadow-md shadow-brand-500/20'
                : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200'
            }`}
          >
            Shu oy
          </button>
          <button
            onClick={() => handleRangeChange('all')}
            className={`px-4 py-2 rounded-2xl text-xs font-extrabold transition-all cursor-pointer ${
              activeRange === 'all'
                ? 'bg-brand-500 text-white shadow-md shadow-brand-500/20'
                : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200'
            }`}
          >
            Barchasi
          </button>
        </div>
      </div>

      {/* 2. Key Personal Metric Cards */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {/* Shaxsiy Yopilganlar */}
        <div className="bg-white dark:bg-slate-800/90 rounded-3xl p-5 border border-slate-200 dark:border-slate-700 shadow-sm hover:shadow-md transition-all">
          <div className="flex items-center justify-between mb-3">
            <span className="text-xs font-extrabold text-slate-500 dark:text-slate-400">Jami Yopilgan Zayavkalaringiz</span>
            <div className="p-2.5 rounded-2xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800">
              <CheckCircle2 className="w-5 h-5" />
            </div>
          </div>
          <div className="flex items-baseline space-x-1.5">
            <span className="text-3xl font-black text-slate-900 dark:text-slate-100">{totalCompleted}</span>
            <span className="text-xs font-bold text-slate-400">ta zayavka</span>
          </div>
        </div>

        {/* Bugun Yopilganlar */}
        <div className="bg-white dark:bg-slate-800/90 rounded-3xl p-5 border border-slate-200 dark:border-slate-700 shadow-sm hover:shadow-md transition-all">
          <div className="flex items-center justify-between mb-3">
            <span className="text-xs font-extrabold text-slate-500 dark:text-slate-400">Bugungi Yopilganlar</span>
            <div className="p-2.5 rounded-2xl bg-brand-50 text-brand-500 dark:bg-brand-950/40 border border-brand-200 dark:border-brand-800">
              <Calendar className="w-5 h-5" />
            </div>
          </div>
          <div className="flex items-baseline space-x-1.5">
            <span className="text-3xl font-black text-brand-500">{todayCompleted}</span>
            <span className="text-xs font-bold text-slate-400">ta bugun</span>
          </div>
        </div>

        {/* 1. Umumiy Yechim Berish Vaqti (Yuborilgandan Yopilguncha) */}
        <div className="bg-white dark:bg-slate-800/90 rounded-3xl p-5 border border-slate-200 dark:border-slate-700 shadow-sm hover:shadow-md transition-all">
          <div className="flex items-center justify-between mb-3">
            <span className="text-xs font-extrabold text-slate-500 dark:text-slate-400">Umumiy Yechim Vaqti</span>
            <div className="p-2.5 rounded-2xl bg-amber-50 text-amber-500 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800">
              <Timer className="w-5 h-5" />
            </div>
          </div>
          <div className="flex items-baseline space-x-1.5">
            <span className="text-3xl font-black text-slate-900 dark:text-slate-100">{stats?.avgTotalResolutionMinutes ?? stats?.avgSpentMinutes ?? 35}</span>
            <span className="text-xs font-bold text-slate-400">daqiqa / yuborilgandan</span>
          </div>
        </div>

        {/* 2. Jarayondan Bajarilguncha Vaqt (Jarayonga o'tgandan yopilguncha) */}
        <div className="bg-white dark:bg-slate-800/90 rounded-3xl p-5 border border-slate-200 dark:border-slate-700 shadow-sm hover:shadow-md transition-all">
          <div className="flex items-center justify-between mb-3">
            <span className="text-xs font-extrabold text-slate-500 dark:text-slate-400">Jarayonda Bajarish Vaqti</span>
            <div className="p-2.5 rounded-2xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800">
              <Clock className="w-5 h-5" />
            </div>
          </div>
          <div className="flex items-baseline space-x-1.5">
            <span className="text-3xl font-black text-emerald-600 dark:text-emerald-400">{stats?.avgExecutionMinutes ?? 18}</span>
            <span className="text-xs font-bold text-slate-400">daqiqa / jarayondan</span>
          </div>
        </div>
      </div>

      {/* 3. MULTI-CHART ANALYTICS 1: Daily completion trend */}
      <div className="bg-white dark:bg-slate-800/90 rounded-3xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-700 pb-4">
          <div>
            <h3 className="text-base font-black text-slate-900 dark:text-slate-100 flex items-center space-x-2">
              <Activity className="w-5 h-5 text-brand-500" />
              <span>Kunlar Kesimida Bajarilgan Zayavkalaringiz Dinamikasi</span>
            </h3>
            <p className="text-xs text-slate-500 font-medium mt-0.5">
              Tanlangan vaqt oralig'ida yopilgan zayavkalar va mahsuldorlik grafigi.
            </p>
          </div>
          <div className="flex items-center space-x-2 text-xs font-extrabold text-emerald-600 dark:text-emerald-400">
            <span className="w-3 h-3 rounded-full bg-emerald-500 inline-block" />
            <span>Yopilgan zayavkalar hajmi</span>
          </div>
        </div>

        {/* Interactive recharts area chart */}
        <div className="h-72 w-full">
          {chartData.length === 0 ? (
            <div className="text-center py-16 text-xs text-slate-400 italic">
              Tanlangan vaqt oralig'ida zayavkalar ma'lumoti topilmadi
            </div>
          ) : (
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart data={chartData} margin={{ top: 12, right: 8, left: -18, bottom: 0 }}>
                <defs>
                  <linearGradient id="trendFill" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stopColor="#10B981" stopOpacity={0.4} />
                    <stop offset="100%" stopColor="#10B981" stopOpacity={0} />
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" vertical={false} className="stroke-slate-200 dark:stroke-slate-700" />
                <XAxis
                  dataKey="shortDay"
                  tick={{ fontSize: 10, fill: 'currentColor' }}
                  className="text-slate-500 dark:text-slate-400"
                  axisLine={false}
                  tickLine={false}
                  minTickGap={20}
                />
                <YAxis
                  tick={{ fontSize: 10, fill: 'currentColor' }}
                  className="text-slate-500 dark:text-slate-400"
                  axisLine={false}
                  tickLine={false}
                  width={30}
                  allowDecimals={false}
                />
                <Tooltip
                  contentStyle={{ borderRadius: 12, border: '1px solid rgba(148,163,184,0.3)', fontSize: 12 }}
                  labelFormatter={(label: any, payload: any) => payload?.[0]?.payload?.dayName ?? label}
                  formatter={(value: any) => [`${value} ta`, 'Yopilgan']}
                />
                <Area
                  type="monotone"
                  dataKey="count"
                  stroke="#10B981"
                  strokeWidth={2.5}
                  fill="url(#trendFill)"
                  dot={(props: any) => {
                    const isPeak = props.payload?.count === maxCount && maxCount > 0;
                    return (
                      <circle
                        key={props.key}
                        cx={props.cx}
                        cy={props.cy}
                        r={isPeak ? 5 : 3}
                        fill={isPeak ? '#F59E0B' : '#10B981'}
                        stroke="#ffffff"
                        strokeWidth={1.5}
                      />
                    );
                  }}
                  activeDot={{ r: 6, strokeWidth: 2 }}
                />
              </AreaChart>
            </ResponsiveContainer>
          )}
        </div>

        {/* Period summary chips */}
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-4 border-t border-slate-100 dark:border-slate-700">
          <div className="rounded-2xl bg-slate-50 dark:bg-slate-900/50 px-4 py-3">
            <p className="text-[10px] font-extrabold text-slate-400 uppercase tracking-wide">Jami yopilgan</p>
            <p className="text-lg font-black text-slate-900 dark:text-slate-100">{totalCompleted} ta</p>
          </div>
          <div className="rounded-2xl bg-slate-50 dark:bg-slate-900/50 px-4 py-3">
            <p className="text-[10px] font-extrabold text-slate-400 uppercase tracking-wide">O'rtacha / kun</p>
            <p className="text-lg font-black text-brand-500">{avgPerDay} ta</p>
          </div>
          <div className="rounded-2xl bg-slate-50 dark:bg-slate-900/50 px-4 py-3">
            <p className="text-[10px] font-extrabold text-slate-400 uppercase tracking-wide">Eng yuqori kun</p>
            <p className="text-lg font-black text-amber-500">{maxCount} ta</p>
          </div>
          <div className="rounded-2xl bg-slate-50 dark:bg-slate-900/50 px-4 py-3">
            <p className="text-[10px] font-extrabold text-slate-400 uppercase tracking-wide">Eng yaxshi kun</p>
            <p className="text-sm font-black text-emerald-600 dark:text-emerald-400 truncate" title={stats?.peakDay || ''}>
              {stats?.peakDay || '—'}
            </p>
          </div>
        </div>
      </div>

      {/* 4. MULTI-CHART ANALYTICS 2: Resolution Speed Intervals Breakdown & Rating Distribution */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        {/* Speed Breakdown Chart */}
        <div className="bg-white dark:bg-slate-800/90 rounded-3xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
          <div className="flex items-center space-x-2 border-b border-slate-100 dark:border-slate-700 pb-3">
            <Timer className="w-5 h-5 text-amber-500" />
            <h4 className="text-sm font-black text-slate-900 dark:text-slate-100">Bajarish Vaqti Oraliqlari (Tezlik Taqsimoti)</h4>
          </div>

          {speedTotal === 0 ? (
            <p className="text-xs text-slate-400 italic text-center py-8">
              Bu davrda yopilgan zayavkalar bo'yicha ma'lumot yo'q
            </p>
          ) : (
            <div className="space-y-3 text-xs font-semibold">
              {[
                { key: 'under15', label: '15 daqiqagacha (Tezkor)', bar: 'bg-emerald-500', text: 'text-emerald-600 dark:text-emerald-400' },
                { key: 'from15to30', label: '15-30 daqiqa (Standart)', bar: 'bg-brand-500', text: 'text-brand-500' },
                { key: 'from30to60', label: "30-60 daqiqa (O'rtacha)", bar: 'bg-amber-500', text: 'text-amber-500' },
                { key: 'over60', label: '60 daqiqadan ortiq (Sekin)', bar: 'bg-rose-500', text: 'text-rose-500' },
              ].map((seg) => {
                const count = speed?.[seg.key as keyof NonNullable<typeof speed>] ?? 0;
                const percent = speedTotal > 0 ? Math.round((count / speedTotal) * 100) : 0;
                return (
                  <div key={seg.key} className="space-y-1">
                    <div className="flex justify-between">
                      <span className="text-slate-600 dark:text-slate-300 font-bold">{seg.label}</span>
                      <span className={`font-extrabold ${seg.text}`}>{count} ta · {percent}%</span>
                    </div>
                    <div className="w-full h-3 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
                      <div className={`h-full ${seg.bar} rounded-full`} style={{ width: `${Math.max(percent, count > 0 ? 4 : 0)}%` }} />
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>

        {/* Service Quality: Average rating stars + real distribution */}
        <div className="bg-white dark:bg-slate-800/90 rounded-3xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
          <div className="flex items-center space-x-2 border-b border-slate-100 dark:border-slate-700 pb-3">
            <Star className="w-5 h-5 text-purple-500 fill-purple-400" />
            <h4 className="text-sm font-black text-slate-900 dark:text-slate-100">Mijozlar Bahosi (O'rtacha)</h4>
          </div>

          {ratingCount === 0 ? (
            <div className="text-center py-8 space-y-2">
              <div className="flex items-center justify-center gap-0.5">
                {[1, 2, 3, 4, 5].map((s) => (
                  <Star key={s} className="w-5 h-5 text-slate-300 dark:text-slate-600" />
                ))}
              </div>
              <p className="text-xs text-slate-400 italic">Hali baho berilmagan</p>
            </div>
          ) : (
            <>
              <div className="flex items-center gap-5">
                <div className="flex items-baseline gap-1">
                  <span className="text-4xl font-black text-slate-900 dark:text-slate-100">{avgRating ?? '—'}</span>
                  <span className="text-sm font-bold text-slate-400">/ 5</span>
                </div>
                <div>
                  <div className="relative inline-block overflow-hidden rounded-sm">
                    <div className="flex gap-0.5">
                      {[1, 2, 3, 4, 5].map((s) => (
                        <Star key={s} className="w-5 h-5 text-slate-300 dark:text-slate-600" />
                      ))}
                    </div>
                    <div
                      className="absolute inset-y-0 left-0 flex gap-0.5 overflow-hidden"
                      style={{ width: `${Math.min((avgRating ?? 0) / 5, 1) * 100}%` }}
                    >
                      {[1, 2, 3, 4, 5].map((s) => (
                        <Star key={s} className="w-5 h-5 shrink-0 fill-amber-400 text-amber-400" />
                      ))}
                    </div>
                  </div>
                  <p className="text-[11px] font-bold text-slate-400 mt-1">{ratingCount} ta baho</p>
                </div>
              </div>

              <div className="space-y-2.5 pt-4 border-t border-slate-100 dark:border-slate-700">
                {ratingDistribution.map((r) => {
                  const percent = ratingCount > 0 ? Math.round((r.count / ratingCount) * 100) : 0;
                  return (
                    <div key={r.star} className="flex items-center gap-3">
                      <span className="w-9 text-xs font-extrabold text-slate-600 dark:text-slate-300 flex items-center gap-0.5">
                        {r.star}
                        <Star className="w-3 h-3 fill-amber-400 text-amber-400" />
                      </span>
                      <div className="flex-1 h-2.5 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
                        <div className="h-full bg-amber-400 rounded-full" style={{ width: `${percent}%` }} />
                      </div>
                      <span className="w-20 text-right text-[11px] font-bold text-slate-500 dark:text-slate-400">
                        {r.count} ta · {percent}%
                      </span>
                    </div>
                  );
                })}
              </div>
            </>
          )}
        </div>
      </div>

      {/* 5. Audit Log Table for Superadmin */}
      {isSuperAdmin && reassignments.length > 0 && (
        <div className="bg-white dark:bg-slate-800/90 rounded-3xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
          <div className="flex items-center justify-between border-b border-slate-100 dark:border-slate-700 pb-4">
            <div className="flex items-center space-x-2">
              <Repeat className="w-5 h-5 text-amber-500" />
              <h3 className="text-base font-black text-slate-900 dark:text-slate-100">
                O'zlashtirishlar Hisoboti (Kim kimning zayafkasini olgan)
              </h3>
            </div>
            <span className="text-xs font-bold text-slate-400">Superadmin Logi</span>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead>
                <tr className="border-b border-slate-200 dark:border-slate-700 text-slate-400 font-bold uppercase tracking-wider">
                  <th className="pb-3 px-3">Zayavka #</th>
                  <th className="pb-3 px-3">Muammo</th>
                  <th className="pb-3 px-3 text-center">Kimdan olindi</th>
                  <th className="pb-3 px-3 text-center">Kim o'zlashtirdi</th>
                  <th className="pb-3 px-3 text-right">Sana / Vaqt</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-700/60 font-medium text-slate-700 dark:text-slate-200">
                {reassignments.map((log) => (
                  <tr key={log.id} className="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                    <td className="py-3 px-3 font-extrabold text-brand-600 dark:text-brand-400">{log.ticket_no}</td>
                    <td className="py-3 px-3 font-bold truncate max-w-xs">{log.subject}</td>
                    <td className="py-3 px-3 text-center">
                      <span className="px-2 py-0.5 rounded-full bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300 font-extrabold">
                        {log.from_username || 'Biriktirilmagan'}
                      </span>
                    </td>
                    <td className="py-3 px-3 text-center">
                      <span className="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 font-extrabold">
                        {log.to_username}
                      </span>
                    </td>
                    <td className="py-3 px-3 text-right text-slate-400 font-mono">
                      {log.created_at ? new Date(log.created_at).toLocaleString('uz-UZ') : '—'}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  );
};
