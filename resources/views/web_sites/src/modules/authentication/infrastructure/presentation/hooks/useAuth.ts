import { useAuthStore } from '@/shared/presentation/store/useAuthStore';
import { LogoutUseCase } from '../../../application/LogoutUseCase';
import { httpAuthRepo } from '../../api/HttpAuthRepo';

const logoutUseCase = new LogoutUseCase(httpAuthRepo);

export function useAuth() {
  const user = useAuthStore((state) => state.user);
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);
  const storeLogout = useAuthStore((state) => state.logout);

  const logout = async () => {
    // API xato bersa ham (masalan, token allaqachon o'lgan) lokal sessiya
    // tozalanadi — foydalanuvchi tizimda "qulflanib" qolmaydi.
    try {
      await logoutUseCase.execute();
    } finally {
      storeLogout();
    }
  };

  return {
    user,
    isAuthenticated,
    logout,
  };
}
