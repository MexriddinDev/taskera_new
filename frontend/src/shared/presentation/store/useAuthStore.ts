import { create } from 'zustand';
import { User, AuthSession } from '@/modules/authentication/domain/entities/User';
import { storage } from '@/shared/infrastructure/storage/localStorage';

interface AuthState {
  user: User | null;
  token: string | null;
  isAuthenticated: boolean;
  setSession: (session: AuthSession) => void;
  setUser: (user: User) => void;
  logout: () => void;
}

/**
 * Sessiyani localStorage'dan tiklaydi — ikkala kalit ham bo'lishi SHART.
 *
 * Ilgari `isAuthenticated` faqat `auth_token` ga qarardi, marshrut
 * qorovullari (`App.tsx` dagi PermissionRouteGuard va boshqalar) esa `user`
 * obyektini talab qiladi. Ikkisi mos kelmaganda ilova cheksiz aylanardi:
 *
 *   LoginPage: isAuthenticated -> <Navigate to="/dashboard">
 *   Guard:     !user           -> <Navigate to="/login">
 *   ...va boshidan.
 *
 * O'lchangan: 4 soniyada 484 ta navigatsiya, React "Maximum update depth
 * exceeded" bilan to'xtardi va ilova umuman ochilmasdi — foydalanuvchi hatto
 * login sahifasiga ham chiqa olmasdi.
 *
 * Bu nomutanosiblik haqiqatda yuzaga keladi: `storage.get` JSON.parse xatosini
 * jimgina yutib `null` qaytaradi, `storage.set` esa yozuv muvaffaqiyatsizligini
 * faqat `console.error` qiladi.
 *
 * Yarim holat saqlanib qolmasligi uchun qolgan kalit ham o'chiriladi: aks holda
 * `axiosClient` egasiz tokenni `Authorization` sarlavhasida yuboraverardi.
 */
const restoreSession = (): Pick<AuthState, 'user' | 'token' | 'isAuthenticated'> => {
  const token = storage.get<string>('auth_token');
  const user = storage.get<User>('auth_user');

  if (!token || !user) {
    if (token || user) {
      storage.remove('auth_token');
      storage.remove('auth_user');
    }

    return { user: null, token: null, isAuthenticated: false };
  }

  return { user, token, isAuthenticated: true };
};

export const useAuthStore = create<AuthState>((set) => ({
  ...restoreSession(),

  setSession: (session: AuthSession) => {
    storage.set('auth_token', session.token);
    storage.set('auth_user', session.user);
    set({
      user: session.user,
      token: session.token,
      isAuthenticated: true,
    });
  },

  setUser: (user: User) => {
    storage.set('auth_user', user);
    set({ user });
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
