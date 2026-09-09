import { useMutation, useQueryClient } from '@tanstack/react-query';
import { CreateTaskDTO } from '../../../domain/entities/Task';
import { CreateTaskUseCase } from '../../../application/CreateTaskUseCase';
import { httpTaskRepo } from '../../api/HttpTaskRepo';

const createTaskUseCase = new CreateTaskUseCase(httpTaskRepo);

/** @param onProgress Fayllar yuklanish foizi (0-100). Faqat FormData yuborilganda chaqiriladi. */
export function useCreateTask(onProgress?: (percent: number) => void) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (dto: CreateTaskDTO | FormData) => createTaskUseCase.execute(dto, onProgress),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tasks'] });
    },
  });
}
