import React from 'react';
import { Task } from '../../../domain/entities/Task';
import { KanbanColumn } from './KanbanColumn';

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
  /** Unassigned incoming tickets shown as a locked "In Queue" column first. */
  queueTasks?: Task[];
  /** Baholash ("Baholash & Yopish") — bajarilgan, hali baholanmagan zayavkalar uchun. */
  onRate?: (task: Task) => void;
  /** Reject — bajarilgan, hali baholanmagan zayavkalar uchun. */
  onReject?: (task: Task) => void;
}

export const KanbanBoard: React.FC<KanbanBoardProps> = ({
  tasks,
  onEdit,
  onDelete,
  onToggleStatus,
  blurTodo = false,
  onAccept,
  isAccepting = false,
  queueTasks,
  onRate,
  onReject,
}) => {
  const todoTasks = tasks.filter((t) => t.status === 'todo');
  const inProgressTasks = tasks.filter((t) => t.status === 'in_progress');
  const rejectedTasks = tasks.filter((t) => t.status === 'rejected');
  const doneTasks = tasks.filter((t) => t.status === 'done');

  return (
    <div className="flex items-start space-x-5 overflow-x-auto pb-6 scrollbar-thin">
      {queueTasks && (
        <KanbanColumn
          title="In Queue (Qabul qilinmagan)"
          status="todo"
          tasks={queueTasks}
          statusColor="bg-slate-500"
          badgeBg="bg-slate-100 dark:bg-slate-800"
          badgeFg="text-slate-600 dark:text-slate-300"
          onEdit={onEdit}
          onDelete={onDelete}
          onToggleStatus={onToggleStatus}
          blurred
          onAccept={onAccept}
          isAccepting={isAccepting}
          onRate={onRate}
          onReject={onReject}
        />
      )}
      <KanbanColumn
        title="Ochiq (To Do)"
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
        maxLimit={3}
        onRate={onRate}
        onReject={onReject}
      />

      <KanbanColumn
        title="Jarayonda (In Progress)"
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
        title="Rad etilgan (Rejected)"
        status="rejected"
        tasks={rejectedTasks}
        statusColor="bg-error-500"
        badgeBg="bg-error-50 dark:bg-error-700/20"
        badgeFg="text-error-500"
        onEdit={onEdit}
        onDelete={onDelete}
        onToggleStatus={onToggleStatus}
        onRate={onRate}
        onReject={onReject}
      />

      <KanbanColumn
        title="Bajarilgan (Solved / Done)"
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
