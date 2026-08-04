import React from 'react';
import { Link } from 'react-router-dom';
import { Task, TaskPriority, TaskStatus } from '../../../domain/entities/Task';
import { Cpu, Code, Building2, Clock, ArrowRight, AlertTriangle } from 'lucide-react';

interface MyTaskCardProps {
  task: Task;
}

export const MyTaskCard: React.FC<MyTaskCardProps> = ({ task }) => {
  const getStatusDot = (status: TaskStatus) => {
    switch (status) {
      case 'done':
        return 'bg-success-500';
      case 'in_progress':
        return 'bg-orange-500';
      case 'rejected':
        return 'bg-error-500';
      default:
        return 'bg-brand-500';
    }
  };

  const getStatusBadge = (status: TaskStatus) => {
    switch (status) {
      case 'done':
        return { bg: 'bg-success-50 dark:bg-success-700/20', fg: 'text-success-500', label: 'Solved' };
      case 'in_progress':
        return { bg: 'bg-orange-50 dark:bg-orange-700/20', fg: 'text-orange-500', label: 'In Progress' };
      case 'rejected':
        return { bg: 'bg-error-50 dark:bg-error-700/20', fg: 'text-error-500', label: 'Rejected' };
      default:
        return { bg: 'bg-brand-50 dark:bg-brand-950/40', fg: 'text-brand-500', label: 'Accepted' };
    }
  };

  const getPriorityBadge = (priority: TaskPriority) => {
    switch (priority) {
      case 'high':
        return { bg: 'bg-orange-50 dark:bg-orange-700/20', fg: 'text-orange-500', label: 'High' };
      case 'medium':
        return { bg: 'bg-warning-50 dark:bg-warning-700/20', fg: 'text-warning-500', label: 'Medium' };
      default:
        return { bg: 'bg-slate-100 dark:bg-slate-800', fg: 'text-slate-600 dark:text-slate-400', label: 'Low' };
    }
  };

  const statusBadge = getStatusBadge(task.status);
  const priorityBadge = getPriorityBadge(task.priority);

  return (
    <div className="bg-white dark:bg-gray-800/90 rounded-2xl p-5 border border-gray-200 dark:border-gray-700/80 shadow-sm hover:shadow-md transition-all flex flex-col justify-between space-y-4">
      {/* Header with status dot */}
      <div className="flex items-start justify-between gap-3">
        <div>
          <span className="text-[11px] font-bold text-gray-400">{task.ticketNumber}</span>
          <h3 className="font-bold text-base text-gray-900 dark:text-gray-100 line-clamp-1">{task.todo}</h3>
        </div>
        <div className={`w-3 h-3 rounded-full ${getStatusDot(task.status)} flex-shrink-0 mt-1`} />
      </div>

      {/* Rejection Alert if rejected and not done */}
      {task.status !== 'done' && task.rejectionReason && (
        <div className="p-2.5 rounded-xl bg-error-50 dark:bg-error-700/20 border border-error-500/20 flex items-start space-x-2">
          <AlertTriangle className="w-4 h-4 text-error-500 flex-shrink-0 mt-0.5" />
          <p className="text-xs text-error-500 font-medium line-clamp-2">{task.rejectionReason}</p>
        </div>
      )}

      {/* Client Rating if present */}
      {task.clientRating && (
        <div className="p-2.5 rounded-xl bg-amber-50 dark:bg-amber-950/40 border border-amber-500/20 flex items-center justify-between">
          <span className="text-xs font-bold text-amber-700 dark:text-amber-300 flex items-center">
            <span className="mr-1">Mijoz bahosi:</span>
          </span>
          <span className="inline-flex items-center space-x-1 px-2.5 py-0.5 rounded-lg bg-amber-500 text-white font-extrabold text-xs shadow-sm">
            ⭐ {task.clientRating} / 5
          </span>
        </div>
      )}

      {/* Location tag */}
      <div className="p-2.5 rounded-xl bg-gray-50 dark:bg-gray-700/40 text-xs text-gray-600 dark:text-gray-300 flex items-center space-x-2 border border-gray-100 dark:border-gray-700">
        <Building2 className="w-4 h-4 text-gray-400" />
        <span className="truncate">{task.originDepartment}</span>
      </div>

      {/* Badges */}
      <div className="flex flex-wrap items-center gap-2">
        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-brand-50 text-brand-500 border border-brand-500/20 dark:bg-brand-950/40">
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

        <span className={`px-2.5 py-0.5 rounded-full text-[11px] font-bold ${priorityBadge.bg} ${priorityBadge.fg}`}>
          {priorityBadge.label}
        </span>

        <span className={`px-2.5 py-0.5 rounded-full text-[11px] font-bold ${statusBadge.bg} ${statusBadge.fg}`}>
          {statusBadge.label}
        </span>
      </div>

      {/* Footer & View Action */}
      <div className="pt-3 border-t border-gray-100 dark:border-gray-700/60 flex items-center justify-between text-xs text-gray-400">
        <div className="flex items-center space-x-1">
          <Clock className="w-3.5 h-3.5" />
          <span>{task.createdAt}</span>
        </div>

        <Link
          to={`/task/${task.id}`}
          className="inline-flex items-center space-x-1.5 px-3.5 py-1.5 rounded-lg bg-brand-500 hover:bg-brand-600 active:bg-brand-700 text-white font-bold text-xs shadow-sm transition-all"
        >
          <span>Ko'rish</span>
          <ArrowRight className="w-3.5 h-3.5" />
        </Link>
      </div>
    </div>
  );
};
