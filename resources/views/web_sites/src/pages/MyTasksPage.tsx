import React, { useState } from 'react';
import { useTasks } from '@/modules/tasks/infrastructure/presentation/hooks/useTasks';
import { useUpdateTask } from '@/modules/tasks/infrastructure/presentation/hooks/useUpdateTask';
import { KanbanBoard } from '@/modules/tasks/infrastructure/presentation/components/KanbanBoard';
import { TaskSkeleton } from '@/modules/tasks/infrastructure/presentation/components/TaskSkeleton';
import { EmptyState } from '@/shared/presentation/components/EmptyState';
import { Task, TaskStatus } from '@/modules/tasks/domain/entities/Task';
import { Clock, AlertTriangle, CheckCheck } from 'lucide-react';

export const MyTasksPage: React.FC = () => {
  const [selectedFilter, setSelectedFilter] = useState<number>(0);
  const filterTabs = ['Barchasi', 'Qabul qilingan', 'Jarayonda', 'Qaytarilgan (Rejected)', 'Bajarilgan'];

  const statusMapping: (TaskStatus | 'all')[] = ['all', 'todo', 'in_progress', 'rejected', 'done'];
  const currentStatus = statusMapping[selectedFilter];

  const { data, isLoading } = useTasks({
    scope: 'my_tasks',
    status: currentStatus,
    limit: 50,
  });

  const updateTaskMutation = useUpdateTask();

  const handleToggleStatus = (task: Task) => {
    if (task.status === 'done') return;

    if (task.status === 'todo') {
      // Move from todo to in_progress ("Jarayonga o'tkazish")
      updateTaskMutation.mutate({
        id: task.id,
        dto: { status: 'in_progress' },
      });
    } else {
      // Move from in_progress / rejected to done ("Yakunlash")
      updateTaskMutation.mutate({
        id: task.id,
        dto: { status: 'done', completed: true, solutionComment: 'Vazifa to\'liq bajarildi.' },
      });
    }
  };

  const tasks = data?.tasks || [];

  const summary = {
    accepted: tasks.filter((t) => t.status === 'todo').length,
    inProgress: tasks.filter((t) => t.status === 'in_progress').length,
    rejected: tasks.filter((t) => t.status === 'rejected').length,
    solved: tasks.filter((t) => t.status === 'done').length,
  };

  return (
    <div className="w-full px-4 sm:px-8 lg:px-12 py-8 space-y-6">
       Page Header
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-extrabold text-gray-900 dark:text-gray-100">My Tasks (Mening Zayavkalarim)</h1>
        </div>
      </div>


      {/* Summary Chips Row */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div className="p-4 rounded-2xl bg-white dark:bg-gray-800/90 border border-gray-200 dark:border-gray-700/80 shadow-sm flex items-center justify-between">
          <div>
            <p className="text-xl font-extrabold text-brand-500">{summary.accepted}</p>
            <p className="text-xs font-semibold text-gray-400">Accepted</p>
          </div>
          <div className="p-2.5 rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-950/40">
            <Clock className="w-4 h-4" />
          </div>
        </div>

        <div className="p-4 rounded-2xl bg-white dark:bg-gray-800/90 border border-gray-200 dark:border-gray-700/80 shadow-sm flex items-center justify-between">
          <div>
            <p className="text-xl font-extrabold text-warning-500">{summary.inProgress}</p>
            <p className="text-xs font-semibold text-gray-400">In Progress</p>
          </div>
          <div className="p-2.5 rounded-xl bg-warning-50 text-warning-500 dark:bg-warning-700/20">
            <Clock className="w-4 h-4" />
          </div>
        </div>

        <div className="p-4 rounded-2xl bg-white dark:bg-gray-800/90 border border-gray-200 dark:border-gray-700/80 shadow-sm flex items-center justify-between">
          <div>
            <p className="text-xl font-extrabold text-error-500">{summary.rejected}</p>
            <p className="text-xs font-semibold text-gray-400">Rejected</p>
          </div>
          <div className="p-2.5 rounded-xl bg-error-50 text-error-500 dark:bg-error-700/20">
            <AlertTriangle className="w-4 h-4" />
          </div>
        </div>

        <div className="p-4 rounded-2xl bg-white dark:bg-gray-800/90 border border-gray-200 dark:border-gray-700/80 shadow-sm flex items-center justify-between">
          <div>
            <p className="text-xl font-extrabold text-success-500">{summary.solved}</p>
            <p className="text-xs font-semibold text-gray-400">Solved</p>
          </div>
          <div className="p-2.5 rounded-xl bg-success-50 text-success-500 dark:bg-success-700/20">
            <CheckCheck className="w-4 h-4" />
          </div>
        </div>
      </div>

      {/* Filter Tabs */}
      <div className="flex items-center space-x-2 overflow-x-auto pb-2 scrollbar-none">
        {filterTabs.map((tab, idx) => {
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
              {tab}
            </button>
          );
        })}
      </div>

      {/* Loading Skeleton */}
      {isLoading && <TaskSkeleton />}

      {/* Kanban Board — accepted tasks are fully visible here */}
      {!isLoading && tasks.length > 0 && (
        <KanbanBoard
          tasks={tasks}
          onEdit={() => {}}
          onDelete={() => {}}
          onToggleStatus={handleToggleStatus}
        />
      )}

      {/* Empty State */}
      {!isLoading && tasks.length === 0 && (
        <EmptyState
          title="Qabul qilingan zayavkalar yo'q"
          description="Siz hali hech qanday zayavkani o'zingizga biriktirmagansiz."
          actionLabel="Barchasini ko'rish"
          onAction={() => setSelectedFilter(0)}
        />
      )}
    </div>
  );
};
