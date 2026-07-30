import React from 'react';
import { Task, TaskStatus } from '../../../domain/entities/Task';
import { TaskCard } from './TaskCard';

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
  maxLimit?: number;
}

export const KanbanColumn: React.FC<KanbanColumnProps> = ({
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
  maxLimit,
}) => {
  return (
    <div className="flex-1 min-w-[320px] bg-gray-100/70 dark:bg-gray-800/40 rounded-2xl p-4 border border-gray-200/80 dark:border-gray-700/60 flex flex-col space-y-4">
      {/* Column Header */}
      <div className="flex items-center justify-between pb-3 border-b border-gray-200/80 dark:border-gray-700/60">
        <div className="flex items-center space-x-2">
          <div className={`w-3 h-3 rounded-full ${statusColor}`} />
          <h3 className="font-extrabold text-sm text-gray-900 dark:text-gray-100">{title}</h3>
        </div>
        <span className={`px-2.5 py-0.5 rounded-full text-xs font-bold ${badgeBg} ${badgeFg}`}>
          {maxLimit ? `${tasks.length} / ${maxLimit}` : tasks.length}
        </span>
      </div>

      {/* Cards List */}
      <div className="space-y-4 overflow-y-auto max-h-[calc(100vh-280px)] pr-1 scrollbar-thin">
        {tasks.map((task) => (
          <TaskCard
            key={task.id}
            task={task}
            onEdit={onEdit}
            onDelete={onDelete}
            onToggleStatus={onToggleStatus}
            blurred={blurred}
            onAccept={onAccept}
            isAccepting={isAccepting}
          />
        ))}

        {tasks.length === 0 && (
          <div className="p-8 text-center border-2 border-dashed border-gray-200 dark:border-gray-700/60 rounded-xl">
            <p className="text-xs font-semibold text-gray-400">Zayavkalar yo'q</p>
          </div>
        )}
      </div>
    </div>
  );
};
