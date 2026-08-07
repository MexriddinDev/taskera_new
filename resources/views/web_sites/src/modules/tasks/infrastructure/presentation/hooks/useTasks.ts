import { useQuery } from '@tanstack/react-query';
import { TaskFilterParams } from '../../../domain/entities/Task';
import { GetTasksUseCase } from '../../../application/GetTasksUseCase';
import { httpTaskRepo } from '../../api/HttpTaskRepo';

const getTasksUseCase = new GetTasksUseCase(httpTaskRepo);

export function useTasks(params: TaskFilterParams) {
  return useQuery({
    queryKey: ['tasks', params],
    queryFn: () => getTasksUseCase.execute(params),
    // Zayavka ichida o'qilgandan keyin ro'yxat/kanbandagi
    // "o'qilmagan xabar" belgisi yo'qolishi uchun fokusda yangilanadi.
    refetchOnWindowFocus: true,
    staleTime: 0,
  });
}
