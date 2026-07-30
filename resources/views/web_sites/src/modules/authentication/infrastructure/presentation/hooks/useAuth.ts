import { useAuthStore } from '@/shared/presentation/store/useAuthStore';
import { LogoutUseCase } from '../../../application/LogoutUseCase';
import { httpAuthRepo } from '../../api/HttpAuthRepo';

const logoutUseCase = new LogoutUseCase(httpAuthRepo);

export function useAuth() {
  const user = useAuthStore((state) => state.user);
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);
  const storeLogout = useAuthStore((state) => state.logout);

  const logout = async () => {
    await logoutUseCase.execute();
    storeLogout();
  };

  return {
    user,
    isAuthenticated,
    logout,
  };
}
