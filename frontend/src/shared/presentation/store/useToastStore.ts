import { create } from 'zustand';

export interface ToastMessage {
  id: string;
  type: 'success' | 'error' | 'warning' | 'info';
  title?: string;
  message: string;
}

interface ToastState {
  toasts: ToastMessage[];
  showToast: (toast: Omit<ToastMessage, 'id'>) => void;
  removeToast: (id: string) => void;
  success: (message: string, title?: string) => void;
  error: (message: string, title?: string) => void;
  warning: (message: string, title?: string) => void;
  info: (message: string, title?: string) => void;
}

// Monoton hisoblagich: Math.random().toString(36).substring(2, 9) juda qisqa
// (hatto 1 belgili) id berishi mumkin edi, ya'ni bir tikda chiqqan ikki toast
// bir xil key olishi va removeToast ikkalasini birdan o'chirishi mumkin edi.
let toastSequence = 0;

/**
 * Bildirishnomalar navbati.
 *
 * O'z-o'zidan yo'qolish YO'Q: bildirishnoma ekran o'rtasida oyna bo'lib
 * chiqadi va foydalanuvchi "OK" bosgunicha turadi. Ilgari u pastki burchakda
 * bir necha soniyada o'chib ketardi — ishlayotgan xodim xabarni umuman
 * ko'rmay qolishi mumkin edi.
 */
export const useToastStore = create<ToastState>((set, get) => ({
  toasts: [],
  showToast: (toast) => {
    toastSequence += 1;
    const newToast: ToastMessage = { ...toast, id: `toast-${toastSequence}` };

    set((state) => ({ toasts: [...state.toasts, newToast] }));
  },
  removeToast: (id) => {
    set((state) => ({ toasts: state.toasts.filter((t) => t.id !== id) }));
  },
  success: (message, title) => get().showToast({ type: 'success', message, title }),
  error: (message, title) => get().showToast({ type: 'error', message, title }),
  warning: (message, title) => get().showToast({ type: 'warning', message, title }),
  info: (message, title) => get().showToast({ type: 'info', message, title }),
}));
