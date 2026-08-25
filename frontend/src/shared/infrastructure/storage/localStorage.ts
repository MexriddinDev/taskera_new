export const storage = {
  get<T>(key: string, defaultValue: T | null = null): T | null {
    try {
      const item = localStorage.getItem(key);
      return item ? JSON.parse(item) : defaultValue;
    } catch {
      return defaultValue;
    }
  },

  set<T>(key: string, value: T): void {
    try {
      localStorage.setItem(key, JSON.stringify(value));
    } catch (e) {
      console.error(`Failed to save key "${key}" to localStorage:`, e);
    }
  },

  remove(key: string): void {
    try {
      localStorage.removeItem(key);
    } catch (e) {
      console.error(`Failed to remove key "${key}" from localStorage:`, e);
    }
  },

  clear(): void {
    try {
      localStorage.clear();
    } catch (e) {
      console.error('Failed to clear localStorage:', e);
    }
  },
};
