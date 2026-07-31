import React from 'react';
import { Link } from 'react-router-dom';
import { Task, TaskPriority, TaskStatus } from '../../../domain/entities/Task';
import { CheckCircle2, Cpu, Code, Copy, AlertTriangle, MapPin, Eye, Lock, Loader2, Star } from 'lucide-react';

interface TaskCardProps {
  task: Task;
  onEdit: (task: Task) => void;
  onDelete: (id: number) => void;
  onToggleStatus: (task: Task) => void;
  /** When true, the card content is hidden/blurred until the task is accepted. */
  blurred?: boolean;
  onAccept?: (id: number) => void;
  isAccepting?: boolean;
}

export const TaskCard: React.FC<TaskCardProps> = ({
  task,
  onEdit: _onEdit,
  onDelete: _onDelete,
  onToggleStatus,
  blurred = false,
  onAccept,
  isAccepting = false,
}) => {
  const [copied, setCopied] = React.useState(false);

  const handleCopyTicket = (e: React.MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();
    navigator.clipboard.writeText(task.ticketNumber);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  const getStatusBadge = (status: TaskStatus) => {
    switch (status) {
      case 'done':
        return 'bg-success-50 text-success-500 border-success-500/20 dark:bg-success-700/30 dark:text-emerald-300';
      case 'in_progress':
        return 'bg-brand-50 text-brand-500 border-brand-500/20 dark:bg-brand-950/50 dark:text-brand-300';
      case 'rejected':
        return 'bg-error-50 text-error-500 border-error-500/20 dark:bg-error-700/30 dark:text-red-300';
      default:
        return 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300';
    }
  };

  const getPriorityBadge = (priority: TaskPriority) => {
    switch (priority) {
      case 'high':
        return 'bg-error-50 text-error-500 border-error-500/20 dark:bg-error-700/30 dark:text-red-300';
      case 'medium':
        return 'bg-warning-50 text-warning-500 border-warning-500/20 dark:bg-warning-700/30 dark:text-amber-300';
      default:
        return 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-400';
    }
  };

  // Blurred / locked state — task content is hidden until the admin accepts it.
  if (blurred) {
    return (
      <div className="relative bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200 dark:border-gray-700/80 shadow-sm overflow-hidden">
        {/* Blurred underlying content (unreadable) */}
        <div className="p-5 select-none pointer-events-none blur-md opacity-70" aria-hidden="true">
          <div className="flex items-center justify-between mb-3">
            <span className="font-bold text-sm text-gray-900 dark:text-gray-100">{task.ticketNumber}</span>
            <span className="text-xs text-gray-400">{task.category}</span>
          </div>
          <div className="h-4 w-3/4 rounded bg-gray-300 dark:bg-gray-600 mb-2" />
          <div className="h-4 w-2/3 rounded bg-gray-200 dark:bg-gray-700 mb-4" />
          <div className="h-3 w-1/2 rounded bg-gray-200 dark:bg-gray-700" />
        </div>

        {/* Lock overlay with Accept button */}
        <div className="absolute inset-0 flex flex-col items-center justify-center bg-white/40 dark:bg-gray-900/40 backdrop-blur-[2px] space-y-3 px-4">
          <div className="flex items-center space-x-2 text-gray-500 dark:text-gray-300">
            <Lock className="w-4 h-4" />
            <span className="text-xs font-bold">Yopiq — qabul qiling</span>
          </div>
          <button
            onClick={(e) => {
              e.preventDefault();
              e.stopPropagation();
              onAccept?.(task.id);
            }}
            disabled={isAccepting}
            className="inline-flex items-center space-x-2 px-4 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 active:bg-brand-700 text-white font-extrabold text-xs shadow-md transition-all disabled:opacity-50 cursor-pointer"
          >
            {isAccepting ? <Loader2 className="w-4 h-4 animate-spin" /> : <CheckCircle2 className="w-4 h-4" />}
            <span>Qabul qilish</span>
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="group bg-white dark:bg-gray-800/90 rounded-2xl p-5 border border-gray-200 dark:border-gray-700/80 shadow-sm hover:shadow-lg transition-all duration-200 flex flex-col justify-between relative overflow-hidden">
      <div>
        {/* Ticket Header & Quick Copy */}
        <div className="flex items-center justify-between gap-2 mb-3 pb-3 border-b border-gray-100 dark:border-gray-700/60">
          <div className="flex items-center space-x-2">
            <span className="font-bold text-sm text-gray-900 dark:text-gray-100">{task.ticketNumber}</span>
            <button
              onClick={handleCopyTicket}
              className="text-gray-400 hover:text-brand-500 p-1 rounded transition-colors"
              title="Copy Ticket Number"
            >
              <Copy className="w-3.5 h-3.5" />
            </button>
            {copied && <span className="text-[10px] text-success-500 font-medium animate-pulse">Copied!</span>}
          </div>
          <span className="text-xs text-gray-400 font-medium">{task.category}</span>
        </div>

        {/* Badges: Department & Priority */}
        <div className="flex items-center justify-between gap-2 mb-3">
          <span className="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-brand-50 dark:bg-brand-950/40 text-brand-500 border border-brand-500/20">
            {task.targetDepartment === 'hardware' ? (
              <>
                <Cpu className="w-3.5 h-3.5 mr-1" /> Hardware
              </>
            ) : (
              <>
                <Code className="w-3.5 h-3.5 mr-1 text-success-500" /> Software
              </>
            )}
          </span>

          <div className="flex items-center space-x-2">
            <span className={`px-2.5 py-0.5 rounded-full text-[11px] font-semibold border ${getStatusBadge(task.status)}`}>
              {task.status.replace('_', ' ').toUpperCase()}
            </span>
            <span className={`px-2.5 py-0.5 rounded-full text-[11px] font-semibold border ${getPriorityBadge(task.priority)}`}>
              {task.priority.toUpperCase()}
            </span>
          </div>
        </div>

        {/* Task Title & Description */}
        <div className="mb-4">
          <Link
            to={`/task/${task.id}`}
            className="font-bold text-base text-gray-900 dark:text-gray-100 hover:text-brand-500 dark:hover:text-brand-400 transition-colors line-clamp-2 mb-1"
          >
            {task.todo}
          </Link>

          {task.status !== 'done' && task.rejectionReason && (
            <div className="mt-2 p-2.5 rounded-lg bg-error-50 dark:bg-error-700/20 border border-error-500/20 flex items-start space-x-2">
              <AlertTriangle className="w-4 h-4 text-error-500 flex-shrink-0 mt-0.5" />
              <p className="text-xs text-error-500 font-medium line-clamp-2">{task.rejectionReason}</p>
            </div>
          )}

          {task.status === 'done' && task.clientRating != null && task.clientRating > 0 && (
            <div className="mt-2 flex items-center space-x-1" title={`Baholangan: ${task.clientRating}/5`}>
              {[1, 2, 3, 4, 5].map((n) => (
                <Star
                  key={n}
                  className={`w-3.5 h-3.5 ${
                    n <= (task.clientRating ?? 0)
                      ? 'text-amber-400 fill-amber-400'
                      : 'text-gray-300 dark:text-gray-600'
                  }`}
                />
              ))}
            </div>
          )}
        </div>
      </div>

      {/* Footer Info & Actions */}
      <div className="pt-3 border-t border-gray-100 dark:border-gray-700/60 flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
        <div className="flex items-center space-x-3">
          {task.floor && (
            <div className="flex items-center space-x-1">
              <MapPin className="w-3.5 h-3.5 text-gray-400" />
              <span>{task.floor}</span>
            </div>
          )}

          {/* Standalone Circular User Avatar (No text label, hover shows ONLY full name/username) */}
          {task.assignedUserId && (
            <div
              className="relative group/user cursor-pointer"
              title={task.assignedTo || 'Xodim'}
            >
              <img
                src={task.assignedUserAvatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(task.assignedTo || 'Xodim')}&size=512&bold=true&background=0D8ABC&color=fff`}
                alt={task.assignedTo || 'Xodim'}
                className="ml-2 w-8 h-8 rounded-full object-cover border-2 border-white dark:border-slate-700 group-hover/user:scale-110 transition-transform"
              />
            </div>
          )}
        </div>

        <div className="flex items-center space-x-2">
          <Link
            to={`/task/${task.id}`}
            className="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-300 transition-colors"
            title="Batafsil ko'rish"
          >
            <Eye className="w-4 h-4" />
          </Link>

          {task.status === 'done' ? (
            <span
              className="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-xl font-extrabold text-xs shadow-sm bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300"
            >
              <CheckCircle2 className="w-3.5 h-3.5" />
              <span>Bajarilgan</span>
            </span>
          ) : task.status === 'todo' ? (
            <button
              onClick={(e) => {
                e.preventDefault();
                e.stopPropagation();
                onToggleStatus(task);
              }}
              className="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-xl font-extrabold text-xs shadow-sm transition-all cursor-pointer bg-brand-500 hover:bg-brand-600 active:bg-brand-700 text-white"
            >
              <span>Jarayonga o'tkazish</span>
            </button>
          ) : (
            <button
              onClick={(e) => {
                e.preventDefault();
                e.stopPropagation();
                onToggleStatus(task);
              }}
              className="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-xl font-extrabold text-xs shadow-sm transition-all cursor-pointer bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white"
            >
              <CheckCircle2 className="w-3.5 h-3.5" />
              <span>Yakunlash</span>
            </button>
          )}
        </div>
      </div>
    </div>
  );
};
