import { useMutation } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { httpAuthRepo } from '../../api/HttpAuthRepo';
import { LoginUseCase } from '../../../application/LoginUseCase';
import { LoginCredentials } from '../../../domain/entities/User';
import { useAuthStore } from '@/shared/presentation/store/useAuthStore';

const loginUseCase = new LoginUseCase(httpAuthRepo);

export function useLogin() {
  const navigate = useNavigate();
  const setSession = useAuthStore((state) => state.setSession);

  return useMutation({
    mutationFn: (credentials: LoginCredentials) => loginUseCase.execute(credentials),
    onSuccess: (session) => {
      setSession(session);
      navigate('/dashboard', { replace: true });
    },
  });
}
