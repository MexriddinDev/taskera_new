import React, { useState, useEffect } from 'react';
import { ShieldCheck, Search, Calendar, Filter, User, RefreshCw, Activity, ArrowLeft } from 'lucide-react';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { Link } from 'react-router-dom';

interface AuditLogItem {
  id: number;
  actor_user_id?: number | null;
  actorName?: string;
  action: string;
  auditable_type?: string;
  auditable_id?: number;
  auditable_public_id?: string;
  description: string;
  ip_address?: string;
  user_agent?: string;
  createdAt: string;
}

export const AuditLogsPage: React.FC = () => {
  const [logs, setLogs] = useState<AuditLogItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [actionFilter, setActionFilter] = useState('');
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);

  const fetchLogs = (page = 1) => {
    setLoading(true);
    axiosClient
      .get('/audit-logs', {
        params: {
          page,
          action: actionFilter || undefined,
          date_from: dateFrom || undefined,
          date_to: dateTo || undefined,
          per_page: 20,
        },
      })
      .then((res) => {
        if (res.data?.data) {
          setLogs(res.data.data);
          setTotalPages(res.data.meta?.last_page || 1);
          setCurrentPage(res.data.meta?.current_page || 1);
        }
      })
      .catch(() => {})
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    fetchLogs(1);
  }, [actionFilter, dateFrom, dateTo]);

  const filteredLogs = logs.filter((log) => {
    if (!search.trim()) return true;
    const s = search.toLowerCase();
    return (
      log.action?.toLowerCase().includes(s) ||
      log.description?.toLowerCase().includes(s) ||
      log.actorName?.toLowerCase().includes(s) ||
      log.ip_address?.toLowerCase().includes(s)
    );
  });

  return (
    <div className="w-full px-4 sm:px-8 lg:px-12 py-8 space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <Link
            to="/dashboard"
            className="inline-flex items-center text-xs font-bold text-slate-500 hover:text-purple-600 transition-colors mb-2"
          >
            <ArrowLeft className="w-4 h-4 mr-1" /> Dashboardga qaytish
          </Link>
          <h1 className="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-slate-100 flex items-center space-x-3">
            <ShieldCheck className="w-8 h-8 text-purple-600 dark:text-purple-400" />
            <span>Tizim Audit Loglari</span>
          </h1>
          <p className="text-xs sm:text-sm font-medium text-slate-500 dark:text-slate-400 mt-1">
            Xavfsizlik va amallar tarixi: Zayavka yaratish, holatlarni o'zgartirish va rollar boshqaruvi.
          </p>
        </div>

        <button
          onClick={() => fetchLogs(currentPage)}
          className="px-4 py-2.5 rounded-xl bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-300 hover:bg-purple-100 font-bold text-xs border border-purple-200 dark:border-purple-800 transition-all flex items-center space-x-2 cursor-pointer"
        >
          <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
          <span>Yangilash</span>
        </button>
      </div>

      {/* Filters Bar */}
      <div className="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {/* Search */}
        <div className="relative">
          <Search className="w-4 h-4 absolute left-3 top-3 text-slate-400" />
          <input
            type="text"
            placeholder="Loglar, harakatlar yoki IP bo'yicha qidirish..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="w-full pl-9 pr-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-purple-500"
          />
        </div>

        {/* Action Type */}
        <div className="relative">
          <Filter className="w-4 h-4 absolute left-3 top-3 text-slate-400" />
          <select
            value={actionFilter}
            onChange={(e) => setActionFilter(e.target.value)}
            className="w-full pl-9 pr-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-purple-500"
          >
            <option value="">Barcha Amal Turlari</option>
            <option value="TICKET_CREATED">Zayavka Yaratildi</option>
            <option value="TICKET_ASSIGNED">Zayavka Biriktirildi</option>
            <option value="STATUS_CHANGED">Holat O'zgartirildi</option>
            <option value="ROLE_UPDATED">Rol Tahrirlandi</option>
            <option value="USER_LOGIN">Tizimga Kirildi</option>
          </select>
        </div>

        {/* Date From */}
        <div className="relative">
          <Calendar className="w-4 h-4 absolute left-3 top-3 text-slate-400" />
          <input
            type="date"
            value={dateFrom}
            onChange={(e) => setDateFrom(e.target.value)}
            className="w-full pl-9 pr-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-purple-500"
          />
        </div>

        {/* Date To */}
        <div className="relative">
          <Calendar className="w-4 h-4 absolute left-3 top-3 text-slate-400" />
          <input
            type="date"
            value={dateTo}
            onChange={(e) => setDateTo(e.target.value)}
            className="w-full pl-9 pr-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-slate-900 dark:text-slate-100 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-purple-500"
          />
        </div>
      </div>

      {/* Logs Table */}
      <div className="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left border-collapse">
            <thead>
              <tr className="border-b border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/50 text-[11px] font-black uppercase text-slate-500 tracking-wider">
                <th className="py-4 px-6">ID / Vaqt</th>
                <th className="py-4 px-6">Bajaruvchi (Actor)</th>
                <th className="py-4 px-6">Amal kodi</th>
                <th className="py-4 px-6">Batafsil Izoh</th>
                <th className="py-4 px-6">IP Manzil</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-800 text-xs font-semibold">
              {loading ? (
                <tr>
                  <td colSpan={5} className="py-12 text-center text-slate-400">
                    <Activity className="w-6 h-6 animate-spin mx-auto mb-2" />
                    Audit loglar yuklanmoqda...
                  </td>
                </tr>
              ) : filteredLogs.length === 0 ? (
                <tr>
                  <td colSpan={5} className="py-12 text-center text-slate-400">
                    Audit loglar topilmadi.
                  </td>
                </tr>
              ) : (
                filteredLogs.map((log) => (
                  <tr key={log.id} className="hover:bg-slate-50/80 dark:hover:bg-slate-800/50 transition-colors">
                    <td className="py-4 px-6 font-mono text-slate-500">
                      <span className="font-bold text-slate-700 dark:text-slate-300">#{log.id}</span>
                      <span className="block text-[10px] text-slate-400 mt-0.5">{log.createdAt}</span>
                    </td>
                    <td className="py-4 px-6">
                      <div className="flex items-center space-x-2">
                        <div className="w-7 h-7 rounded-full bg-purple-100 dark:bg-purple-950/80 text-purple-700 dark:text-purple-300 flex items-center justify-center font-bold text-xs">
                          <User className="w-4 h-4" />
                        </div>
                        <span className="font-extrabold text-slate-800 dark:text-slate-200">
                          {log.actorName || (log.actor_user_id ? `User #${log.actor_user_id}` : 'Tizim')}
                        </span>
                      </div>
                    </td>
                    <td className="py-4 px-6">
                      <span className="px-2.5 py-1 rounded-lg bg-purple-100 dark:bg-purple-950/80 text-purple-700 dark:text-purple-300 text-[11px] font-extrabold font-mono border border-purple-200 dark:border-purple-800">
                        {log.action}
                      </span>
                    </td>
                    <td className="py-4 px-6 max-w-md text-slate-700 dark:text-slate-300">
                      {log.description}
                    </td>
                    <td className="py-4 px-6 font-mono text-slate-500 text-[11px]">
                      {log.ip_address || '127.0.0.1'}
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
        {totalPages > 1 && (
          <div className="p-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
            <span className="text-xs font-medium text-slate-500">
              Sahifa {currentPage} / {totalPages}
            </span>
            <div className="flex space-x-2">
              <button
                disabled={currentPage <= 1}
                onClick={() => fetchLogs(currentPage - 1)}
                className="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-xs font-bold disabled:opacity-40 cursor-pointer"
              >
                Oldingi
              </button>
              <button
                disabled={currentPage >= totalPages}
                onClick={() => fetchLogs(currentPage + 1)}
                className="px-3 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-xs font-bold disabled:opacity-40 cursor-pointer"
              >
                Keyingi
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
};
