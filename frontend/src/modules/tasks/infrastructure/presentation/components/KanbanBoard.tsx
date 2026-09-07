import React, { useMemo } from 'react';
import { Task } from '../../../domain/entities/Task';
import { KanbanColumn } from './KanbanColumn';
import { useT } from '@/shared/presentation/i18n/i18n';

interface KanbanBoardProps {
  tasks: Task[];
  onEdit: (task: Task) => void;
  onDelete: (id: number) => void;
  onToggleStatus: (task: Task) => void;
  /** When true, tasks in the "todo" column are blurred until accepted. */
  blurTodo?: boolean;
  /** Accept ("Qabul qilish") handler — shown on blurred todo cards. */
  onAccept?: (id: number) => void;
  isAccepting?: boolean;
  acceptingTaskId?: number | null;
  /** Unassigned incoming tickets shown as a locked "In Queue" column first. */
  queueTasks?: Task[];
  /** Baholash ("Baholash & Yopish") — bajarilgan, hali baholanmagan zayavkalar uchun. */
  onRate?: (task: Task) => void;
  /** Reject — bajarilgan, hali baholanmagan zayavkalar uchun. */
  onReject?: (task: Task) => void;
}

const QUEUE_PRIORITY_WEIGHT: Record<string, number> = { high: 3, medium: 2, low: 1 };

/**
 * Navbat tartibi: avval muhimlik (Yuqori -> Past), teng bo'lsa eng uzoq
 * kutgani tepada.
 *
 * API zayavkalarni created_at DESC bilan qaytaradi, ya'ni tepada eng YANGI
 * turadi. Qabul qilish tugmasi faqat tepadagi kartochkada bo'lgani uchun
 * bunday tartibda eski zayavkalar navbatda qolib ketardi.
 */
const sortForQueue = (list: Task[]): Task[] =>
  [...list].sort((a, b) => {
    const byPriority = (QUEUE_PRIORITY_WEIGHT[b.priority] ?? 0) - (QUEUE_PRIORITY_WEIGHT[a.priority] ?? 0);
    if (byPriority !== 0) {
      return byPriority;
    }

    // createdAt API'dan "02-Sep 2026, 16:45" ko'rinishida keladi — bu nostandart
    // format va uni hamma brauzer ham bir xil o'qimaydi (Safari qat'iyroq).
    // Parse bo'lmasa id bo'yicha taqqoslaymiz: id auto-increment, ya'ni kichigi eskiroq.
    const aTime = new Date(a.createdAt).getTime();
    const bTime = new Date(b.createdAt).getTime();

    if (Number.isNaN(aTime) || Number.isNaN(bTime)) {
      return a.id - b.id;
    }

    return aTime - bTime;
  });

export const KanbanBoard: React.FC<KanbanBoardProps> = ({
  tasks,
  onEdit,
  onDelete,
  onToggleStatus,
  blurTodo = false,
  onAccept,
  isAccepting = false,
  acceptingTaskId,
  queueTasks,
  onRate,
  onReject,
}) => {
  const t = useT();
  // Rad etilgan zayavkalar alohida ustun emas — To Do ustuniga qizil kartochka sifatida qaytadi.
  const { todoTasks, inProgressTasks, doneTasks, sortedQueueTasks } = useMemo(() => ({
    todoTasks: [
      ...sortForQueue(tasks.filter((task) => task.status === 'todo')),
      ...sortForQueue(tasks.filter((task) => task.status === 'rejected')),
    ],
    inProgressTasks: tasks.filter((task) => task.status === 'in_progress'),
    doneTasks: tasks.filter((task) => task.status === 'done'),
    sortedQueueTasks: queueTasks ? sortForQueue(queueTasks) : undefined,
  }), [tasks, queueTasks]);

  return (
    <div className="flex items-start space-x-5 overflow-x-auto pb-6 scrollbar-thin">
      {queueTasks && (
        <KanbanColumn
          title={t('kanban.queue')}
          status="todo"
          tasks={sortedQueueTasks ?? []}
          statusColor="bg-slate-500"
          badgeBg="bg-slate-100 dark:bg-slate-800"
          badgeFg="text-slate-600 dark:text-slate-300"
          queueLabel
          onEdit={onEdit}
          onDelete={onDelete}
          onToggleStatus={onToggleStatus}
          blurred
          onAccept={onAccept}
          isAccepting={isAccepting}
          acceptingTaskId={acceptingTaskId}
          onRate={onRate}
          onReject={onReject}
        />
      )}
      <KanbanColumn
        title={t("kanban.todo")}
        status="todo"
        tasks={todoTasks}
        statusColor="bg-brand-500"
        badgeBg="bg-brand-50 dark:bg-brand-950/40"
        badgeFg="text-brand-500"
        onEdit={onEdit}
        onDelete={onDelete}
        onToggleStatus={onToggleStatus}
        blurred={blurTodo}
        onAccept={onAccept}
        isAccepting={isAccepting}
        acceptingTaskId={acceptingTaskId}
        maxLimit={3}
        onRate={onRate}
        onReject={onReject}
      />

      <KanbanColumn
        title={t("kanban.inProgress")}
        status="in_progress"
        tasks={inProgressTasks}
        statusColor="bg-warning-500"
        badgeBg="bg-warning-50 dark:bg-warning-700/20"
        badgeFg="text-warning-500"
        onEdit={onEdit}
        onDelete={onDelete}
        onToggleStatus={onToggleStatus}
        onRate={onRate}
        onReject={onReject}
      />

      <KanbanColumn
        title={t("kanban.done")}
        status="done"
        tasks={doneTasks}
        statusColor="bg-success-500"
        badgeBg="bg-success-50 dark:bg-success-700/20"
        badgeFg="text-success-500"
        onEdit={onEdit}
        onDelete={onDelete}
        onToggleStatus={onToggleStatus}
        onRate={onRate}
        onReject={onReject}
      />
    </div>
  );
};
