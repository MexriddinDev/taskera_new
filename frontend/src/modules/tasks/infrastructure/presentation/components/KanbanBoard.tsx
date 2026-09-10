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
  /**
   * Xodimda yopilmagan qaytarilgan (reject) zayavka bor — navbatdan yangi
   * zayavka olish taqiqlanadi. Backend'dagi qoidaning aynan o'zi
   * (TicketController::update), shunchaki xatoni kutib o'tirmasdan tugma
   * boshidanoq bloklanadi.
   */
  acceptBlocked?: boolean;
  /**
   * Kuzatuvchi ko'rinishi — "Zayavkalarim" bo'limi uchun. Zayavka yuborgan
   * foydalanuvchi ijrochi emas, shuning uchun unga "Jarayonga o'tkazish"
   * kabi amallar ko'rsatilmaydi.
   */
  readOnly?: boolean;
  /**
   * Hali qabul qilinmagan ("Ochiq") ustuni ko'rsatilsinmi.
   *
   * Xodimlar taxtasida u ATAYLAB yo'q: ish "Jarayonda" dan boshlanadi.
   * Ustun faqat navbat bilan ishlaydigan sahifalarda (Ochiq topshiriqlar,
   * Mening topshiriqlarim) va murojaatchi ko'rinishida ("Kutishda") chiqadi.
   */
  showTodoColumn?: boolean;
  /** Baholash ("Baholash & Yopish") — bajarilgan, hali baholanmagan zayavkalar uchun. */
  onRate?: (task: Task) => void;
  /** Reject — bajarilgan, hali baholanmagan zayavkalar uchun. */
  onReject?: (task: Task) => void;
  /**
   * Hali hech kim qabul qilmagan zayavkalar — dispetcher ustuni.
   *
   * `queueTasks` dan farqi: u xodim navbati (kartochkalar xiralashgan va
   * faqat tepadagisini olish mumkin), bu esa biriktirish huquqi bor
   * admin/superadmin uchun — hammasi ochiq ko'rinadi va har biri xodimga
   * taqsimlanadi.
   */
  unassignedTasks?: Task[];
  /**
   * Biriktirish oynasini ochadi (biriktirish huquqi bo'lganlarga).
   * Dispetcher ustunida — "Biriktirish", "Jarayonda" ustunida (rad etilganlar
   * ham shu yerda) — mas'ulni almashtirish uchun "Boshqaga biriktirish".
   */
  onAssign?: (task: Task) => void;
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
  acceptBlocked = false,
  readOnly = false,
  showTodoColumn = false,
  unassignedTasks,
  onAssign,
}) => {
  const t = useT();
  // Rad etilgan zayavka alohida ustun emas — u xodimning ochiq ishi hisoblanadi,
  // shuning uchun "Jarayonda" ustunida, qizil kartochka sifatida va eng tepada
  // turadi: yakunlanmaguncha xodim navbatdan yangi zayavka ololmaydi.
  // "Bajarildi" va "Baholandi" ajratildi: yakunlangan zayavka murojaatchi
  // baho qo'ygunga qadar yopilgan hisoblanmaydi — xodim uchun ham, murojaatchi
  // uchun ham qaysi ish javob kutayotgani shu bilan ko'rinib turadi.
  const { todoTasks, inProgressTasks, doneTasks, ratedTasks, sortedQueueTasks } = useMemo(() => ({
    todoTasks: sortForQueue(tasks.filter((task) => task.status === 'todo')),
    inProgressTasks: [
      ...sortForQueue(tasks.filter((task) => task.status === 'rejected')),
      ...tasks.filter((task) => task.status === 'in_progress'),
    ],
    doneTasks: tasks.filter((task) => task.status === 'done' && !task.clientRating),
    ratedTasks: tasks.filter((task) => task.status === 'done' && Boolean(task.clientRating)),
    sortedQueueTasks: queueTasks ? sortForQueue(queueTasks) : undefined,
  }), [tasks, queueTasks]);

  return (
    <div className="flex items-start space-x-5 overflow-x-auto pb-6 scrollbar-thin">
      {unassignedTasks && (
        <KanbanColumn
          title={t('kanban.queue')}
          status="todo"
          tasks={sortForQueue(unassignedTasks)}
          statusColor="bg-slate-500"
          badgeBg="bg-slate-100 dark:bg-slate-800"
          badgeFg="text-slate-600 dark:text-slate-300"
          onEdit={onEdit}
          onDelete={onDelete}
          onToggleStatus={onToggleStatus}
          onAssign={onAssign}
        />
      )}
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
          acceptBlocked={acceptBlocked}
          onRate={onRate}
          onReject={onReject}
          readOnly={readOnly}
        />
      )}
      {(readOnly || showTodoColumn) && (
        <KanbanColumn
          title={readOnly ? t('kanban.waiting') : t('kanban.todo')}
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
          acceptBlocked={acceptBlocked}
          onRate={onRate}
          onReject={onReject}
          readOnly={readOnly}
        />
      )}

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
        readOnly={readOnly}
        onAssign={onAssign}
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
        readOnly={readOnly}
      />

      <KanbanColumn
        title={t('kanban.rated')}
        status="done"
        tasks={ratedTasks}
        statusColor="bg-emerald-600"
        badgeBg="bg-emerald-50 dark:bg-emerald-950/40"
        badgeFg="text-emerald-600 dark:text-emerald-400"
        onEdit={onEdit}
        onDelete={onDelete}
        onToggleStatus={onToggleStatus}
        readOnly={readOnly}
      />
    </div>
  );
};
