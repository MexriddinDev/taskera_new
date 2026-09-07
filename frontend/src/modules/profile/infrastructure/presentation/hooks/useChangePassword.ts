import { useMutation } from '@tanstack/react-query';
import { httpProfileRepo } from '../../api/HttpProfileRepo';
import { ChangePasswordPayload } from '../../../domain/entities/Profile';
import { useToastStore } from '@/shared/presentation/store/useToastStore';

export function useChangePassword() {
  const toast = useToastStore();

  return useMutation({
    mutationFn: (payload: ChangePasswordPayload) => httpProfileRepo.changePassword(payload),
    onSuccess: (data) => {
      toast.success(data.message || "Parol muvaffaqiyatli o'zgartirildi");
    },
    onError: (err: any) => {
      const msg = err.response?.data?.message || err.message || "Parolni o'zgartirishda xatolik yuz berdi";
      toast.error(msg);
    },
  });
}
