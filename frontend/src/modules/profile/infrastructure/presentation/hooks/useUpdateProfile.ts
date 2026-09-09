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
        // Profil javobida huquqlar va `isStaff` yo'q (u UserProfile shakli).
        // Ilgari u to'g'ridan-to'g'ri yozilardi va navbardagi barcha havolalar
        // keyingi `/auth/me` sinxronizatsiyasigacha yo'qolib turardi.
        const current = useAuthStore.getState().user;
        setUser({ ...(current as any), ...(data.user as any) });
      }
      toast.success(data.message || "Profil ma'lumotlari muvaffaqiyatli saqlandi");
    },
    onError: (err: any) => {
      const msg = err.response?.data?.message || err.message || "Xatolik yuz berdi";
      toast.error(msg);
    },
  });
}
