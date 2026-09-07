import { useMutation, useQueryClient } from '@tanstack/react-query';
import { httpProfileRepo } from '../../api/HttpProfileRepo';
import { UpdateProfilePayload } from '../../../domain/entities/Profile';
import { useAuthStore } from '@/shared/presentation/store/useAuthStore';
import { useToastStore } from '@/shared/presentation/store/useToastStore';

export function useUpdateProfile() {
  const queryClient = useQueryClient();
  const setUser = useAuthStore((state) => state.setUser);
  const toast = useToastStore();

  return useMutation({
    mutationFn: (payload: UpdateProfilePayload) => httpProfileRepo.updateProfile(payload),
    onSuccess: (data) => {
      queryClient.invalidateQueries({ queryKey: ['profile'] });
      queryClient.invalidateQueries({ queryKey: ['auth', 'me'] });
      if (data.user) {
        setUser(data.user as any);
      }
      toast.success(data.message || "Profil ma'lumotlari muvaffaqiyatli saqlandi");
    },
    onError: (err: any) => {
      const msg = err.response?.data?.message || err.message || "Xatolik yuz berdi";
      toast.error(msg);
    },
  });
}
