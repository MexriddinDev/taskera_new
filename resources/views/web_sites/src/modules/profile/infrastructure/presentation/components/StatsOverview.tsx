import React, { useState, useEffect } from 'react';
import { CheckCircle2, XCircle, Clock, Star } from 'lucide-react';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';

export const StatsOverview: React.FC = () => {
  const [stats, setStats] = useState<{ total: number; completed: number; rejected: number; myTasks: number } | null>(null);

  useEffect(() => {
    axiosClient.get('/tickets/stats').then((res) => {
      if (res.data) setStats(res.data);
    }).catch(() => {});
  }, []);

  const personalSummary = {
    totalSolved: stats?.completed ?? 0,
    totalRejected: stats?.rejected ?? 0,
    avgResolutionTimeMinutes: 0,
    satisfactionRating: 0,
  };

  return (
    <div className="w-full space-y-6">
      {/* 1. Shaxsiy Ko'rsatkichlar Kartalari (Sleek SaaS Metric Cards) */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
        {/* Bajarilgan */}
        <div className="bg-white dark:bg-gray-800/90 rounded-2xl p-5 border border-gray-200/80 dark:border-gray-700/80 shadow-sm hover:border-success-500/40 transition-all group">
          <div className="flex items-center justify-between mb-3">
            <span className="text-xs font-semibold text-gray-500 dark:text-gray-400">Bajarilgan</span>
            <div className="p-2 rounded-xl bg-success-50 text-success-500 dark:bg-success-700/20 group-hover:scale-110 transition-transform">
              <CheckCircle2 className="w-4 h-4" />
            </div>
          </div>
          <div className="flex items-baseline space-x-1.5">
            <span className="text-2xl font-extrabold text-gray-900 dark:text-gray-100">{personalSummary.totalSolved}</span>
            <span className="text-xs font-bold text-gray-400">ta zayavka</span>
          </div>
        </div>

        {/* Rad etilgan */}
        <div className="bg-white dark:bg-gray-800/90 rounded-2xl p-5 border border-gray-200/80 dark:border-gray-700/80 shadow-sm hover:border-error-500/40 transition-all group">
          <div className="flex items-center justify-between mb-3">
            <span className="text-xs font-semibold text-gray-500 dark:text-gray-400">Rad etilgan</span>
            <div className="p-2 rounded-xl bg-error-50 text-error-500 dark:bg-error-700/20 group-hover:scale-110 transition-transform">
              <XCircle className="w-4 h-4" />
            </div>
          </div>
          <div className="flex items-baseline space-x-1.5">
            <span className="text-2xl font-extrabold text-error-500">{personalSummary.totalRejected}</span>
            <span className="text-xs font-bold text-gray-400">ta zayavka</span>
          </div>
        </div>

        {/* Ishlash tezligi */}
        <div className="bg-white dark:bg-gray-800/90 rounded-2xl p-5 border border-gray-200/80 dark:border-gray-700/80 shadow-sm hover:border-brand-500/40 transition-all group">
          <div className="flex items-center justify-between mb-3">
            <span className="text-xs font-semibold text-gray-500 dark:text-gray-400">Ishlash tezligi</span>
            <div className="p-2 rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-950/40 group-hover:scale-110 transition-transform">
              <Clock className="w-4 h-4" />
            </div>
          </div>
          <div className="flex items-baseline space-x-1.5">
            <span className="text-2xl font-extrabold text-gray-900 dark:text-gray-100">{personalSummary.avgResolutionTimeMinutes}</span>
            <span className="text-xs font-bold text-gray-400">daqiqa / o'rtacha</span>
          </div>
        </div>

        {/* Mijoz bahosi */}
        <div className="bg-white dark:bg-gray-800/90 rounded-2xl p-5 border border-gray-200/80 dark:border-gray-700/80 shadow-sm hover:border-amber-500/40 transition-all group">
          <div className="flex items-center justify-between mb-3">
            <span className="text-xs font-semibold text-gray-500 dark:text-gray-400">Mijoz bahosi</span>
            <div className="p-2 rounded-xl bg-amber-50 text-amber-500 dark:bg-amber-950/40 group-hover:scale-110 transition-transform">
              <Star className="w-4 h-4 fill-amber-400" />
            </div>
          </div>
          <div className="flex items-baseline space-x-1.5">
            <span className="text-2xl font-extrabold text-gray-900 dark:text-gray-100">{personalSummary.satisfactionRating}</span>
            <span className="text-xs font-bold text-amber-500">★ 5.0 dan</span>
          </div>
        </div>
      </div>
    </div>
  );
};
