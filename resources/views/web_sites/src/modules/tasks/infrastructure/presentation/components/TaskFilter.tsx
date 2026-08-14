import React from 'react';
import { Search, LayoutGrid, Kanban } from 'lucide-react';
import { Input } from '@/shared/presentation/components/Input';
import { Button } from '@/shared/presentation/components/Button';
import { TaskPriority, TaskStatus, TargetDepartment } from '../../../domain/entities/Task';
import { useT } from '@/shared/presentation/i18n/i18n';

interface TaskFilterProps {
  search: string;
  onSearchChange: (value: string) => void;
  status: TaskStatus | 'all';
  onStatusChange: (status: TaskStatus | 'all') => void;
  priority: TaskPriority | 'all';
  onPriorityChange: (priority: TaskPriority | 'all') => void;
  targetDepartment: TargetDepartment | 'all';
  onDepartmentChange: (dept: TargetDepartment | 'all') => void;
  viewMode?: 'grid' | 'kanban';
  onViewModeChange?: (mode: 'grid' | 'kanban') => void;
  hideStatus?: boolean;
}

export const TaskFilter: React.FC<TaskFilterProps> = ({
  search,
  onSearchChange,
  status,
  onStatusChange,
  priority,
  onPriorityChange,
  targetDepartment,
  onDepartmentChange,
  viewMode = 'grid',
  onViewModeChange,
  hideStatus = false,
}) => {
  const t = useT();
  return (
    <div className="bg-white dark:bg-gray-800/90 rounded-2xl p-4 sm:p-5 border border-gray-200 dark:border-gray-700/80 shadow-sm space-y-4">
      {/* Top Bar: Search, View Toggle, Create Button */}
      <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
        <div className="flex-1 max-w-md">
          <Input
            value={search}
            onChange={(e) => onSearchChange(e.target.value)}
            placeholder={t('filter.searchPlaceholder')}
            icon={<Search className="w-4 h-4 text-gray-400" />}
          />
        </div>

        <div className="flex items-center space-x-3">
          {/* Grid vs Kanban Toggle Button */}
          {onViewModeChange && (
            <div className="flex items-center p-1 rounded-xl bg-gray-100 dark:bg-gray-700 border border-gray-200 dark:border-gray-600">
              <button
                onClick={() => onViewModeChange('grid')}
                className={`flex items-center space-x-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all ${
                  viewMode === 'grid'
                    ? 'bg-white dark:bg-gray-800 text-brand-500 shadow-sm'
                    : 'text-gray-500 hover:text-gray-900 dark:hover:text-gray-100'
                }`}
                title={t('filter.gridTitle')}
              >
                <LayoutGrid className="w-3.5 h-3.5" />
                <span className="hidden sm:inline">Grid</span>
              </button>
              <button
                onClick={() => onViewModeChange('kanban')}
                className={`flex items-center space-x-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all ${
                  viewMode === 'kanban'
                    ? 'bg-white dark:bg-gray-800 text-brand-500 shadow-sm'
                    : 'text-gray-500 hover:text-gray-900 dark:hover:text-gray-100'
                }`}
                title={t('filter.kanbanTitle')}
              >
                <Kanban className="w-3.5 h-3.5" />
                <span className="hidden sm:inline">Kanban</span>
              </button>
            </div>
          )}
        </div>
      </div>

      {/* Filter Dropdowns */}
      <div className={
        hideStatus
          ? 'grid grid-cols-1 sm:grid-cols-2 gap-3 pt-3 border-t border-gray-100 dark:border-gray-700'
          : 'grid grid-cols-1 sm:grid-cols-3 gap-3 pt-3 border-t border-gray-100 dark:border-gray-700'
      }>
        <div>
          <label className="block text-[11px] font-bold text-gray-400 mb-1">{t('filter.departmentLabel')}</label>
          <select
            value={targetDepartment}
            onChange={(e) => onDepartmentChange(e.target.value as TargetDepartment | 'all')}
            className="w-full h-10 rounded-xl bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 px-3 text-xs font-semibold text-gray-900 dark:text-gray-100 focus:outline-none focus:border-brand-500 transition-colors"
          >
            <option value="all">{t('dept.all')}</option>
            <option value="hardware">{t('dept.hardware')}</option>
            <option value="software">{t('dept.software')}</option>
          </select>
        </div>

        {!hideStatus && (
          <div>
            <label className="block text-[11px] font-bold text-gray-400 mb-1">{t('filter.statusLabel')}</label>
            <select
              value={status}
              onChange={(e) => onStatusChange(e.target.value as TaskStatus | 'all')}
              className="w-full h-10 rounded-xl bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 px-3 text-xs font-semibold text-gray-900 dark:text-gray-100 focus:outline-none focus:border-brand-500 transition-colors"
            >
              <option value="all">{t('status.all')}</option>
              <option value="todo">{t('status.todo')}</option>
              <option value="in_progress">{t('status.inProgress')}</option>
              <option value="rejected">{t('status.rejected')}</option>
              <option value="done">{t('status.done')}</option>
            </select>
          </div>
        )}

        <div>
          <label className="block text-[11px] font-bold text-gray-400 mb-1">{t('filter.priorityLabel')}</label>
          <select
            value={priority}
            onChange={(e) => onPriorityChange(e.target.value as TaskPriority | 'all')}
            className="w-full h-10 rounded-xl bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 px-3 text-xs font-semibold text-gray-900 dark:text-gray-100 focus:outline-none focus:border-brand-500 transition-colors"
          >
            <option value="all">{t('priority.all')}</option>
            <option value="high">{t('priority.high')}</option>
            <option value="medium">{t('priority.medium')}</option>
            <option value="low">{t('priority.low')}</option>
          </select>
        </div>
      </div>
    </div>
  );
};
