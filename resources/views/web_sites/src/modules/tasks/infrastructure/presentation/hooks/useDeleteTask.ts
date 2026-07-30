import { useMutation, useQueryClient } from '@tanstack/react-query';
import { DeleteTaskUseCase } from '../../../application/DeleteTaskUseCase';
import { httpTaskRepo } from '../../api/HttpTaskRepo';

const deleteTaskUseCase = new DeleteTaskUseCase(httpTaskRepo);

export function useDeleteTask() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (id: number) => deleteTaskUseCase.execute(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['tasks'] });
    },
  });
}
