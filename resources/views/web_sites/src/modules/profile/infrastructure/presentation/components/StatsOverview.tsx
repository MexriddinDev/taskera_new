import React, { useState, useEffect } from 'react';
import { CheckCircle2, XCircle, Clock, Calendar, TrendingUp, Award, Zap, Repeat, ShieldCheck } from 'lucide-react';
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
  dailyTrend?: DailyTrendItem[];
  peakDay?: string;
  maxClosedCount?: number;
}

export const StatsOverview: React.FC = () => {
  const [stats, setStats] = useState<StatsData | null>(null);
  const [reassignments, setReassignments] = useState<ReassignmentLog[]>([]);
  const [loading, setLoading] = useState(true);

  const { user } = useCan();
  const isSuperAdmin = user?.role === 'Super Admin' || user?.username === 'admin' || user?.username === 'superadmin';

  useEffect(() => {
    setLoading(true);
    axiosClient
      .get('/tickets/stats')
      .then((res) => {
        if (res.data) setStats(res.data);
      })
      .catch(() => {})
      .finally(() => setLoading(false));

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
  }, [isSuperAdmin]);

  const totalCompleted = stats?.completed ?? 0;
  const todayCompleted = stats?.todayCompleted ?? 0;
  const inProgress = stats?.inProgress ?? 0;
  const myTasks = stats?.myTasks ?? 0;
  const dailyTrend = stats?.dailyTrend || [];
  const maxCount = stats?.maxClosedCount || Math.max(...dailyTrend.map((d) => d.count), 1);
  const peakDay = stats?.peakDay || "Ma'lumot yetarli emas";

  if (loading) {
    return (
      <div className="w-full p-8 text-center text-xs font-bold text-slate-400 animate-pulse">
        Statistika ma'lumotlari yuklanmoqda...
      </div>
    );
  }

  return (
    <div className="w-full space-y-6">
      {/* Peak Day Achievement Banner */}
      <div className="p-5 rounded-3xl bg-gradient-to-r from-brand-500/10 via-purple-500/10 to-emerald-500/10 border border-brand-500/30 shadow-md flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div className="flex items-center space-x-3.5">
          <div className="w-12 h-12 rounded-2xl bg-amber-500/20 text-amber-500 flex items-center justify-center flex-shrink-0 shadow-sm border border-amber-500/30">
            <Award className="w-6 h-6" />
          </div>
          <div>
            <span className="text-[11px] font-mono font-extrabold uppercase tracking-wider text-amber-600 dark:text-amber-400">
              Shaxsiy Rekord va Samadorlik
            </span>
            <h3 className="text-sm sm:text-base font-black text-slate-900 dark:text-slate-100">
              Eng ko'p zayavka yopgan kuningiz: <span className="text-brand-500 dark:text-brand-400">{peakDay}</span>
            </h3>
          </div>
        </div>

        <div className="px-4 py-2 rounded-2xl bg-white dark:bg-slate-800 text-xs font-extrabold text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 flex items-center space-x-2 shadow-sm">
          <Zap className="w-4 h-4 text-emerald-500" />
          <span>Bugun: <strong>{todayCompleted} ta</strong> zayavka yopildi</span>
        </div>
      </div>

      {/* 1. Shaxsiy Ko'rsatkichlar Kartalari */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {/* Bajarilgan */}
        <div className="bg-white dark:bg-slate-800/90 rounded-2xl p-5 border border-slate-200 dark:border-slate-700 shadow-sm hover:border-emerald-500/50 transition-all group">
          <div className="flex items-center justify-between mb-3">
            <span className="text-xs font-bold text-slate-500 dark:text-slate-400">Jami Yopilgan Zayavkalaringiz</span>
            <div className="p-2 rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 group-hover:scale-110 transition-transform">
              <CheckCircle2 className="w-4 h-4" />
            </div>
          </div>
          <div className="flex items-baseline space-x-1.5">
            <span className="text-2xl font-black text-slate-900 dark:text-slate-100">{totalCompleted}</span>
            <span className="text-xs font-bold text-slate-400">ta zayavka</span>
          </div>
        </div>

        {/* Bugun Yopilgan */}
        <div className="bg-white dark:bg-slate-800/90 rounded-2xl p-5 border border-slate-200 dark:border-slate-700 shadow-sm hover:border-brand-500/50 transition-all group">
          <div className="flex items-center justify-between mb-3">
            <span className="text-xs font-bold text-slate-500 dark:text-slate-400">Bugun Yopilganlar</span>
            <div className="p-2 rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-950/40 group-hover:scale-110 transition-transform">
              <Calendar className="w-4 h-4" />
            </div>
          </div>
          <div className="flex items-baseline space-x-1.5">
            <span className="text-2xl font-black text-brand-500">{todayCompleted}</span>
            <span className="text-xs font-bold text-slate-400">ta bugun</span>
          </div>
        </div>

        {/* Jarayondagi Topshiriqlar */}
        <div className="bg-white dark:bg-slate-800/90 rounded-2xl p-5 border border-slate-200 dark:border-slate-700 shadow-sm hover:border-amber-500/50 transition-all group">
          <div className="flex items-center justify-between mb-3">
            <span className="text-xs font-bold text-slate-500 dark:text-slate-400">Sizdagi Jarayondagi Topshiriqlar</span>
            <div className="p-2 rounded-xl bg-amber-50 text-amber-500 dark:bg-amber-950/40 group-hover:scale-110 transition-transform">
              <Clock className="w-4 h-4" />
            </div>
          </div>
          <div className="flex items-baseline space-x-1.5">
            <span className="text-2xl font-black text-slate-900 dark:text-slate-100">{myTasks}</span>
            <span className="text-xs font-bold text-slate-400">ta topshiriq</span>
          </div>
        </div>

        {/* Samadorlik Dinamikasi */}
        <div className="bg-white dark:bg-slate-800/90 rounded-2xl p-5 border border-slate-200 dark:border-slate-700 shadow-sm hover:border-purple-500/50 transition-all group">
          <div className="flex items-center justify-between mb-3">
            <span className="text-xs font-bold text-slate-500 dark:text-slate-400">O'rtacha Samadorlik</span>
            <div className="p-2 rounded-xl bg-purple-50 text-purple-600 dark:bg-purple-950/40 group-hover:scale-110 transition-transform">
              <TrendingUp className="w-4 h-4" />
            </div>
          </div>
          <div className="flex items-baseline space-x-1.5">
            <span className="text-2xl font-black text-purple-600 dark:text-purple-400">98.4%</span>
            <span className="text-xs font-bold text-slate-400">SLA moslik</span>
          </div>
        </div>
      </div>

      {/* 2. Kunlar Kesimida Bajarilgan Zayavkalar Grafigi (Daily Resolution Chart) */}
      <div className="bg-white dark:bg-slate-800/90 rounded-3xl p-6 border border-slate-200 dark:border-slate-700 shadow-md space-y-4">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 dark:border-slate-700 pb-4">
          <div>
            <h3 className="text-base font-black text-slate-900 dark:text-slate-100 flex items-center space-x-2">
              <TrendingUp className="w-5 h-5 text-emerald-500" />
              <span>Kunlar Kesimida Bajarilgan Zayavkalaringiz Dinamikasi (So'nggi 7 Kun)</span>
            </h3>
            <p className="text-xs text-slate-500 font-medium mt-0.5">
              Qaysi kuni nechta zayavka yopganingiz va eng mahsuldor kunlaringiz aks etadi.
            </p>
          </div>
        </div>

        {/* Visual Bar Chart */}
        <div className="pt-4 pb-2">
          {dailyTrend.length === 0 ? (
            <div className="text-center py-8 text-xs text-slate-400 italic">
              So'nggi 7 kunda bajarilgan zayavkalar ma'lumoti topilmadi
            </div>
          ) : (
            <div className="grid grid-cols-7 gap-2 sm:gap-4 items-end h-56 px-2">
              {dailyTrend.map((item, idx) => {
                const heightPercent = maxCount > 0 ? Math.max((item.count / maxCount) * 100, 8) : 8;
                const isPeak = item.count > 0 && item.count === maxCount;

                return (
                  <div key={idx} className="flex flex-col items-center h-full justify-end group">
                    {/* Count Badge on Top of Bar */}
                    <div className={`mb-2 text-xs font-black px-2 py-0.5 rounded-lg transition-transform group-hover:scale-110 ${
                      isPeak
                        ? 'bg-amber-500 text-white shadow-md'
                        : item.count > 0
                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'
                        : 'text-slate-400 bg-slate-100 dark:bg-slate-700'
                    }`}>
                      {item.count} ta
                    </div>

                    {/* Bar Line */}
                    <div
                      style={{ height: `${heightPercent}%` }}
                      className={`w-full max-w-[48px] rounded-2xl transition-all duration-500 relative ${
                        isPeak
                          ? 'bg-gradient-to-t from-amber-500 to-emerald-500 shadow-lg shadow-amber-500/20 ring-2 ring-amber-400'
                          : item.count > 0
                          ? 'bg-gradient-to-t from-emerald-600 to-emerald-400 shadow-md shadow-emerald-500/20'
                          : 'bg-slate-200 dark:bg-slate-700 opacity-60'
                      }`}
                    />

                    {/* Day Name & Date Below */}
                    <div className="mt-3 text-center">
                      <span className={`block text-[11px] font-extrabold ${isPeak ? 'text-amber-500' : 'text-slate-700 dark:text-slate-300'}`}>
                        {item.dayName}
                      </span>
                      <span className="block text-[9px] font-bold text-slate-400 font-mono">
                        {item.shortDay}
                      </span>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>
      </div>

      {/* 3. Superadmin Monitoring: Zayavkalarni o'zlashtirish va biriktirish hisoboti (Kim kimning zayafkasini olgan) */}
      {isSuperAdmin && (
        <div className="bg-white dark:bg-slate-800/90 rounded-3xl p-6 border border-slate-200 dark:border-slate-700 shadow-md space-y-4">
          <div className="flex items-center justify-between border-b border-slate-100 dark:border-slate-700 pb-4">
            <div className="flex items-center space-x-2">
              <Repeat className="w-5 h-5 text-amber-500" />
              <h3 className="text-base font-black text-slate-900 dark:text-slate-100">
                O'zlashtirishlar Hisoboti (Kim kimning zayafkasini ko'p olgan)
              </h3>
            </div>
            <span className="text-xs font-bold text-slate-400">Superadmin Auditi</span>
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
                {reassignments.length === 0 && (
                  <tr>
                    <td colSpan={5} className="py-6 text-center text-slate-400 font-medium italic">
                      Hali zayavkalarni o'zlashtirish holatlari yuz bermagan.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  );
};
