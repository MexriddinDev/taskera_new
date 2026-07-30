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
import { AlertCircle, ChevronLeft, ChevronRight, CheckCircle2, Layers, Cpu, Code } from 'lucide-react';

export const DashboardPage: React.FC = () => {
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebounce(search, 400);

  const [status, setStatus] = useState<TaskStatus | 'all'>('all');
  const [priority, setPriority] = useState<TaskPriority | 'all'>('all');
  const [targetDepartment, setTargetDepartment] = useState<TargetDepartment | 'all'>('all');
  const [viewMode, setViewMode] = useState<'grid' | 'kanban'>('grid');

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

  // Dashboard shows all accepted / closed tickets, but hides brand-new
  // requests that nobody has accepted yet (unassigned todo).
  const visibleTasks = (data?.tasks ?? []).filter(
    (t) => !(t.status === 'todo' && !t.isAssigned)
  );

  return (
    <div className="w-full px-4 sm:px-8 lg:px-12 py-8 space-y-6">
      {/* Welcome Banner */}
      <div className="bg-gradient-to-r from-brand-500 via-brand-600 to-brand-700 text-white rounded-3xl p-6 sm:p-8 shadow-lg flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
        <div>
          <span className="px-3 py-1 bg-white/20 rounded-full text-xs font-semibold backdrop-blur-md">
            Xush kelibsiz 👋
          </span>
          <h1 className="text-2xl sm:text-3xl font-extrabold mt-2">TaskFlow Boshqaruv Paneli</h1>
          <p className="text-sm text-white/80 mt-1 max-w-2xl">
            Barcha zayavkalarni real-vaqt rejimida qabul qiling, nazorat qiling va ijrosini ta'minlang.
          </p>
        </div>

        <div className="flex items-center space-x-3 bg-white/10 p-3.5 rounded-2xl backdrop-blur-md border border-white/20">
          <div className="text-right">
            <p className="text-xs text-white/80 font-medium">Boshqarma Holati</p>
            <p className="text-sm font-bold">Hardware & Software</p>
          </div>
          <div className="w-10 h-10 rounded-xl bg-white text-brand-500 flex items-center justify-center font-bold shadow">
            TF
          </div>
        </div>
      </div>

      {/* Quick Summary Widgets */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div className="p-4 rounded-2xl bg-white dark:bg-gray-800/90 border border-gray-200 dark:border-gray-700/80 shadow-sm flex items-center space-x-3">
          <div className="p-3 rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-950/40">
            <Layers className="w-5 h-5" />
          </div>
          <div>
            <p className="text-xs text-gray-400 font-medium">Jami Zayavkalar</p>
            <p className="text-lg font-bold text-gray-900 dark:text-gray-100">{stats?.total ?? data?.total ?? 0} ta</p>
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
