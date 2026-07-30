import React, { useState } from 'react';
import { useTasks } from '@/modules/tasks/infrastructure/presentation/hooks/useTasks';
import { useUpdateTask } from '@/modules/tasks/infrastructure/presentation/hooks/useUpdateTask';
import { KanbanBoard } from '@/modules/tasks/infrastructure/presentation/components/KanbanBoard';
import { TaskSkeleton } from '@/modules/tasks/infrastructure/presentation/components/TaskSkeleton';
import { EmptyState } from '@/shared/presentation/components/EmptyState';
import { Task, TaskPriority } from '@/modules/tasks/domain/entities/Task';
import { Layers, AlertOctagon, AlertTriangle, CheckCircle } from 'lucide-react';

export const OpenTasksPage: React.FC = () => {
  const [selectedFilter, setSelectedFilter] = useState<number>(0);
  const filterLabels = ['Barchasi', 'Kritik', 'Yuqori', 'O\'rta', 'Past'];

  const priorityMapping: (TaskPriority | 'all')[] = ['all', 'high', 'high', 'medium', 'low'];
  const currentPriority = priorityMapping[selectedFilter];

  const { data, isLoading, refetch } = useTasks({
    status: 'todo',
    priority: currentPriority,
    limit: 50,
  });

  const updateTaskMutation = useUpdateTask();

  const handleAcceptTask = (taskId: number) => {
    // "Qabul qilish": assign to me but keep in todo — it moves to My Tasks (todo).
    updateTaskMutation.mutate(
      { id: taskId, dto: { assignToMe: true } },
      {
        onSuccess: () => {
          refetch();
        },
      }
    );
  };

  const handleToggleStatus = (task: Task) => {
    // A completed ticket must never regress. Only allow forward-close.
    if (task.status === 'done') return;
    updateTaskMutation.mutate({
      id: task.id,
      dto: { status: 'done', completed: true },
    });
  };

  // Open board shows only NOT-yet-accepted (unassigned) todo tickets.
  // Once accepted (assigned), a ticket leaves this board and appears in My Tasks.
  const openTasks = data?.tasks.filter((t) => !t.isAssigned && t.status === 'todo') || [];

  return (
    <div className="w-full px-4 sm:px-8 lg:px-12 py-8 space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-extrabold text-gray-900 dark:text-gray-100">Tasks (Ochiq Zayavkalar)</h1>
          <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
            Yangi zayavkalar yopiq holatda. Ko'rish uchun "Qabul qilish" tugmasini bosing.
          </p>
        </div>
      </div>

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
