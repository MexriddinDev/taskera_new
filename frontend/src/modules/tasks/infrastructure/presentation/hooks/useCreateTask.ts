import { useMutation, useQueryClient } from '@tanstack/react-query';
import { CreateTaskDTO } from '../../../domain/entities/Task';
import { CreateTaskUseCase } from '../../../application/CreateTaskUseCase';
import { httpTaskRepo } from '../../api/HttpTaskRepo';

const createTaskUseCase = new CreateTaskUseCase(httpTaskRepo);

export function useCreateTask() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (dto: CreateTaskDTO | FormData) => createTaskUseCase.execute(dto),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tasks'] });
    },
  });
}
