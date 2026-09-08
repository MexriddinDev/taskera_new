/**
 * Foydalanuvchini qaysi sahifaga yuborish kerakligini bitta joyda hal qiladi.
 *
 * Boshqaruv paneli (`/dashboard`) — barcha zayavkalarning umumiy ko'rinishi —
 * `dashboard.view` huquqini talab qiladi. Rolda bu huquq bo'lmasa foydalanuvchini
 * ko'r-ko'rona `/dashboard` ga yuborib bo'lmaydi: route qorovuli uni darhol
 * qaytarib yuborardi. Shuning uchun tushish nuqtasi huquqlar bo'yicha tanlanadi.
 */
export type CanFn = (permission: string | string[]) => boolean;

/** Xodim bo'lmagan foydalanuvchi uchun tushish nuqtasi. */
export const nonStaffHomePath = (can: CanFn): string =>
  can('tickets.view_own') ? '/requests' : '/knowledge';

/** Xodim uchun mavjud bo'lgan birinchi sahifa. */
export const staffHomePath = (can: CanFn): string => {
  if (can('dashboard.view')) return '/dashboard';
  if (can('my_tasks.view')) return '/my-tasks';
  if (can('monitoring.view')) return '/monitoring';
  if (can('team_workload.view')) return '/team-workload';
  return nonStaffHomePath(can);
};

/** Foydalanuvchi turiga qarab bosh sahifa. */
export const homePathFor = (can: CanFn, isStaff: boolean): string =>
  isStaff ? staffHomePath(can) : nonStaffHomePath(can);
