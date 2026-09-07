import { useQuery } from '@tanstack/react-query';
import { GetTaskByIdUseCase } from '../../../application/GetTaskByIdUseCase';
import { httpTaskRepo } from '../../api/HttpTaskRepo';

const getTaskByIdUseCase = new GetTaskByIdUseCase(httpTaskRepo);

export function useTaskDetail(id: number) {
  return useQuery({
    queryKey: ['task', id],
    queryFn: () => getTaskByIdUseCase.execute(id),
    enabled: Boolean(id && !isNaN(id)),
    staleTime: 3_000,
    refetchInterval: (query) => {
      const task = query.state.data;
      const isClosed = task?.status === 'done' || task?.status === 'rejected';
      return document.visibilityState === 'visible' && !isClosed ? 5_000 : false;
    },
    refetchIntervalInBackground: false,
  });
}
