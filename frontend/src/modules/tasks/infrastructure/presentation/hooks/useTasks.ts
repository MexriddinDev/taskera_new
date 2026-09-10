import { useQuery } from '@tanstack/react-query';
import { TaskFilterParams } from '../../../domain/entities/Task';
import { GetTasksUseCase } from '../../../application/GetTasksUseCase';
import { httpTaskRepo } from '../../api/HttpTaskRepo';

const getTasksUseCase = new GetTasksUseCase(httpTaskRepo);

export function useTasks(params: TaskFilterParams, options?: { enabled?: boolean }) {
  return useQuery({
    queryKey: ['tasks', params],
    queryFn: () => getTasksUseCase.execute(params),
    // Ayrim so'rovlar huquqqa bog'liq (masalan dispetcher ustuni) — huquq
    // bo'lmasa so'rov umuman yuborilmasin.
    enabled: options?.enabled ?? true,
    // Zayavka ichida o'qilgandan keyin ro'yxat/kanbandagi
    // "o'qilmagan xabar" belgisi yo'qolishi uchun fokusda yangilanadi.
    refetchOnWindowFocus: true,
    staleTime: 10_000,
  });
}
