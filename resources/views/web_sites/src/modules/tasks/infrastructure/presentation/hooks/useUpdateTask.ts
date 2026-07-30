import { useMutation, useQueryClient } from '@tanstack/react-query';
import { UpdateTaskDTO } from '../../../domain/entities/Task';
import { UpdateTaskUseCase } from '../../../application/UpdateTaskUseCase';
import { httpTaskRepo } from '../../api/HttpTaskRepo';

const updateTaskUseCase = new UpdateTaskUseCase(httpTaskRepo);

export function useUpdateTask() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, dto }: { id: number; dto: UpdateTaskDTO }) => updateTaskUseCase.execute(id, dto),
    onSuccess: (_data, variables) => {
      queryClient.invalidateQueries({ queryKey: ['tasks'] });
      queryClient.invalidateQueries({ queryKey: ['task', variables.id] });
    },
  });
}
