import { create } from 'zustand';
import { User, AuthSession } from '@/modules/authentication/domain/entities/User';
import { storage } from '@/shared/infrastructure/storage/localStorage';

interface AuthState {
  user: User | null;
  token: string | null;
  isAuthenticated: boolean;
  setSession: (session: AuthSession) => void;
  logout: () => void;
}

export const useAuthStore = create<AuthState>((set) => ({
  user: storage.get<User>('auth_user'),
  token: storage.get<string>('auth_token'),
  isAuthenticated: Boolean(storage.get<string>('auth_token')),

  setSession: (session: AuthSession) => {
    storage.set('auth_token', session.token);
    storage.set('auth_user', session.user);
    set({
      user: session.user,
      token: session.token,
      isAuthenticated: true,
    });
  },

  logout: () => {
    storage.remove('auth_token');
    storage.remove('auth_user');
    set({
      user: null,
      token: null,
      isAuthenticated: false,
    });
  },
}));
