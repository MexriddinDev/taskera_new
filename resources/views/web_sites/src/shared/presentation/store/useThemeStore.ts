import { create } from 'zustand';
import { storage } from '@/shared/infrastructure/storage/localStorage';

interface ThemeState {
  theme: 'light' | 'dark';
  toggleTheme: () => void;
}

const getInitialTheme = (): 'light' | 'dark' => {
  const saved = storage.get<'light' | 'dark'>('theme');
  if (saved) return saved;
  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
};

export const useThemeStore = create<ThemeState>((set) => {
  const initialTheme = getInitialTheme();
  if (initialTheme === 'dark') {
    document.documentElement.classList.add('dark');
  } else {
    document.documentElement.classList.remove('dark');
  }

  return {
    theme: initialTheme,
    toggleTheme: () => {
      set((state) => {
        const nextTheme = state.theme === 'light' ? 'dark' : 'light';
        storage.set('theme', nextTheme);
        if (nextTheme === 'dark') {
          document.documentElement.classList.add('dark');
        } else {
          document.documentElement.classList.remove('dark');
        }
        return { theme: nextTheme };
      });
    },
  };
});
