import React, { useState, useEffect } from 'react';
import { useTasks } from '@/modules/tasks/infrastructure/presentation/hooks/useTasks';
import { useUpdateTask } from '@/modules/tasks/infrastructure/presentation/hooks/useUpdateTask';
import { KanbanBoard } from '@/modules/tasks/infrastructure/presentation/components/KanbanBoard';
import { TaskSkeleton } from '@/modules/tasks/infrastructure/presentation/components/TaskSkeleton';
import { EmptyState } from '@/shared/presentation/components/EmptyState';
import { Task, TaskPriority } from '@/modules/tasks/domain/entities/Task';
import { useCan } from '@/shared/presentation/hooks/useCan';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { Layers, AlertOctagon, AlertTriangle, CheckCircle, ShieldAlert, Users, Clock, ArrowRight } from 'lucide-react';

interface EmployeeStat {
  userId: number;
  name: string;
  username: string;
  todo: number;
  inProgress: number;
  rejected: number;
  done: number;
  totalActive: number;
  avgSpentMinutes: number;
}

export const OpenTasksPage: React.FC = () => {
  const [selectedFilter, setSelectedFilter] = useState<number>(0);
  const [limitErrorMessage, setLimitErrorMessage] = useState<string | null>(null);

  // Superadmin monitoring data
  const { user } = useCan();
  const isSuperAdmin = user?.role === 'Super Admin' || user?.username === 'admin' || user?.username === 'superadmin';
  const [employeeStats, setEmployeeStats] = useState<EmployeeStat[]>([]);
  const [statsLoading, setStatsLoading] = useState(false);

  const filterLabels = ['Barchasi', 'Kritik', 'Yuqori', 'O\'rta', 'Past'];
  const priorityMapping: (TaskPriority | 'all')[] = ['all', 'high', 'high', 'medium', 'low'];
  const currentPriority = priorityMapping[selectedFilter];

  const { data, isLoading, refetch } = useTasks({
    status: 'todo',
    priority: currentPriority,
    limit: 50,
  });

  const updateTaskMutation = useUpdateTask();

  useEffect(() => {
    if (isSuperAdmin) {
      setStatsLoading(true);
      axiosClient.get<{ employeeStats: EmployeeStat[] }>('/tickets/monitoring')
        .then((res) => {
          if (res.data?.employeeStats) {
            setEmployeeStats(res.data.employeeStats);
          }
        })
        .catch(() => {})
        .finally(() => setStatsLoading(false));
    }
  }, [isSuperAdmin]);

  const handleAcceptTask = (taskId: number) => {
    setLimitErrorMessage(null);
    updateTaskMutation.mutate(
      { id: taskId, dto: { assignToMe: true } },
      {
        onSuccess: () => {
          refetch();
          if (isSuperAdmin) {
            axiosClient.get<{ employeeStats: EmployeeStat[] }>('/tickets/monitoring')
              .then((res) => setEmployeeStats(res.data?.employeeStats || []));
          }
        },
        onError: (err: any) => {
          const msg = err.response?.data?.message || err.message || "Zayavka qabul qilishda xatolik";
          setLimitErrorMessage(msg);
        },
      }
    );
  };

  const handleToggleStatus = (task: Task) => {
    if (task.status === 'done') return;
    updateTaskMutation.mutate({
      id: task.id,
      dto: { status: 'done', completed: true },
    });
  };

  // Open board shows ONLY unassigned incoming todo tickets.
  // Once accepted (assigned), a ticket disappears from here and moves to My Tasks.
  const openTasks = (data?.tasks || []).filter((t) => !t.isAssigned && t.status === 'todo');

  return (
    <div className="w-full px-4 sm:px-8 lg:px-12 py-8 space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-extrabold text-gray-900 dark:text-gray-100">Tasks (Ochiq Zayavkalar)</h1>
          <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
            {isSuperAdmin
              ? "Barcha guruhlar bo'limlari va xodimlarda turgan zayavkalar nazorati"
              : "Yangi guruh zayavkalari yopiq holatda. Ko'rish uchun 'Qabul qilish' tugmasini bosing."}
          </p>
        </div>
      </div>

      {/* Error Alert Modal if To Do limit (max 3) exceeded */}
      {limitErrorMessage && (
        <div className="p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-300 dark:border-rose-800 text-rose-700 dark:text-rose-300 text-sm font-semibold flex items-center justify-between animate-fadeIn shadow-md">
          <div className="flex items-center space-x-3">
            <ShieldAlert className="w-6 h-6 text-rose-600 flex-shrink-0" />
            <span>{limitErrorMessage}</span>
          </div>
          <button
            onClick={() => setLimitErrorMessage(null)}
            className="px-3 py-1 bg-rose-200 dark:bg-rose-800 hover:bg-rose-300 text-rose-900 dark:text-rose-100 rounded-lg text-xs font-bold transition-colors"
          >
            Tushundim
          </button>
        </div>
      )}

      {/* Superadmin Cross-Department Employee Workload Breakdown */}
      {isSuperAdmin && (
        <div className="bg-white dark:bg-gray-800/90 rounded-3xl p-6 border border-gray-200 dark:border-gray-700/80 shadow-sm space-y-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-2">
              <Users className="w-5 h-5 text-brand-500" />
              <h3 className="font-extrabold text-base text-gray-900 dark:text-gray-100">
                Xodimlar bo'yicha zayavkalar taqsimoti (Superadmin Monitoring)
              </h3>
            </div>
            <span className="text-xs font-bold text-gray-400">
              {statsLoading ? "Yuklanmoqda..." : `${employeeStats.length} ta faol xodim`}
            </span>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead>
                <tr className="border-b border-gray-200 dark:border-gray-700 text-gray-400 font-bold uppercase tracking-wider">
                  <th className="pb-3 px-3">Xodim</th>
                  <th className="pb-3 px-3 text-center">To Do (Qabul qilingan)</th>
                  <th className="pb-3 px-3 text-center">In Progress (Jarayonda)</th>
                  <th className="pb-3 px-3 text-center">Rejected (Qaytarilgan)</th>
                  <th className="pb-3 px-3 text-center">Done (Bajarilgan)</th>
                  <th className="pb-3 px-3 text-center">Jami Faol</th>
                  <th className="pb-3 px-3 text-right">O'rtacha sarflangan vaqt</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 dark:divide-gray-700/60 font-medium text-gray-700 dark:text-gray-200">
                {employeeStats.map((emp) => (
                  <tr key={emp.userId} className="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition-colors">
                    <td className="py-3 px-3 font-extrabold text-gray-900 dark:text-gray-100 flex items-center space-x-2">
                      <div className="w-7 h-7 rounded-full bg-brand-100 text-brand-700 dark:bg-brand-900/50 dark:text-brand-300 font-bold flex items-center justify-center text-xs">
                        {emp.name.charAt(0).toUpperCase()}
                      </div>
                      <span>{emp.name} ({emp.username})</span>
                    </td>
                    <td className="py-3 px-3 text-center">
                      <span className={`px-2 py-0.5 rounded-full font-extrabold ${emp.todo >= 3 ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/40' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300'}`}>
                        {emp.todo} / 3
                      </span>
                    </td>
                    <td className="py-3 px-3 text-center font-bold text-amber-600 dark:text-amber-400">
                      {emp.inProgress}
                    </td>
                    <td className="py-3 px-3 text-center font-bold text-rose-600 dark:text-rose-400">
                      {emp.rejected}
                    </td>
                    <td className="py-3 px-3 text-center font-bold text-emerald-600 dark:text-emerald-400">
                      {emp.done}
                    </td>
                    <td className="py-3 px-3 text-center font-extrabold text-gray-900 dark:text-gray-100">
                      {emp.totalActive}
                    </td>
                    <td className="py-3 px-3 text-right font-semibold text-gray-500 dark:text-gray-400">
                      {emp.avgSpentMinutes > 0 ? `${emp.avgSpentMinutes} daqiqa` : '—'}
                    </td>
                  </tr>
                ))}
                {employeeStats.length === 0 && !statsLoading && (
                  <tr>
                    <td colSpan={7} className="py-4 text-center text-gray-400 font-semibold">
                      Hozirda qabul qilingan faol zayavkalar yo'q.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Stats Row Chips */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div className="p-4 rounded-2xl bg-white dark:bg-gray-800/90 border border-gray-200 dark:border-gray-700/80 shadow-sm flex items-center justify-between">
          <div>
            <p className="text-2xl font-extrabold text-gray-900 dark:text-gray-100">{openTasks.length}</p>
            <p className="text-xs font-semibold text-gray-400">Open</p>
          </div>
          <div className="p-2.5 rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-950/40">
            <Layers className="w-5 h-5" />
          </div>
        </div>

        <div className="p-4 rounded-2xl bg-white dark:bg-gray-800/90 border border-gray-200 dark:border-gray-700/80 shadow-sm flex items-center justify-between">
          <div>
            <p className="text-2xl font-extrabold text-error-500">
              {openTasks.filter((t) => t.priority === 'high').length}
            </p>
            <p className="text-xs font-semibold text-gray-400">Critical / High</p>
          </div>
          <div className="p-2.5 rounded-xl bg-error-50 text-error-500 dark:bg-error-700/20">
            <AlertOctagon className="w-5 h-5" />
          </div>
        </div>

        <div className="p-4 rounded-2xl bg-white dark:bg-gray-800/90 border border-gray-200 dark:border-gray-700/80 shadow-sm flex items-center justify-between">
          <div>
            <p className="text-2xl font-extrabold text-warning-500">
              {openTasks.filter((t) => t.priority === 'medium').length}
            </p>
            <p className="text-xs font-semibold text-gray-400">Medium</p>
          </div>
          <div className="p-2.5 rounded-xl bg-warning-50 text-warning-500 dark:bg-warning-700/20">
            <AlertTriangle className="w-5 h-5" />
          </div>
        </div>

        <div className="p-4 rounded-2xl bg-white dark:bg-gray-800/90 border border-gray-200 dark:border-gray-700/80 shadow-sm flex items-center justify-between">
          <div>
            <p className="text-2xl font-extrabold text-slate-700 dark:text-slate-200">
              {openTasks.filter((t) => t.priority === 'low').length}
            </p>
            <p className="text-xs font-semibold text-gray-400">Low</p>
          </div>
          <div className="p-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-600">
            <CheckCircle className="w-5 h-5" />
          </div>
        </div>
      </div>

      {/* Filter Tabs */}
      <div className="flex items-center space-x-2 overflow-x-auto pb-2 scrollbar-none">
        {filterLabels.map((label, idx) => {
          const isSelected = selectedFilter === idx;
          return (
            <button
              key={idx}
              onClick={() => setSelectedFilter(idx)}
              className={`px-4 py-2 rounded-full text-xs font-bold whitespace-nowrap transition-all ${
                isSelected
                  ? 'bg-brand-500 text-white shadow-sm'
                  : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:bg-gray-50'
              }`}
            >
              {label}
            </button>
          );
        })}
      </div>

      {/* Loading Skeleton */}
      {isLoading && <TaskSkeleton />}

      {/* Kanban Board — todo cards are blurred until accepted */}
      {!isLoading && openTasks.length > 0 && (
        <KanbanBoard
          tasks={openTasks}
          onEdit={() => {}}
          onDelete={() => {}}
          onToggleStatus={handleToggleStatus}
          blurTodo
          onAccept={handleAcceptTask}
          isAccepting={updateTaskMutation.isPending}
        />
      )}

      {/* Empty State */}
      {!isLoading && openTasks.length === 0 && (
        <EmptyState
          title="Ochiq zayavkalar mavjud emas"
          description="Hozirda barcha zayavkalar mutaxassislar tomonidan qabul qilingan."
          actionLabel="Barchasini ko'rish"
          onAction={() => setSelectedFilter(0)}
        />
      )}
    </div>
  );
};

