import React from 'react';
import { Task, TaskStatus } from '../../../domain/entities/Task';
import { TaskCard } from './TaskCard';
import { useT } from '@/shared/presentation/i18n/i18n';

interface KanbanColumnProps {
  title: string;
  status: TaskStatus;
  tasks: Task[];
  statusColor: string;
  badgeBg: string;
  badgeFg: string;
  onEdit: (task: Task) => void;
  onDelete: (id: number) => void;
  onToggleStatus: (task: Task) => void;
  blurred?: boolean;
  onAccept?: (id: number) => void;
  isAccepting?: boolean;
  acceptingTaskId?: number | null;
  /** Queue column belgisi — blur kartochkada "Navbatda" pill ko'rsatiladi. */
  queueLabel?: boolean;
  /** Yopilmagan qaytarilgan zayavka bor — navbatdan yangi zayavka olinmaydi. */
  acceptBlocked?: boolean;
  /** Kuzatuvchi ko'rinishi — ijrochi amallari kartochkada ko'rsatilmaydi. */
  readOnly?: boolean;
  onRate?: (task: Task) => void;
  onReject?: (task: Task) => void;
  /** Dispetcher amali — kartochkada "Biriktirish" tugmasi chiqadi. */
  onAssign?: (task: Task) => void;
}

export const KanbanColumn: React.FC<KanbanColumnProps> = React.memo(({
  title,
  tasks,
  statusColor,
  badgeBg,
  badgeFg,
  onEdit,
  onDelete,
  onToggleStatus,
  blurred = false,
  onAccept,
  isAccepting = false,
  acceptingTaskId,
  queueLabel = false,
  acceptBlocked = false,
  readOnly = false,
  onRate,
  onReject,
  onAssign,
}) => {
  const t = useT();
  const rejectedCount = tasks.filter((task) => task.status === 'rejected').length;
  return (
    <div className="flex-1 min-w-[320px] bg-gray-100/70 dark:bg-gray-800/40 rounded-2xl p-4 border border-gray-200/80 dark:border-gray-700/60 flex flex-col space-y-4">
      {/* Column Header */}
      <div className="flex items-center justify-between pb-3 border-b border-gray-200/80 dark:border-gray-700/60">
        <div className="flex items-center space-x-2">
          <div className={`w-3 h-3 rounded-full ${statusColor}`} />
          <h3 className="font-extrabold text-sm text-gray-900 dark:text-gray-100">{title}</h3>
        </div>
        <div className="flex items-center gap-1.5">
          <span className={`px-2.5 py-0.5 rounded-full text-xs font-bold ${badgeBg} ${badgeFg}`}>
            {tasks.length}
          </span>
          {rejectedCount > 0 && (
            <span
              className="px-2 py-0.5 rounded-full text-[11px] font-bold bg-error-50 dark:bg-error-700/20 text-error-500 border border-error-500/20"
              title={t('status.rejected')}
            >
              +{rejectedCount}
            </span>
          )}
        </div>
      </div>

      {/* Cards List */}
      <div className="space-y-4 overflow-y-auto max-h-[calc(100vh-280px)] pr-1 scrollbar-thin">
        {tasks.map((task, index) => (
          <TaskCard
            key={task.id}
            task={task}
            onEdit={onEdit}
            onDelete={onDelete}
            onToggleStatus={onToggleStatus}
            blurred={blurred}
            queueLabel={queueLabel}
            onAccept={onAccept}
            /* Navbatda faqat birinchi zayavka qabul qilinadi; u olingach
               ro'yxat siljiydi va keyingisi tepaga chiqadi. Yopilmagan
               qaytarilgan zayavka bo'lsa — birinchisi ham qulflanadi. */
            canAccept={index === 0 && !acceptBlocked}
            acceptBlocked={acceptBlocked}
            queuePosition={index + 1}
            isAccepting={acceptingTaskId !== undefined ? acceptingTaskId === task.id : isAccepting}
            onRate={onRate}
            onReject={onReject}
            readOnly={readOnly}
            onAssign={onAssign}
          />
        ))}

        {tasks.length === 0 && (
          <div className="p-8 text-center border-2 border-dashed border-gray-200 dark:border-gray-700/60 rounded-xl">
            <p className="text-xs font-semibold text-gray-400">{t('kanban.noTickets')}</p>
          </div>
        )}
      </div>
    </div>
  );
});
