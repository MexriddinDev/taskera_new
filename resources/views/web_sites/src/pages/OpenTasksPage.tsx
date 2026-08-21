import React, { useState, useEffect } from 'react';
import { useTasks } from '@/modules/tasks/infrastructure/presentation/hooks/useTasks';
import { useUpdateTask } from '@/modules/tasks/infrastructure/presentation/hooks/useUpdateTask';
import { KanbanBoard } from '@/modules/tasks/infrastructure/presentation/components/KanbanBoard';
import { TaskSkeleton } from '@/modules/tasks/infrastructure/presentation/components/TaskSkeleton';
import { EmptyState } from '@/shared/presentation/components/EmptyState';
import { Task, TaskPriority } from '@/modules/tasks/domain/entities/Task';
import { useCan } from '@/shared/presentation/hooks/useCan';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { useT } from '@/shared/presentation/i18n/i18n';
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
  const t = useT();
  const [selectedFilter, setSelectedFilter] = useState<number>(0);
  const [limitErrorMessage, setLimitErrorMessage] = useState<string | null>(null);

  // Superadmin monitoring data
  const { user } = useCan();
  const isSuperAdmin = user?.role === 'Super Admin' || user?.username === 'admin' || user?.username === 'superadmin';
  const [employeeStats, setEmployeeStats] = useState<EmployeeStat[]>([]);
  const [statsLoading, setStatsLoading] = useState(false);

  const filterLabels = [t('openTasks.filterAll'), t('openTasks.filterCritical'), t('openTasks.filterHigh'), t('openTasks.filterMedium'), t('openTasks.filterLow')];
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
          const msg = err.response?.data?.message || err.message || t('common.errorGeneric');
          setLimitErrorMessage(msg);
        },
      }
    );
  };

  const handleToggleStatus = (task: Task) => {
    if (task.status === 'done') return;
    // Ochiq (todo) → Jarayonda (in_progress); Jarayonda → Bajarilgan (done)
    const isProgressing = task.status === 'todo';
    updateTaskMutation.mutate({
      id: task.id,
      dto: isProgressing ? { status: 'in_progress' } : { status: 'done', completed: true },
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
          <h1 className="text-3xl font-extrabold text-gray-900 dark:text-gray-100">{t('openTasks.title')}</h1>
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
            {t('openTasks.gotIt')}
          </button>
        </div>
      )}



      {/* Stats Row Chips */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div className="p-4 rounded-2xl bg-white dark:bg-gray-800/90 border border-gray-200 dark:border-gray-700/80 shadow-sm flex items-center justify-between">
          <div>
            <p className="text-2xl font-extrabold text-gray-900 dark:text-gray-100">{openTasks.length}</p>
            <p className="text-xs font-semibold text-gray-400">{t('openTasks.open')}</p>
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
            <p className="text-xs font-semibold text-gray-400">{t('openTasks.criticalHigh')}</p>
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
            <p className="text-xs font-semibold text-gray-400">{t('openTasks.medium')}</p>
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
            <p className="text-xs font-semibold text-gray-400">{t('openTasks.low')}</p>
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
          title={t('openTasks.emptyTitle')}
          description={t('openTasks.emptyDesc')}
          actionLabel={t('openTasks.viewAll')}
          onAction={() => setSelectedFilter(0)}
        />
      )}
    </div>
  );
};

