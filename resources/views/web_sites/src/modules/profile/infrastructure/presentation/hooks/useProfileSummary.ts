import { useQuery } from '@tanstack/react-query';
import { httpProfileRepo } from '../../api/HttpProfileRepo';

export function useProfileSummary(userId: number) {
  return useQuery({
    queryKey: ['profile-summary', userId],
    queryFn: () => httpProfileRepo.getSummary(userId),
    enabled: Boolean(userId && !isNaN(userId)),
  });
}
