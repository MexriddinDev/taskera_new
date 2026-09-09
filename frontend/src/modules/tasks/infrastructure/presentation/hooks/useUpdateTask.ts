import { useMutation, useQueryClient, type QueryKey } from '@tanstack/react-query';
import { Task, UpdateTaskDTO } from '../../../domain/entities/Task';
import { UpdateTaskUseCase } from '../../../application/UpdateTaskUseCase';
import { httpTaskRepo } from '../../api/HttpTaskRepo';
import { useAuthStore } from '@/shared/presentation/store/useAuthStore';

const updateTaskUseCase = new UpdateTaskUseCase(httpTaskRepo);

type TasksPage = { tasks: Task[] } & Record<string, unknown>;

/** Serverga borib kelmasdan, kartochkaning kutilayotgan holatini qaytaradi. */
const applyLocally = (task: Task, dto: UpdateTaskDTO): Task => {
  const user = useAuthStore.getState().user;
  const next: Task = { ...task };

  if (dto.assignToMe && user) {
    next.isAssigned = true;
    next.assignedUserId = user.id;
    next.assignedTo = [user.firstName, user.lastName].filter(Boolean).join(' ') || user.username;
    next.assignedUserAvatar = user.image || next.assignedUserAvatar;
  }
  if (dto.status) {
    next.status = dto.status;
  }
  if (dto.completed) {
    next.status = 'done';
  }

  return next;
};

export function useUpdateTask() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, dto }: { id: number; dto: UpdateTaskDTO }) => updateTaskUseCase.execute(id, dto),

    /**
     * Optimistik yangilanish: "Qabul qilish" bosilishi bilan kartochka
     * navbatdan chiqib, xodimning "Ochiq" ustuniga o'tadi. Ilgari bu server
     * javobi va uch-to'rtta qayta so'rov tugagach sodir bo'lardi — shuning
     * uchun bosishdan keyin sezilarli kechikish bor edi.
     */
    onMutate: async ({ id, dto }) => {
      await queryClient.cancelQueries({ queryKey: ['tasks'] });

      const queries = queryClient.getQueryCache().findAll({ queryKey: ['tasks'] });
      const snapshots: [QueryKey, unknown][] = [];

      // Kartochka qaysidir ro'yxatda bor — o'shandan nusxa olinadi.
      let patched: Task | undefined;
      for (const query of queries) {
        const data = query.state.data as TasksPage | undefined;
        const found = data?.tasks?.find((task) => task.id === id);
        if (found) {
          patched = applyLocally(found, dto);
          break;
        }
      }

      for (const query of queries) {
        const data = query.state.data as TasksPage | undefined;
        if (!data?.tasks) continue;

        snapshots.push([query.queryKey, data]);

        let tasks = data.tasks.map((task) => (task.id === id ? applyLocally(task, dto) : task));

        // "Mening vazifalarim" ro'yxatida zayavka hali yo'q — qabul qilingach
        // u darhol shu ro'yxatning boshida ko'rinishi kerak.
        const scope = (query.queryKey[1] as { scope?: string } | undefined)?.scope;
        if (dto.assignToMe && scope === 'my_tasks' && patched && !tasks.some((task) => task.id === id)) {
          tasks = [patched, ...tasks];
        }

        queryClient.setQueryData(query.queryKey, { ...data, tasks });
      }

      return { snapshots };
    },

    onError: (_error, _variables, context) => {
      // Server rad etsa (masalan limit yoki yopilmagan qaytarilgan zayavka) —
      // ro'yxatlar avvalgi holatiga qaytariladi.
      context?.snapshots.forEach(([key, data]) => queryClient.setQueryData(key, data));
    },

    onSuccess: (_data, variables) => {
      queryClient.invalidateQueries({ queryKey: ['task', variables.id] });
      window.dispatchEvent(new Event('tickets:changed'));
    },

    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: ['tasks'] });
    },
  });
}
