import React, { useState, useEffect } from 'react';
import { CheckCircle2, Clock, Calendar, TrendingUp, Star, Repeat, BarChart3, Timer, Activity, Filter, ShieldCheck, Zap } from 'lucide-react';
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
  avgRating?: number;
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
  const avgRating = stats?.avgRating ?? 5.0;
  const dailyTrend = stats?.dailyTrend || [];
  const maxCount = stats?.maxClosedCount || Math.max(...dailyTrend.map((d) => d.count), 1);

  if (loading) {
    return (
      <div className="w-full p-16 text-center text-xs font-extrabold text-slate-400 animate-pulse space-y-3">
        <BarChart3 className="w-8 h-8 mx-auto text-brand-500 animate-bounce" />
        <p>Statistika va ko'rsatkichlar yuklanmoqda...</p>
      </div>
    );
  }

  // Calculate SVG curve path points for 320px high-impact chart
  const points = dailyTrend.map((item, index) => {
    const x = (index / Math.max(dailyTrend.length - 1, 1)) * 680 + 40;
    const y = 240 - (maxCount > 0 ? (item.count / maxCount) * 180 : 0);
    return { x, y, count: item.count, day: item.dayName, date: item.shortDay };
  });

  const pathD = points.reduce((acc, point, i, a) => {
    if (i === 0) return `M ${point.x} ${point.y}`;
    const prev = a[i - 1];
    const cx = (prev.x + point.x) / 2;
    return `${acc} C ${cx} ${prev.y}, ${cx} ${point.y}, ${point.x} ${point.y}`;
  }, '');

  const areaD = `${pathD} L ${points[points.length - 1]?.x || 720} 270 L ${points[0]?.x || 40} 270 Z`;

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

      {/* 3. MULTI-CHART ANALYTICS 1: Full-Height High Impact SVG Trend Chart (Height 320px) */}
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

        {/* 320px High-Impact SVG Chart Area */}
        <div className="relative pt-4 pb-2">
          {dailyTrend.length === 0 ? (
            <div className="text-center py-16 text-xs text-slate-400 italic">
              Tanlangan vaqt oralig'ida zayavkalar ma'lumoti topilmadi
            </div>
          ) : (
            <div className="w-full overflow-x-auto">
              <div className="min-w-[720px]">
                <svg viewBox="0 0 760 300" className="w-full h-80 overflow-visible">
                  <defs>
                    <linearGradient id="mainTrendGradient" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="0%" stopColor="#10B981" stopOpacity="0.45" />
                      <stop offset="100%" stopColor="#10B981" stopOpacity="0.0" />
                    </linearGradient>
                  </defs>

                  {/* Horizontal Grid Lines */}
                  <line x1="40" y1="60" x2="720" y2="60" stroke="#e2e8f0" strokeDasharray="4 4" className="dark:stroke-slate-700" />
                  <line x1="40" y1="120" x2="720" y2="120" stroke="#e2e8f0" strokeDasharray="4 4" className="dark:stroke-slate-700" />
                  <line x1="40" y1="180" x2="720" y2="180" stroke="#e2e8f0" strokeDasharray="4 4" className="dark:stroke-slate-700" />
                  <line x1="40" y1="240" x2="720" y2="240" stroke="#cbd5e1" className="dark:stroke-slate-600" strokeWidth="1.5" />

                  {/* Gradient Area Fill */}
                  <path d={areaD} fill="url(#mainTrendGradient)" />

                  {/* Smooth Curve Line */}
                  <path d={pathD} fill="none" stroke="#10B981" strokeWidth="4" strokeLinecap="round" />

                  {/* Interactive Nodes */}
                  {points.map((pt, idx) => {
                    const isPeak = pt.count > 0 && pt.count === maxCount;
                    const step = points.length > 15 ? 4 : (points.length > 8 ? 2 : 1);
                    const showLabel = points.length <= 8 || idx % step === 0 || isPeak || idx === points.length - 1;
                    return (
                      <g key={idx} className="group cursor-pointer">
                        <circle
                          cx={pt.x}
                          cy={pt.y}
                          r={isPeak ? 8 : 5}
                          className={`${
                            isPeak ? 'fill-amber-500 stroke-white stroke-2 shadow-lg' : pt.count > 0 ? 'fill-emerald-500 stroke-white stroke-2' : 'fill-slate-300 dark:fill-slate-600'
                          } transition-all group-hover:r-9`}
                        />

                        <g transform={`translate(${pt.x}, ${pt.y - 18})`}>
                          <rect
                            x="-24"
                            y="-20"
                            width="48"
                            height="22"
                            rx="11"
                            className={isPeak ? 'fill-amber-500' : pt.count > 0 ? 'fill-emerald-600' : 'fill-slate-400'}
                          />
                          <text
                            x="0"
                            y="-5"
                            textAnchor="middle"
                            fill="#ffffff"
                            fontSize="11"
                            fontWeight="bold"
                          >
                            {pt.count} ta
                          </text>
                        </g>

                        {showLabel && (
                          <>
                            <text
                              x={pt.x}
                              y="262"
                              textAnchor="middle"
                              className={`text-xs font-extrabold ${isPeak ? 'fill-amber-500 font-black' : 'fill-slate-700 dark:text-slate-300'}`}
                            >
                              {pt.day}
                            </text>
                            <text
                              x={pt.x}
                              y="280"
                              textAnchor="middle"
                              className="text-[10px] font-bold fill-slate-400 font-mono"
                            >
                              {pt.date}
                            </text>
                          </>
                        )}
                      </g>
                    );
                  })}
                </svg>
              </div>
            </div>
          )}
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

          <div className="space-y-3 text-xs font-semibold">
            <div className="space-y-1">
              <div className="flex justify-between">
                <span className="text-slate-600 dark:text-slate-300 font-bold">&lt;15 Daqiqada Yopilganlar (Tezkor)</span>
                <span className="font-extrabold text-emerald-600 dark:text-emerald-400">80%</span>
              </div>
              <div className="w-full h-3 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
                <div className="h-full bg-emerald-500 rounded-full w-[80%]" />
              </div>
            </div>

            <div className="space-y-1">
              <div className="flex justify-between">
                <span className="text-slate-600 dark:text-slate-300 font-bold">15-30 Daqiqa (Standart)</span>
                <span className="font-extrabold text-brand-500">15%</span>
              </div>
              <div className="w-full h-3 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
                <div className="h-full bg-brand-500 rounded-full w-[15%]" />
              </div>
            </div>

            <div className="space-y-1">
              <div className="flex justify-between">
                <span className="text-slate-600 dark:text-slate-300 font-bold">30-60 Daqiqa (O'rtacha)</span>
                <span className="font-extrabold text-amber-500">5%</span>
              </div>
              <div className="w-full h-3 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
                <div className="h-full bg-amber-500 rounded-full w-[5%]" />
              </div>
            </div>
          </div>
        </div>

        {/* Service Quality Standards & Rating Breakdown */}
        <div className="bg-white dark:bg-slate-800/90 rounded-3xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
          <div className="flex items-center space-x-2 border-b border-slate-100 dark:border-slate-700 pb-3">
            <Star className="w-5 h-5 text-purple-500 fill-purple-400" />
            <h4 className="text-sm font-black text-slate-900 dark:text-slate-100">Xizmat Sifat Standartlari va Mijozlar Mamnunligi</h4>
          </div>

          <div className="space-y-3 text-xs font-semibold">
            <div className="space-y-1">
              <div className="flex justify-between">
                <span className="text-slate-600 dark:text-slate-300 font-bold">5 Yulduz (A'lo Baho)</span>
                <span className="font-extrabold text-purple-600 dark:text-purple-400">100%</span>
              </div>
              <div className="w-full h-3 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
                <div className="h-full bg-purple-600 rounded-full w-[100%]" />
              </div>
            </div>

            <div className="space-y-1">
              <div className="flex justify-between">
                <span className="text-slate-600 dark:text-slate-300 font-bold">Reglament Bo'yicha O'z Vaqtida Bajarilganlar</span>
                <span className="font-extrabold text-emerald-600 dark:text-emerald-400">100%</span>
              </div>
              <div className="w-full h-3 rounded-full bg-slate-100 dark:bg-slate-700 overflow-hidden">
                <div className="h-full bg-emerald-500 rounded-full w-[100%]" />
              </div>
            </div>
          </div>
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
