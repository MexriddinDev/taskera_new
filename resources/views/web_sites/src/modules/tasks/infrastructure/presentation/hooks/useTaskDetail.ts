import { useQuery } from '@tanstack/react-query';
import { GetTaskByIdUseCase } from '../../../application/GetTaskByIdUseCase';
import { httpTaskRepo } from '../../api/HttpTaskRepo';

const getTaskByIdUseCase = new GetTaskByIdUseCase(httpTaskRepo);

export function useTaskDetail(id: number) {
  return useQuery({
    queryKey: ['task', id],
    queryFn: () => getTaskByIdUseCase.execute(id),
    enabled: Boolean(id && !isNaN(id)),
  });
}
