import React, { useState, useEffect } from 'react';
import { useTasks } from '@/modules/tasks/infrastructure/presentation/hooks/useTasks';
import { useCreateTask } from '@/modules/tasks/infrastructure/presentation/hooks/useCreateTask';
import { useTaskActions } from '@/modules/tasks/infrastructure/presentation/hooks/useTaskActions';
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
import { useT } from '@/shared/presentation/i18n/i18n';
import { AlertCircle, ChevronLeft, ChevronRight, CheckCircle2, Layers, Cpu, Code, Calendar, Search, Clock } from 'lucide-react';
import { SolveTaskModal } from '@/modules/tasks/infrastructure/presentation/components/SolveTaskModal';
import { StaffFilterStrip, useStaffAvatars } from '@/modules/tasks/infrastructure/presentation/components/StaffFilterStrip';
import { AssignTaskModal } from '@/modules/tasks/infrastructure/presentation/components/AssignTaskModal';
import { useCan } from '@/shared/presentation/hooks/useCan';

export const DashboardPage: React.FC = () => {
  const t = useT();
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebounce(search, 400);

  const [status, setStatus] = useState<TaskStatus | 'all'>('all');
  const [priority, setPriority] = useState<TaskPriority | 'all'>('all');
  const [targetDepartment, setTargetDepartment] = useState<TargetDepartment | 'all'>('all');
  const [viewMode, setViewMode] = useState<'grid' | 'kanban'>('kanban');

  const [page, setPage] = useState(1);
  const pageSize = 16;

  // Xodim bo'yicha filtr — "Jamoa yuklamasi" dagi kabi avatarlar qatori.
  const [selectedStaffId, setSelectedStaffId] = useState<number | null>(null);
  const { employees: staffAvatars, isLoading: isStaffLoading, refresh: refreshStaff } = useStaffAvatars();

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
  //
  // toISOString() UTC ga o'tkazadi — Toshkent vaqti bilan ertalabki soatlarda
  // sana bir kun orqaga siljib ketardi. Shuning uchun mahalliy sana yig'iladi.
  const toLocalDateStr = (d: Date): string =>
    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

  /** Joriy hafta boshi — dushanba. */
  const startOfWeek = (d: Date): Date => {
    const start = new Date(d);
    const dayFromMonday = (start.getDay() + 6) % 7; // yakshanba (0) → 6
    start.setDate(start.getDate() - dayFromMonday);
    return start;
  };

  const todayStr = toLocalDateStr(new Date());
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
      const yStr = toLocalDateStr(y);
      setStartDate(yStr);
      setEndDate(yStr);
    } else if (p === 'week') {
      // "Shu hafta" = joriy kalendar hafta (dushanbadan bugungacha).
      // Ilgari bu "oxirgi 7 kun" edi — o'tgan haftadagi zayavkalar ham tushardi.
      setStartDate(toLocalDateStr(startOfWeek(now)));
      setEndDate(todayStr);
    } else if (p === 'month') {
      const m = new Date(now.getFullYear(), now.getMonth(), 1);
      setStartDate(toLocalDateStr(m));
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
    dateField: 'resolved_at',
    limit: pageSize,
    skip: (page - 1) * pageSize,
  });

  // Dispetcher ustuni — biriktirish huquqi bo'lganlarga (admin/superadmin).
  // Alohida so'rov: taxtadagi asosiy ro'yxat sana oralig'i va sahifalash bilan
  // cheklangan, qabul qilinmagan zayavkalar esa TO'LIQ ko'rinishi kerak.
  const { can } = useCan();
  const canAssign = can('tickets.assign');
  const [assigningTask, setAssigningTask] = useState<Task | null>(null);

  const { data: unassignedData, refetch: refetchUnassigned } = useTasks(
    {
      status: 'todo',
      search: debouncedSearch,
      priority,
      targetDepartment,
      limit: 50,
    },
    { enabled: canAssign }
  );

  // Qabul qilingan (mas'ul xodimi bor) zayavka bu ustunda turmaydi.
  const unassignedTasks = (unassignedData?.tasks ?? []).filter((task) => !task.assignedUserId);

  const createTaskMutation = useCreateTask();
  const { toggleStatus, mutation: updateTaskMutation } = useTaskActions();
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

  // Zayavkani yopish uchun yechim izohi majburiy — oyna orqali so'raladi.
  const [solvingTask, setSolvingTask] = useState<Task | null>(null);

  const handleToggleStatus = (task: Task) => {
    // Umumiy oqim: todo/rejected → in_progress; in_progress → done
    toggleStatus(task, setSolvingTask);
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
  const allTasks = data?.tasks ?? [];
  const visibleTasks = selectedStaffId !== null
    ? allTasks.filter((task) => task.assignedUserId === selectedStaffId)
    : allTasks;

  // Xodim bo'yicha filtr yoqilganda ustun ma'nosini yo'qotadi: qabul
  // qilinmagan zayavkaning mas'uli yo'q, ya'ni hech bir xodimga tegishli emas.
  const showUnassignedColumn = canAssign && selectedStaffId === null;
  const boardHasContent = visibleTasks.length > 0
    || (viewMode === 'kanban' && showUnassignedColumn && unassignedTasks.length > 0);

  return (
    <div className="w-full px-4 sm:px-8 lg:px-12 py-8 space-y-6">
      {/* Xodimlar bo'yicha filtr qatori */}
      <StaffFilterStrip
        employees={staffAvatars}
        selectedUserId={selectedStaffId}
        onSelect={(userId) => { setSelectedStaffId(userId); setPage(1); }}
        onRefresh={() => { refreshStaff(); refetch(); }}
        isRefreshing={isStaffLoading}
      />
      {/* Date Range Filter Bar (Replacing old static banner) */}
      <div className="bg-white dark:bg-slate-800/90 rounded-3xl p-6 border border-slate-200 dark:border-slate-700 shadow-sm space-y-4">
        <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
          <div className="flex items-center space-x-2">
            <Calendar className="w-5 h-5 text-brand-500" />
            <h2 className="text-xl font-extrabold text-slate-900 dark:text-slate-100">
              {t('dashboard.filterTitle')}
            </h2>
          </div>

          {/* Preset Buttons */}
          <div className="flex flex-wrap items-center gap-2">
            {[
              { id: 'today', label: t('dashboard.presetToday') },
              { id: 'yesterday', label: t('dashboard.presetYesterday') },
              { id: 'week', label: t('dashboard.presetWeek') },
              { id: 'month', label: t('dashboard.presetMonth') },
              { id: 'all', label: t('dashboard.presetAll') },
            ].map((item) => (
              <button
                key={item.id}
                type="button"
                onClick={() => handleApplyPreset(item.id as any)}
                aria-pressed={preset === item.id}
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
            <label htmlFor="dashboard-start-date" className="text-xs font-bold text-slate-500">{t('dashboard.startDate')}</label>
            <input
              id="dashboard-start-date"
              type="date"
              value={startDate}
              onChange={(e) => { setStartDate(e.target.value); setPreset('all'); setPage(1); }}
              className="px-3 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-xs font-bold text-slate-800 dark:text-slate-200 shadow-sm focus:ring-2 focus:ring-brand-500"
            />
          </div>

          <div className="flex items-center space-x-2 w-full sm:w-auto">
            <label htmlFor="dashboard-end-date" className="text-xs font-bold text-slate-500">{t('dashboard.endDate')}</label>
            <input
              id="dashboard-end-date"
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
            <span>{t('dashboard.searchFilter')}</span>
          </button>
        </div>
      </div>

      {/* Quick Summary Widgets */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div className="p-4 rounded-2xl bg-white dark:bg-gray-800/90 border border-gray-200 dark:border-gray-700/80 shadow-sm flex items-center space-x-3">
          <div className="p-3 rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-950/40">
            <Layers className="w-5 h-5" />
          </div>
          <div>
            <p className="text-xs text-gray-500 dark:text-gray-400 font-medium">{t('dashboard.totalTickets')}</p>
            <p className="text-lg font-bold text-gray-900 dark:text-gray-100">{stats?.total ?? data?.total ?? 0} {t('dashboard.countUnit')}</p>
          </div>
        </div>

        <div className="p-4 rounded-2xl bg-white dark:bg-gray-800/90 border border-gray-200 dark:border-gray-700/80 shadow-sm flex items-center space-x-3">
          <div className="p-3 rounded-xl bg-success-50 text-success-500 dark:bg-success-700/20">
            <CheckCircle2 className="w-5 h-5" />
          </div>
          <div>
            <p className="text-xs text-gray-400 font-medium">{t('dashboard.completed')}</p>
            <p className="text-lg font-bold text-gray-900 dark:text-gray-100">{stats?.completed ?? 0} {t('dashboard.countUnit')}</p>
          </div>
        </div>

        <div className="p-4 rounded-2xl bg-white dark:bg-gray-800/90 border border-gray-200 dark:border-gray-700/80 shadow-sm flex items-center space-x-3">
          <div className="p-3 rounded-xl bg-brand-50 text-brand-500 dark:bg-brand-950/40">
            <Cpu className="w-5 h-5" />
          </div>
          <div>
            <p className="text-xs text-gray-400 font-medium">{t('dashboard.hardware')}</p>
            <p className="text-lg font-bold text-gray-900 dark:text-gray-100">{stats?.hardware ?? 0} {t('dashboard.countUnit')}</p>
          </div>
        </div>

        <div className="p-4 rounded-2xl bg-white dark:bg-gray-800/90 border border-gray-200 dark:border-gray-700/80 shadow-sm flex items-center space-x-3">
          <div className="p-3 rounded-xl bg-purple-50 text-purple-600 dark:bg-purple-950/40">
            <Code className="w-5 h-5" />
          </div>
          <div>
            <p className="text-xs text-gray-400 font-medium">{t('dashboard.software')}</p>
            <p className="text-lg font-bold text-gray-900 dark:text-gray-100">{stats?.software ?? 0} {t('dashboard.countUnit')}</p>
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
        hideStatus
      />

      {/* Error State */}
      {isError && (
        <div className="p-6 rounded-2xl bg-error-50 dark:bg-error-700/20 border border-error-500/30 text-center">
          <AlertCircle className="w-8 h-8 text-error-500 mx-auto mb-2" />
          <h3 className="text-lg font-bold text-error-500">{t('dashboard.loadErrorTitle')}</h3>
          <p className="text-xs text-error-500/90 mb-4">{error?.message}</p>
          <Button variant="danger" onClick={() => refetch()}>
            {t('common.retry')}
          </Button>
        </div>
      )}

      {/* Loading Skeleton */}
      {isLoading && <TaskSkeleton />}

      {/* Grid View vs Kanban View Rendering */}
      {!isLoading && !isError && data && boardHasContent && (
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
              unassignedTasks={showUnassignedColumn ? unassignedTasks : undefined}
              onAssign={(task) => setAssigningTask(task)}
            />
          )}

          {/* Pagination Controls — faqat Grid ko'rinishida.
              Kanban taxtasi ustunlar ichida o'z scrolli bilan ishlaydi,
              u yerda sahifalash chalkashtiradi. */}
          {viewMode === 'grid' && (
          <div className="mt-8 flex items-center justify-between border-t border-gray-200 dark:border-gray-800 pt-6">
            <span className="text-xs font-semibold text-gray-500 dark:text-gray-400">
              {t('dashboard.page')} <span className="font-bold text-gray-900 dark:text-gray-100">{page}</span> /{' '}
              <span className="font-bold text-gray-900 dark:text-gray-100">{totalPages}</span> {t('dashboard.ticketsCount', { count: data.total })}
            </span>

            <div className="flex items-center space-x-2">
              <Button
                variant="secondary"
                size="sm"
                disabled={page <= 1}
                onClick={() => setPage((p) => Math.max(1, p - 1))}
                leftIcon={<ChevronLeft className="w-4 h-4" />}
              >
                {t('dashboard.previous')}
              </Button>
              <Button
                variant="secondary"
                size="sm"
                disabled={page >= totalPages}
                onClick={() => setPage((p) => p + 1)}
              >
                {t('dashboard.next')} <ChevronRight className="w-4 h-4 ml-1 inline" />
              </Button>
            </div>
          </div>
          )}
        </>
      )}

      {/* Empty State */}
      {!isLoading && !isError && data && !boardHasContent && (
        <EmptyState
          title={t('dashboard.noTickets')}
          description={t('dashboard.noTicketsDesc')}
          actionLabel={t('dashboard.clearFilters')}
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

      {/* Zayavkani support xodimiga biriktirish (dispetcher ustuni) */}
      <AssignTaskModal
        task={assigningTask}
        isOpen={assigningTask !== null}
        onClose={() => setAssigningTask(null)}
        onAssigned={() => {
          refetchUnassigned();
          refetch();
        }}
      />

      {/* Yakunlash — yechim izohi majburiy */}
      <SolveTaskModal
        task={solvingTask}
        isOpen={solvingTask !== null}
        onClose={() => setSolvingTask(null)}
        onSuccess={() => {
          setSolvingTask(null);
          refetch();
        }}
      />
    </div>
  );
};
