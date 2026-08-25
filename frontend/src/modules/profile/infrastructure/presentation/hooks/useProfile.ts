import { useQuery } from '@tanstack/react-query';
import { GetProfileUseCase } from '../../../application/GetProfileUseCase';
import { httpProfileRepo } from '../../api/HttpProfileRepo';

const getProfileUseCase = new GetProfileUseCase(httpProfileRepo);

export function useProfile(userId: number) {
  return useQuery({
    queryKey: ['profile', userId],
    queryFn: () => getProfileUseCase.execute(userId),
    enabled: Boolean(userId && !isNaN(userId)),
  });
}
