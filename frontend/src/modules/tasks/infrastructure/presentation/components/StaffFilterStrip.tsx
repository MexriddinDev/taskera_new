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

/** Banner/logo kabi juda keng rasmlar avatar o'rnida buzilib ko'rinmasligi uchun initials fallback. */
const EmployeeAvatarImage: React.FC<{ employee: EmployeeAvatar; selected: boolean }> = ({ employee, selected }) => {
  const [invalidImage, setInvalidImage] = useState(!employee.avatarUrl);
  const initials = employee.name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join('') || employee.username.slice(0, 2).toUpperCase();
  const frameClass = selected
    ? 'border-brand-500 ring-4 ring-brand-500/25 shadow-xl shadow-brand-500/25'
    : 'border-slate-200 dark:border-slate-700 group-hover:border-brand-400';

  if (invalidImage) {
    return (
      <span className={`flex h-20 w-20 sm:h-24 sm:w-24 items-center justify-center rounded-full border-2 bg-gradient-to-br from-brand-500 to-sky-500 text-xl font-black text-white shadow-md ${frameClass}`}>
        {initials}
      </span>
    );
  }

  return (
    <img
      src={employee.avatarUrl}
      alt={employee.name}
      decoding="async"
      onError={() => setInvalidImage(true)}
      onLoad={(event) => {
        const image = event.currentTarget;
        const ratio = image.naturalWidth / Math.max(image.naturalHeight, 1);
        if (ratio > 1.8 || ratio < 0.55) setInvalidImage(true);
      }}
      className={`h-20 w-20 sm:h-24 sm:w-24 rounded-full object-cover object-center border-2 transition-all shadow-md ${frameClass}`}
    />
  );
};

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
    <div className="bg-white dark:bg-slate-800/90 rounded-3xl p-4 sm:p-6 border border-slate-200 dark:border-slate-700 shadow-md space-y-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
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
            type="button"
            onClick={onRefresh}
            disabled={isRefreshing}
            aria-busy={isRefreshing || undefined}
            className="flex items-center space-x-1.5 px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-200 transition-all shadow-xs"
          >
            <RefreshCw className={`w-3.5 h-3.5 ${isRefreshing ? 'animate-spin' : ''}`} />
            <span>{t('teamWorkload.refresh')}</span>
          </button>
        </div>
      </div>

      <div className="flex items-start gap-4 sm:gap-6 overflow-x-auto pb-3 scrollbar-thin pt-2 snap-x snap-proximity">
        {/* All Chip */}
        <button
          type="button"
          onClick={() => onSelect(null)}
          aria-pressed={selectedUserId === null}
          className={`flex flex-col items-center space-y-2 group min-w-[80px] sm:min-w-[90px] ml-1 sm:ml-2 transition-transform snap-start ${
            selectedUserId === null ? 'scale-105' : ''
          }`}
        >
          <div
            className={`w-20 h-20 sm:w-24 sm:h-24 rounded-full flex items-center justify-center border-2 transition-all ${
              selectedUserId === null
                ? 'bg-brand-500 text-white border-brand-500 shadow-xl shadow-brand-500/25 ring-4 ring-brand-500/20'
                : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 border-slate-300 dark:border-slate-600'
            }`}
          >
            <Users className="w-8 h-8 sm:w-10 sm:h-10" />
          </div>
          <span className="text-sm font-extrabold text-slate-800 dark:text-slate-200">{t('teamWorkload.all')}</span>
        </button>

        {/* Employee Avatar Badged Cards */}
        {employees.map((emp) => {
          const isSelected = selectedUserId === emp.userId;
          return (
            <button
              key={emp.userId}
              type="button"
              onClick={() => onSelect(isSelected ? null : emp.userId)}
              aria-pressed={isSelected}
              // Ilgari tanlanmagan avatarlar opacity-85 bilan xiralashtirilardi —
              // rasm o'chib, sifatsiz ko'rinardi. Tanlangani halqa (ring) bilan
              // allaqachon ajralib turadi, shaffoflik shart emas.
              className={`flex flex-col items-center space-y-2 relative group min-w-[80px] sm:min-w-[90px] transition-transform snap-start ${
                isSelected ? 'scale-105' : 'hover:scale-105'
              }`}
              title={t('teamWorkload.activeTickets', { name: emp.name, count: emp.activeCount })}
            >
              <div className="relative">
                <EmployeeAvatarImage employee={emp} selected={isSelected} />
                {/* Badge count at top-right of avatar */}
                {emp.activeCount > 0 && (
                  <span className="absolute -top-1 -right-1 min-w-6 h-6 px-1 rounded-full bg-amber-500 text-white font-black text-xs flex items-center justify-center shadow-lg border-2 border-white dark:border-slate-800">
                    {emp.activeCount}
                  </span>
                )}
              </div>
              <span
                className={`text-xs font-black leading-tight text-center max-w-[120px] ${
                  isSelected ? 'text-brand-500' : 'text-slate-800 dark:text-slate-200'
                }`}
              >
                {emp.name}
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
    window.addEventListener('tickets:changed', refresh);
    return () => window.removeEventListener('tickets:changed', refresh);
  }, [refresh]);

  return { employees, isLoading, refresh };
};
