import { useUpdateTask } from './useUpdateTask';
import type { Task } from '../../../domain/entities/Task';

/**
 * Umumiy task status o'tkazish logikasi — 3 sahifada (MyTasks, OpenTasks,
 * Dashboard) takrorlangan handleToggleStatus bitta joyga jamlandi.
 *
 * Oqim: todo/rejected → in_progress; in_progress → done.
 */
export function useTaskActions() {
  const updateTaskMutation = useUpdateTask();

  /**
   * @param onRequireSolveComment Zayavkani YOPISHDAN oldin chaqiriladi.
   *   Yopish uchun yechim izohi majburiy, shuning uchun bu yerda to'g'ridan-
   *   to'g'ri mutatsiya qilmaymiz — sahifa SolveTaskModal'ni ochadi va
   *   yuborishni o'sha oyna bajaradi. Ilgari bu yerda avtomatik
   *   "Vazifa bajarildi" matni qo'yilardi va izoh talabi chetlab o'tilardi.
   */
  const toggleStatus = (task: Task, onRequireSolveComment?: (task: Task) => void) => {
    if (task.status === 'done') return;

    if (task.status === 'todo' || task.status === 'rejected') {
      // Rad etilgan zayavka "Jarayonda" ustunida qizil kartochka bo'lib turadi —
      // bosilganda oddiy jarayondagi holatga o'tadi. Bu yo'nalishda izoh kerak emas.
      updateTaskMutation.mutate({
        id: task.id,
        dto: { status: 'in_progress' },
      });

      return;
    }

    onRequireSolveComment?.(task);
  };

  return {
    toggleStatus,
    isPending: updateTaskMutation.isPending,
    mutation: updateTaskMutation,
  };
}
