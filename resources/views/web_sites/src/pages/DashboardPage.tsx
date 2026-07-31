import React, { useState, useEffect } from 'react';
import { useTasks } from '@/modules/tasks/infrastructure/presentation/hooks/useTasks';
import { useCreateTask } from '@/modules/tasks/infrastructure/presentation/hooks/useCreateTask';
import { useUpdateTask } from '@/modules/tasks/infrastructure/presentation/hooks/useUpdateTask';
import { useDeleteTask } from '@/modules/tasks/infrastructure/presentation/hooks/useDeleteTask';
import { TaskFilter } from '@/modules/tasks/infrastructure/presentation/components/TaskFilter';
import { TaskCard } from '@/modules/tasks/infrastructure/presentation/components/TaskCard';
import { KanbanBoard } from '@/modules/tasks/infrastructure/presentation/components/KanbanBoard';
import { TaskSkeleton } from '@/modules/tasks/infrastructure/presentation/components/TaskSkeleton';
import { TaskFormModal } from '@/modules/tasks/infrastructure/presentation/components/TaskFormModal';
import { TaskDeleteDialog } from '@/modules/tasks/infrastructure/presentation/components/TaskDeleteDialog';
import { EmptyState } from '@/shared/presentation/components/EmptyState';
import { Button } from '@/shared/presentation/components/Button';
import { useDebounce } from '@/shared/presentation/hooks/useDebounce';
import { Task, TaskPriority, TaskStatus, TargetDepartment } from '@/modules/tasks/domain/entities/Task';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { AlertCircle, ChevronLeft, ChevronRight, CheckCircle2, Layers, Cpu, Code, Calendar, Search, Clock } from 'lucide-react';

export const DashboardPage: React.FC = () => {
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebounce(search, 400);

  const [status, setStatus] = useState<TaskStatus | 'all'>('all');
  const [priority, setPriority] = useState<TaskPriority | 'all'>('all');
  const [targetDepartment, setTargetDepartment] = useState<TargetDepartment | 'all'>('all');
  const [viewMode, setViewMode] = useState<'grid' | 'kanban'>('kanban');

  const [page, setPage] = useState(1);
  const pageSize = 16;

  // Modals state
  const [isFormOpen, setIsFormOpen] = useState(false);
  const [editingTask, setEditingTask] = useState<Task | null>(null);
  const [deletingId, setDeletingId] = useState<number | null>(null);

  // Stats from API
  const [stats, setStats] = useState<{ total: number; completed: number; hardware: number; software: number } | null>(null);

  useEffect(() => {
    axiosClient.get('/tickets/stats').then((res) => {
      if (res.data) setStats(res.data);
    }).catch(() => {});
  }, []);

  // Date Range Filter State (Default: Bugungi kun / Today)
  const todayStr = new Date().toISOString().split('T')[0];
  const [startDate, setStartDate] = useState<string>(todayStr);
  const [endDate, setEndDate] = useState<string>(todayStr);
  const [preset, setPreset] = useState<'today' | 'yesterday' | 'week' | 'month' | 'all'>('today');

  const handleApplyPreset = (p: 'today' | 'yesterday' | 'week' | 'month' | 'all') => {
    setPreset(p);
    const now = new Date();
    if (p === 'today') {
      setStartDate(todayStr);
      setEndDate(todayStr);
    } else if (p === 'yesterday') {
      const y = new Date(now);
      y.setDate(y.getDate() - 1);
      const yStr = y.toISOString().split('T')[0];
      setStartDate(yStr);
      setEndDate(yStr);
    } else if (p === 'week') {
      const w = new Date(now);
      w.setDate(w.getDate() - 7);
      setStartDate(w.toISOString().split('T')[0]);
      setEndDate(todayStr);
    } else if (p === 'month') {
      const m = new Date(now.getFullYear(), now.getMonth(), 1);
      setStartDate(m.toISOString().split('T')[0]);
      setEndDate(todayStr);
    } else {
      setStartDate('');
      setEndDate('');
    }
    setPage(1);
  };

  // TanStack Query custom hooks
  const {
    data,
    isLoading,
    isError,
    error,
    refetch,
  } = useTasks({
    search: debouncedSearch,
    status,
    priority,
    targetDepartment,
    startDate: startDate || undefined,
    endDate: endDate || undefined,
    limit: pageSize,
    skip: (page - 1) * pageSize,
  });

  const createTaskMutation = useCreateTask();
  const updateTaskMutation = useUpdateTask();
  const deleteTaskMutation = useDeleteTask();

  const handleCreateOrUpdate = (formData: { todo: string; status: TaskStatus; priority: TaskPriority }) => {
    if (editingTask) {
      updateTaskMutation.mutate(
        { id: editingTask.id, dto: formData },
        {
          onSuccess: () => {
            setIsFormOpen(false);
            setEditingTask(null);
          },
        }
      );
    } else {
      createTaskMutation.mutate(formData, {
        onSuccess: () => {
          setIsFormOpen(false);
        },
      });
    }
  };

  const handleToggleStatus = (task: Task) => {
    // A completed ticket must never regress. Only allow forward-close.
    if (task.status === 'done') return;
    updateTaskMutation.mutate({
      id: task.id,
      dto: { status: 'done', completed: true },
    });
  };

  const handleConfirmDelete = () => {
    if (deletingId) {
      deleteTaskMutation.mutate(deletingId, {
        onSuccess: () => {
          setDeletingId(null);
        },
      });
    }
  };

  const totalPages = data ? Math.ceil(data.total / pageSize) : 1;

  // Visible tasks on Dashboard (all tasks except brand-new unaccepted ones, or all depending on filter)
  const visibleTasks = data?.tasks ?? [];

  return (
    <div className="w-full px-4 sm:px-8 lg:px-12 py-8 space-y-6">
      {/* Date Range Filter Bar (Replacing old static banner) */}
      <div className="bg-white dark:bg-slate-800/90 rounded-3xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
        <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
          <div className="space-y-1">
            <div className="flex items-center space-x-2">
              <Calendar className="w-5 h-5 text-brand-500" />
              <h2 className="text-xl font-extrabold text-slate-900 dark:text-slate-100">
                Dashboard Vaqt Bo'yicha Filtr
              </h2>
            </div>
            <p className="text-xs text-slate-500 dark:text-slate-400">
              Standart holatda faqat bugun yopilgan zayavkalar ko'rsatiladi. Kerakli sana oralig'ini tanlab qidirishingiz mumkin.
            </p>
          </div>

          {/* Preset Buttons */}
          <div className="flex flex-wrap items-center gap-2">
            {[
              { id: 'today', label: 'Bugun' },
              { id: 'yesterday', label: 'Kechagi kun' },
              { id: 'week', label: 'Shu hafta' },
              { id: 'month', label: 'Shu oy' },
              { id: 'all', label: 'Barchasi' },
            ].map((item) => (
              <button
                key={item.id}
                onClick={() => handleApplyPreset(item.id as any)}
                className={`px-3.5 py-1.5 rounded-full text-xs font-bold transition-all ${
                  preset === item.id
                    ? 'bg-brand-500 text-white shadow-sm'
                    : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-200'
                }`}
              >
                {item.label}
              </button>
            ))}
          </div>
        </div>

        {/* Date Inputs Controls */}
        <div className="flex flex-col sm:flex-row items-center space-y-3 sm:space-y-0 sm:space-x-3 pt-2 border-t border-slate-100 dark:border-slate-700/60">
          <div className="flex items-center space-x-2 w-full sm:w-auto">
            <span className="text-xs font-bold text-slate-500">Boshlanish:</span>
            <input
              type="date"
              value={startDate}
              onChange={(e) => { setStartDate(e.target.value); setPreset('all'); setPage(1); }}
              className="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-bold text-slate-800 dark:text-slate-200 shadow-sm focus:ring-2 focus:ring-brand-500"
            />
          </div>

          <div className="flex items-center space-x-2 w-full sm:w-auto">
            <span className="text-xs font-bold text-slate-500">Tugash:</span>
            <input
              type="date"
              value={endDate}
              onChange={(e) => { setEndDate(e.target.value); setPreset('all'); setPage(1); }}
              className="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-bold text-slate-800 dark:text-slate-200 shadow-sm focus:ring-2 focus:ring-brand-500"
            />
          </div>

          <button
            onClick={() => { setPage(1); refetch(); }}
            className="w-full sm:w-auto inline-flex items-center justify-center space-x-2 px-5 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-white font-extrabold text-xs shadow-md transition-all cursor-pointer"
          >
            <Search className="w-4 h-4" />
            <span>Qidirish / Filtr</span>
          </button>
        </div>
      </div>

      {/* Quick Summary Widgets */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div className="p-4 rounded-2xl bg-white dark:bg-gray-800/90 border border-gray-200 dark:border-gray-700/80 shadow-sm flex items-center space-x-3">
          <div className="p-3 rounded-xl bg-success-50 text-success-500 dark:bg-success-700/20">
            <CheckCircle2 className="w-5 h-5" />
          </div>
          <div>
            <p className="text-xs text-gray-400 font-medium">Yopilgan Zayavkalar</p>
            <p className="text-lg font-bold text-gray-900 dark:text-gray-100">{stats?.completed ?? data?.total ?? 0} ta</p>
          </div>
        </div>

        <div className="p-4 rounded-2xl bg-white dark:bg-gray-800/90 border border-gray-200 dark:border-gray-700/80 shadow-sm flex items-center space-x-3">
          <div className="p-3 rounded-xl bg-success-50 text-success-500 dark:bg-success-700/20">
            <CheckCircle2 className="w-5 h-5" />
          </div>
          <div>
            <p className="text-xs text-gray-400 font-medium">Bajarilgan</p>
            <p className="text-lg font-bold text-gray-900 dark:text-gray-100">{stats?.completed ?? 0} ta</p>
          </div>
        </div>

        <div className="p-4 rounded-2xl bg-white dark:bg-gray-800/90 border border-gray-200 dark:border-gray-700/80 shadow-sm flex items-center space-x-3">
          <div className="p-3 rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-950/40">
            <Cpu className="w-5 h-5" />
          </div>
          <div>
            <p className="text-xs text-gray-400 font-medium">Hardware</p>
            <p className="text-lg font-bold text-gray-900 dark:text-gray-100">{stats?.hardware ?? 0} ta</p>
          </div>
        </div>

        <div className="p-4 rounded-2xl bg-white dark:bg-gray-800/90 border border-gray-200 dark:border-gray-700/80 shadow-sm flex items-center space-x-3">
          <div className="p-3 rounded-xl bg-purple-50 text-purple-600 dark:bg-purple-950/40">
            <Code className="w-5 h-5" />
          </div>
          <div>
            <p className="text-xs text-gray-400 font-medium">Software</p>
            <p className="text-lg font-bold text-gray-900 dark:text-gray-100">{stats?.software ?? 0} ta</p>
          </div>
        </div>
      </div>

      {/* Search & Filter Bar with View Mode Toggle */}
      <TaskFilter
        search={search}
        onSearchChange={(val) => {
          setSearch(val);
          setPage(1);
        }}
        status={status}
        onStatusChange={(val) => {
          setStatus(val);
          setPage(1);
        }}
        priority={priority}
        onPriorityChange={(val) => {
          setPriority(val);
          setPage(1);
        }}
        targetDepartment={targetDepartment}
        onDepartmentChange={(val) => {
          setTargetDepartment(val);
          setPage(1);
        }}
        viewMode={viewMode}
        onViewModeChange={(mode) => setViewMode(mode)}
        onCreateClick={() => {
          setEditingTask(null);
          setIsFormOpen(true);
        }}
      />

      {/* Error State */}
      {isError && (
        <div className="p-6 rounded-2xl bg-error-50 dark:bg-error-700/20 border border-error-500/30 text-center">
          <AlertCircle className="w-8 h-8 text-error-500 mx-auto mb-2" />
          <h3 className="text-lg font-bold text-error-500">Zayavkalarni yuklashda xatolik</h3>
          <p className="text-xs text-error-500/90 mb-4">{error?.message}</p>
          <Button variant="danger" onClick={() => refetch()}>
            Qayta Urinish
          </Button>
        </div>
      )}

      {/* Loading Skeleton */}
      {isLoading && <TaskSkeleton />}

      {/* Grid View vs Kanban View Rendering */}
      {!isLoading && !isError && data && visibleTasks.length > 0 && (
        <>
          {viewMode === 'grid' ? (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
              {visibleTasks.map((task) => (
                <TaskCard
                  key={task.id}
                  task={task}
                  onEdit={(t) => {
                    setEditingTask(t);
                    setIsFormOpen(true);
                  }}
                  onDelete={(id) => setDeletingId(id)}
                  onToggleStatus={handleToggleStatus}
                />
              ))}
            </div>
          ) : (
            <KanbanBoard
              tasks={visibleTasks}
              onEdit={(t) => {
                setEditingTask(t);
                setIsFormOpen(true);
              }}
              onDelete={(id) => setDeletingId(id)}
              onToggleStatus={handleToggleStatus}
            />
          )}

          {/* Pagination Controls */}
          <div className="mt-8 flex items-center justify-between border-t border-gray-200 dark:border-gray-800 pt-6">
            <span className="text-xs font-semibold text-gray-500 dark:text-gray-400">
              Sahifa <span className="font-bold text-gray-900 dark:text-gray-100">{page}</span> /{' '}
              <span className="font-bold text-gray-900 dark:text-gray-100">{totalPages}</span> ({data.total} ta zayavka)
            </span>

            <div className="flex items-center space-x-2">
              <Button
                variant="secondary"
                size="sm"
                disabled={page <= 1}
                onClick={() => setPage((p) => Math.max(1, p - 1))}
                leftIcon={<ChevronLeft className="w-4 h-4" />}
              >
                Oldingi
              </Button>
              <Button
                variant="secondary"
                size="sm"
                disabled={page >= totalPages}
                onClick={() => setPage((p) => p + 1)}
              >
                Keyingi <ChevronRight className="w-4 h-4 ml-1 inline" />
              </Button>
            </div>
          </div>
        </>
      )}

      {/* Empty State */}
      {!isLoading && !isError && data && visibleTasks.length === 0 && (
        <EmptyState
          title="Zayavkalar topilmadi"
          description="Filtr parametrlarini o'zgartiring yoki qidiruv so'rovini tozalab ko'ring."
          actionLabel="Filtrlarni Tozalash"
          onAction={() => {
            setSearch('');
            setStatus('all');
            setPriority('all');
            setTargetDepartment('all');
            setPage(1);
          }}
        />
      )}

      {/* Task Create / Edit Modal */}
      <TaskFormModal
        isOpen={isFormOpen}
        onClose={() => {
          setIsFormOpen(false);
          setEditingTask(null);
        }}
        onSubmit={handleCreateOrUpdate}
        taskToEdit={editingTask}
        isLoading={createTaskMutation.isPending || updateTaskMutation.isPending}
      />

      {/* Delete Confirmation Modal */}
      <TaskDeleteDialog
        isOpen={deletingId !== null}
        onClose={() => setDeletingId(null)}
        onConfirm={handleConfirmDelete}
        isLoading={deleteTaskMutation.isPending}
      />
    </div>
  );
};
