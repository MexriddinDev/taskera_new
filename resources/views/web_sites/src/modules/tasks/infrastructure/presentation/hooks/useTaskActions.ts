import { useUpdateTask } from './useUpdateTask';
import { useT } from '@/shared/presentation/i18n/i18n';
import type { Task } from '../../../domain/entities/Task';

/**
 * Umumiy task status o'tkazish logikasi — 3 sahifada (MyTasks, OpenTasks,
 * Dashboard) takrorlangan handleToggleStatus bitta joyga jamlandi.
 *
 * Oqim: todo/rejected → in_progress; in_progress → done.
 */
export function useTaskActions() {
  const updateTaskMutation = useUpdateTask();
  const t = useT();

  const toggleStatus = (task: Task, options?: { defaultSolution?: string }) => {
    if (task.status === 'done') return;

    if (task.status === 'todo' || task.status === 'rejected') {
      // Rad etilgan zayavka ham To Doga qaytadi — bosilganda jarayonga o'tadi
      updateTaskMutation.mutate({
        id: task.id,
        dto: { status: 'in_progress' },
      });
    } else {
      updateTaskMutation.mutate({
        id: task.id,
        dto: {
          status: 'done',
          completed: true,
          solutionComment:
            options?.defaultSolution ?? t('myTasks.defaultSolution'),
        },
      });
    }
  };

  return {
    toggleStatus,
    isPending: updateTaskMutation.isPending,
    mutation: updateTaskMutation,
  };
}
