import React, { useCallback, useEffect, useState } from 'react';
import { UserCheck, Users, Filter, RefreshCw } from 'lucide-react';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { useT } from '@/shared/presentation/i18n/i18n';

export interface EmployeeAvatar {
  userId: number;
  name: string;
  username: string;
  activeCount: number;
  avatarUrl: string;
}

interface StaffFilterStripProps {
  employees: EmployeeAvatar[];
  selectedUserId: number | null;
  onSelect: (userId: number | null) => void;
  onRefresh: () => void;
  isRefreshing?: boolean;
}

/**
 * Xodimlar avatarlari qatori — bosilgan xodim bo'yicha zayavkalarni filtrlaydi.
 * Avval faqat "Jamoa yuklamasi" sahifasida edi; dashboardda ham kerak bo'ldi,
 * shuning uchun ikkala sahifa uchun umumiy komponentga chiqarildi.
 */
export const StaffFilterStrip: React.FC<StaffFilterStripProps> = ({
  employees,
  selectedUserId,
  onSelect,
  onRefresh,
  isRefreshing = false,
}) => {
  const t = useT();
  const selectedName = employees.find((e) => e.userId === selectedUserId)?.name;

  return (
    <div className="bg-white dark:bg-slate-800/90 rounded-3xl p-6 border border-slate-200 dark:border-slate-700 shadow-md space-y-4">
      <div className="flex items-center justify-between">
        <span className="text-xs font-black uppercase tracking-wider text-slate-400 flex items-center space-x-2">
          <UserCheck className="w-4 h-4 text-brand-500" />
          <span>{t('teamWorkload.selectEmployee')}</span>
        </span>
        <div className="flex items-center space-x-3">
          {selectedUserId !== null && (
            <button
              onClick={() => onSelect(null)}
              className="text-xs font-bold text-brand-500 hover:underline flex items-center space-x-1"
            >
              <Filter className="w-3.5 h-3.5" />
              <span>{t('teamWorkload.clearFilter', { name: selectedName ?? '' })}</span>
            </button>
          )}
          <button
            onClick={onRefresh}
            className="flex items-center space-x-1.5 px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-200 transition-all shadow-xs"
          >
            <RefreshCw className={`w-3.5 h-3.5 ${isRefreshing ? 'animate-spin' : ''}`} />
            <span>{t('teamWorkload.refresh')}</span>
          </button>
        </div>
      </div>

      <div className="flex items-center space-x-6 overflow-x-auto pb-3 scrollbar-thin pt-2">
        {/* All Chip */}
        <button
          onClick={() => onSelect(null)}
          className={`flex flex-col items-center space-y-2 group min-w-[90px] ml-2 transition-transform ${
            selectedUserId === null ? 'scale-105' : ''
          }`}
        >
          <div
            className={`w-24 h-24 rounded-full flex items-center justify-center border-3 transition-all ${
              selectedUserId === null
                ? 'bg-brand-500 text-white border-brand-500 shadow-xl shadow-brand-500/25 ring-4 ring-brand-500/20'
                : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 border-slate-300 dark:border-slate-600'
            }`}
          >
            <Users className="w-10 h-10" />
          </div>
          <span className="text-sm font-extrabold text-slate-800 dark:text-slate-200">{t('teamWorkload.all')}</span>
        </button>

        {/* Employee Avatar Badged Cards */}
        {employees.map((emp) => {
          const isSelected = selectedUserId === emp.userId;
          return (
            <button
              key={emp.userId}
              onClick={() => onSelect(isSelected ? null : emp.userId)}
              // Ilgari tanlanmagan avatarlar opacity-85 bilan xiralashtirilardi —
              // rasm o'chib, sifatsiz ko'rinardi. Tanlangani halqa (ring) bilan
              // allaqachon ajralib turadi, shaffoflik shart emas.
              className={`flex flex-col items-center space-y-2 relative group min-w-[90px] transition-transform ${
                isSelected ? 'scale-105' : 'hover:scale-105'
              }`}
              title={t('teamWorkload.activeTickets', { name: emp.name, count: emp.activeCount })}
            >
              <div className="relative">
                <img
                  src={emp.avatarUrl}
                  alt={emp.name}
                  className={`w-24 h-24 rounded-full object-cover border-3 transition-all shadow-md ${
                    isSelected
                      ? 'border-brand-500 ring-4 ring-brand-500/25 shadow-xl shadow-brand-500/25'
                      : 'border-slate-200 dark:border-slate-700 hover:border-brand-400'
                  }`}
                />
                {/* Badge count at top-right of avatar */}
                {emp.activeCount > 0 && (
                  <span className="absolute -top-1 -right-1 w-7 h-7 rounded-full bg-amber-500 text-white font-black text-sm flex items-center justify-center shadow-lg border-2 border-white dark:border-slate-800">
                    {emp.activeCount}
                  </span>
                )}
              </div>
              <span
                className={`text-xs font-black truncate max-w-[96px] ${
                  isSelected ? 'text-brand-500' : 'text-slate-800 dark:text-slate-200'
                }`}
              >
                {emp.name.split(' ')[0]}
              </span>
            </button>
          );
        })}
      </div>
    </div>
  );
};

/**
 * `/tickets/monitoring` dan xodimlar ro'yxatini oladi.
 * Strip ishlatilgan har bir sahifa o'z yuklashini takrorlamasligi uchun.
 */
export const useStaffAvatars = () => {
  const [employees, setEmployees] = useState<EmployeeAvatar[]>([]);
  const [isLoading, setIsLoading] = useState(false);

  const refresh = useCallback(() => {
    setIsLoading(true);
    axiosClient
      .get<{ employeeAvatars: EmployeeAvatar[] }>('/tickets/monitoring')
      .then((res) => {
        if (res.data?.employeeAvatars) setEmployees(res.data.employeeAvatars);
      })
      .catch(() => {})
      .finally(() => setIsLoading(false));
  }, []);

  useEffect(() => {
    refresh();
  }, [refresh]);

  return { employees, isLoading, refresh };
};
