import React from 'react';
import { Task, TaskPriority } from '../../../domain/entities/Task';
import { Cpu, Code, Lock, Clock, Check, Building2 } from 'lucide-react';

interface OpenTaskCardProps {
  task: Task;
  onAccept: (taskId: number) => void;
  isAccepting?: boolean;
}

export const OpenTaskCard: React.FC<OpenTaskCardProps> = ({ task, onAccept, isAccepting = false }) => {
  const getPriorityBadge = (priority: TaskPriority) => {
    switch (priority) {
      case 'high':
        return 'bg-orange-50 text-orange-500 border-orange-500/20 dark:bg-orange-700/20';
      case 'medium':
        return 'bg-warning-50 text-warning-500 border-warning-500/20 dark:bg-warning-700/20';
      default:
        return 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-400';
    }
  };

  const getPriorityDot = (priority: TaskPriority) => {
    switch (priority) {
      case 'high':
        return 'bg-orange-500';
      case 'medium':
        return 'bg-warning-500';
      default:
        return 'bg-slate-300';
    }
  };

  return (
    <div className="bg-white dark:bg-slate-800/90 rounded-2xl p-5 border border-slate-200 dark:border-slate-700/80 shadow-sm hover:shadow-md transition-all flex flex-col justify-between relative overflow-hidden">
      {/* Blurred Card Content with Lock Badge Overlay */}
      <div className="relative rounded-xl overflow-hidden mb-4">
        {/* Full Backdrop Blur Overlay over Task Details */}
        <div className="absolute inset-0 z-10 backdrop-blur-md bg-white/75 dark:bg-slate-900/75 flex items-center justify-center p-4 text-center select-none">
          <div className="px-4 py-2 rounded-full bg-white/90 dark:bg-slate-800/90 border border-slate-200 dark:border-slate-700 shadow-md flex items-center space-x-2">
            <Lock className="w-3.5 h-3.5 text-brand-500" />
            <span className="text-xs font-bold text-slate-800 dark:text-slate-200">
              Qabul qilingach ma'lumotlar ochiladi
            </span>
          </div>
        </div>

        {/* Task Details Content (Blurred behind overlay) */}
        <div className="space-y-3 filter blur-[1px]">
          {/* Ticket Meta & Priority Header */}
          <div className="flex items-start justify-between gap-2">
            <div className="flex flex-wrap items-center gap-1.5">
              <span className="text-xs font-bold text-slate-400">{task.ticketNumber}</span>
              <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-brand-50 text-brand-500 border border-brand-500/20 dark:bg-brand-950/40">
                {task.targetDepartment === 'hardware' ? (
                  <>
                    <Cpu className="w-3 h-3 mr-1" /> Hardware
                  </>
                ) : (
                  <>
                    <Code className="w-3 h-3 mr-1 text-success-500" /> Software
                  </>
                )}
              </span>
              <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold border ${getPriorityBadge(task.priority)}`}>
                {task.priority.toUpperCase()}
              </span>
              <span className="px-2 py-0.5 rounded-full text-[10px] font-bold bg-warning-50 text-warning-500 border border-warning-500/20">
                Open
              </span>
            </div>

            <div className={`w-2.5 h-2.5 rounded-full ${getPriorityDot(task.priority)} flex-shrink-0 mt-1`} />
          </div>

          {/* Title & Description */}
          <div>
            <h3 className="font-bold text-base text-slate-900 dark:text-slate-100 line-clamp-1">{task.todo}</h3>
            <p className="text-xs text-slate-500 dark:text-slate-400 line-clamp-2 mt-0.5">
              {task.category} — {task.originDepartment} bo'limidan yuborilgan xizmat so'rovi.
            </p>
          </div>

          {/* Location Row */}
          <div className="p-2 rounded-lg bg-slate-50 dark:bg-slate-700/40 text-xs text-slate-600 dark:text-slate-300 flex items-center space-x-2 border border-slate-100 dark:border-slate-700">
            <Building2 className="w-3.5 h-3.5 text-slate-400" />
            <span className="truncate">{task.originDepartment}</span>
          </div>
        </div>
      </div>

      {/* Clear Unblurred Footer & Accept Action Button */}
      <div className="pt-3 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between text-xs text-slate-400 relative z-20">
        <div className="flex items-center space-x-1">
          <Clock className="w-3.5 h-3.5" />
          <span>{task.createdAt}</span>
        </div>

        <button
          onClick={() => onAccept(task.id)}
          disabled={isAccepting}
          className="inline-flex items-center space-x-1.5 px-4 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 active:bg-brand-700 text-white font-bold text-xs shadow-md hover:shadow-lg transition-all disabled:opacity-50 cursor-pointer"
        >
          <span>Qabul qilish</span>
          <Check className="w-3.5 h-3.5" />
        </button>
      </div>
    </div>
  );
};
