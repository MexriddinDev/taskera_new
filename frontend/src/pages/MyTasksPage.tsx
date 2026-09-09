import React, { useState } from 'react';
import { useTasks } from '@/modules/tasks/infrastructure/presentation/hooks/useTasks';
import { useTaskActions } from '@/modules/tasks/infrastructure/presentation/hooks/useTaskActions';
import { KanbanBoard } from '@/modules/tasks/infrastructure/presentation/components/KanbanBoard';
import { TaskSkeleton } from '@/modules/tasks/infrastructure/presentation/components/TaskSkeleton';
import { EmptyState } from '@/shared/presentation/components/EmptyState';
import { Task, TaskStatus } from '@/modules/tasks/domain/entities/Task';
import { Clock, AlertTriangle, CheckCheck, Lock } from 'lucide-react';
import { useT } from '@/shared/presentation/i18n/i18n';
import { useToastStore } from '@/shared/presentation/store/useToastStore';
import { SolveTaskModal } from '@/modules/tasks/infrastructure/presentation/components/SolveTaskModal';
import { StaffFilterStrip, useStaffAvatars } from '@/modules/tasks/infrastructure/presentation/components/StaffFilterStrip';

export const MyTasksPage: React.FC = () => {
  const t = useT();
  const toast = useToastStore();
  const [selectedFilter, setSelectedFilter] = useState<number>(0);
  const [selectedStaffId, setSelectedStaffId] = useState<number | null>(null);
  const { employees: staffAvatars, isLoading: isStaffLoading, refresh: refreshStaff } = useStaffAvatars();
  const filterTabs = [t('myTasks.filterAll'), t('status.inProgress'), t('myTasks.filterRejected'), t('status.done')];

  const statusMapping: (TaskStatus | 'all')[] = ['all', 'in_progress', 'rejected', 'done'];
  const currentStatus = statusMapping[selectedFilter];

  // My own accepted tickets
  const { data, isLoading, refetch, isError } = useTasks({
    scope: 'my_tasks',
    status: 'all',
    limit: 50,
  });

  // Xodim tanlanganda foydalanuvchiga ko'rishga ruxsat etilgan jamoa
  // zayavkalari olinadi; tanlanmagan holatda sahifa avvalgidek faqat o'ziniki.
  const {
    data: staffTasksData,
    isLoading: isStaffTasksLoading,
    isError: isStaffTasksError,
    refetch: refetchStaffTasks,
  } = useTasks({ status: 'all', limit: 50 });

  // In Queue — unassigned incoming tickets, visible to everyone with permission
  const { data: queueData, isLoading: isQueueLoading, isError: isQueueError, refetch: refetchQueue } = useTasks({
    status: 'todo',
    limit: 50,
  });

  const [acceptingTaskId, setAcceptingTaskId] = useState<number | null>(null);

  const { toggleStatus, mutation: updateTaskMutation } = useTaskActions();

  // Zayavkani yopish uchun yechim izohi majburiy — oyna orqali so'raladi.
  const [solvingTask, setSolvingTask] = useState<Task | null>(null);

  const handleToggleStatus = (task: Task) => {
    toggleStatus(task, setSolvingTask);
  };

  const handleAcceptTask = (taskId: number) => {
    setAcceptingTaskId(taskId);
    updateTaskMutation.mutate(
      { id: taskId, dto: { assignToMe: true } },
      {
        // onSuccess'da qo'lda refetch qilinmaydi: useUpdateTask allaqachon
        // optimistik yangilab, so'ng ['tasks'] so'rovlarini yangilaydi.
        // Ikkinchi marta chaqirish har bosishda ortiqcha 2 ta so'rov edi.
        onError: (err: any) => {
          const msg = err.response?.data?.message || err.message || t('common.errorGeneric');
          // Sabab (masalan yopilmagan qaytarilgan zayavka) ekran ustidagi
          // bildirishnomada chiqadi — ilgari sahifa ichidagi qutida edi va
          // pastroqda ishlayotgan xodim uni umuman ko'rmasligi mumkin edi.
          toast.showToast({
            type: 'error',
            title: t('myTasks.acceptBlocked'),
            message: msg,
          });
        },
        onSettled: () => {
          setAcceptingTaskId(null);
        },
      }
    );
  };

  const ownTasks = data?.tasks || [];
  const allTasks = selectedStaffId === null ? ownTasks : (staffTasksData?.tasks || []);
  const staffFilteredTasks = selectedStaffId === null
    ? allTasks
    : allTasks.filter((task) => task.assignedUserId === selectedStaffId);
  // "Jarayonda" tabi rad etilganlarni ham ko'rsatadi: ular endi shu ustunda
  // turadi va xodimning ochiq ishi hisoblanadi. Faqat rad etilganlarni ko'rish
  // uchun alohida "Qaytarilgan" tabi bor.
  const tasks = currentStatus === 'all'
    ? staffFilteredTasks
    : currentStatus === 'in_progress'
      ? staffFilteredTasks.filter((task) => task.status === 'in_progress' || task.status === 'rejected')
      : staffFilteredTasks.filter((task) => task.status === currentStatus);
  const queueTasks = (queueData?.tasks || []).filter((t) => !t.isAssigned && t.status === 'todo');
  const visibleQueueTasks = selectedStaffId === null && currentStatus === 'all' ? queueTasks : [];

  // Yopilmagan qaytarilgan zayavka navbatni qulflaydi — backend'dagi qoidaning
  // aynan o'zi (TicketController::update). Filtrdan qat'i nazar `allTasks`
  // bo'yicha hisoblanadi, aks holda boshqa tab tanlanganda blok yo'qolardi.
  const hasOpenRejected = ownTasks.some((task) => task.status === 'rejected');
  const taskListLoading = isLoading || (selectedStaffId !== null && isStaffTasksLoading);
  const taskListError = isError || (selectedStaffId !== null && isStaffTasksError);

  const summary = {
    queue: selectedStaffId === null ? queueTasks.length : 0,
    inProgress: staffFilteredTasks.filter((t) => t.status === 'in_progress').length,
    rejected: staffFilteredTasks.filter((t) => t.status === 'rejected').length,
    solved: staffFilteredTasks.filter((t) => t.status === 'done').length,
  };

  return (
    <div className="w-full px-4 sm:px-8 lg:px-12 py-8 space-y-6">
      <StaffFilterStrip
        employees={staffAvatars}
        selectedUserId={selectedStaffId}
        onSelect={setSelectedStaffId}
        onRefresh={() => { refreshStaff(); refetch(); refetchStaffTasks(); refetchQueue(); }}
        isRefreshing={isStaffLoading}
      />

      {/* Page Header */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-extrabold text-gray-900 dark:text-gray-100">{t('myTasks.title')}</h1>
        </div>
      </div>

      {/* Summary Chips Row.
          Har bir karta ramkasi o'z ko'rsatkichi rangida — raqam, ikonka va
          ramka bitta rangda bo'lgani uchun ko'z bir qarashda ajratadi.
          Kanban kartochkalaridagi (TaskCard) rang tizimi bilan bir xil. */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div className="p-4 rounded-2xl bg-white dark:bg-gray-800/90 border-2 border-slate-300 dark:border-slate-700 shadow-sm flex items-center justify-between">
          <div>
            <p className="text-xl font-extrabold text-slate-600 dark:text-slate-300">{summary.queue}</p>
            <p className="text-xs font-semibold text-gray-400">{t('myTasks.inQueue')}</p>
          </div>
          <div className="p-2.5 rounded-xl bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300">
            <Lock className="w-4 h-4" />
          </div>
        </div>

        <div className="p-4 rounded-2xl bg-white dark:bg-gray-800/90 border-2 border-warning-300 dark:border-warning-700 shadow-sm flex items-center justify-between">
          <div>
            <p className="text-xl font-extrabold text-warning-500">{summary.inProgress}</p>
            <p className="text-xs font-semibold text-gray-400">{t('myTaskCard.inProgress')}</p>
          </div>
          <div className="p-2.5 rounded-xl bg-warning-50 text-warning-500 dark:bg-warning-700/20">
            <Clock className="w-4 h-4" />
          </div>
        </div>

        <div className="p-4 rounded-2xl bg-white dark:bg-gray-800/90 border-2 border-error-300 dark:border-error-700 shadow-sm flex items-center justify-between">
          <div>
            <p className="text-xl font-extrabold text-error-500">{summary.rejected}</p>
            <p className="text-xs font-semibold text-gray-400">{t('myTaskCard.rejected')}</p>
          </div>
          <div className="p-2.5 rounded-xl bg-error-50 text-error-500 dark:bg-error-700/20">
            <AlertTriangle className="w-4 h-4" />
          </div>
        </div>

        <div className="p-4 rounded-2xl bg-white dark:bg-gray-800/90 border-2 border-success-300 dark:border-success-700 shadow-sm flex items-center justify-between">
          <div>
            <p className="text-xl font-extrabold text-success-500">{summary.solved}</p>
            <p className="text-xs font-semibold text-gray-400">{t('myTaskCard.solved')}</p>
          </div>
          <div className="p-2.5 rounded-xl bg-success-50 text-success-500 dark:bg-success-700/20">
            <CheckCheck className="w-4 h-4" />
          </div>
        </div>
      </div>

      {/* Filter Tabs */}
      <div className="flex items-center space-x-2 overflow-x-auto pb-2 scrollbar-none mt-1 mb-3">
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
      {(taskListLoading || isQueueLoading) && <TaskSkeleton />}

      {/* Error State */}
      {!taskListLoading && !isQueueLoading && (taskListError || isQueueError) && (
        <div className="p-8 rounded-2xl bg-error-50 dark:bg-error-700/20 border border-error-300 dark:border-error-700 text-center">
          <AlertTriangle className="w-10 h-10 text-error-500 mx-auto mb-3" />
          <p className="text-sm font-bold text-error-600 dark:text-error-300 mb-3">{t('common.errorGeneric')}</p>
          <button
            onClick={() => { refetch(); refetchQueue(); }}
            className="px-4 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-white text-xs font-bold transition-colors"
          >
            {t('common.retry')}
          </button>
        </div>
      )}

      {/* Kanban Board — In Queue column first, then my accepted tickets */}
      {!taskListError && !isQueueError && !taskListLoading && !isQueueLoading && (visibleQueueTasks.length > 0 || tasks.length > 0) && (
        <KanbanBoard
          tasks={tasks}
          queueTasks={visibleQueueTasks}
          onEdit={() => {}}
          onDelete={() => {}}
          onToggleStatus={handleToggleStatus}
          onAccept={handleAcceptTask}
          acceptingTaskId={acceptingTaskId}
          isAccepting={updateTaskMutation.isPending}
          acceptBlocked={hasOpenRejected}
        />
      )}

      {/* Empty State */}
      {!taskListError && !isQueueError && !taskListLoading && !isQueueLoading && visibleQueueTasks.length === 0 && tasks.length === 0 && (
        <EmptyState
          title={t('kanban.noTickets')}
          description={t('myTasks.emptyDesc')}
          actionLabel={t('myTasks.viewAll')}
          onAction={() => setSelectedFilter(0)}
        />
      )}

      {/* Yakunlash — yechim izohi majburiy */}
      <SolveTaskModal
        task={solvingTask}
        isOpen={solvingTask !== null}
        onClose={() => setSolvingTask(null)}
        onSuccess={() => {
          setSolvingTask(null);
          refetch();
          refetchQueue();
        }}
      />
    </div>
  );
};
