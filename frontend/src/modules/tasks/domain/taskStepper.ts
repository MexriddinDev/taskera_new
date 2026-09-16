/**
 * Zayavka yo'lakchasi (stepper) qadamlari.
 *
 * Bu yerda faqat hisob: qaysi qadamlar ko'rinadi va qaysi biri joriy. Chizish
 * `TaskDetailPage` da, shuning uchun qoidani sinovdan o'tkazish uchun sahifani
 * ko'tarish shart emas.
 */

export type TaskStepKey = 'todo' | 'in_progress' | 'done' | 'rated' | 'rejected';

export interface TaskStep {
  key: TaskStepKey;
  /** `translations.ts` dagi kalit. */
  labelKey: string;
}

export interface TaskStepper {
  steps: TaskStep[];
  /** Joriy qadamning `steps` ichidagi o'rni. */
  currentIndex: number;
}

const STEP: Record<TaskStepKey, TaskStep> = {
  todo: { key: 'todo', labelKey: 'taskDetail.stepTodo' },
  in_progress: { key: 'in_progress', labelKey: 'taskDetail.stepInProgress' },
  done: { key: 'done', labelKey: 'taskDetail.stepDone' },
  rated: { key: 'rated', labelKey: 'taskDetail.stepRated' },
  rejected: { key: 'rejected', labelKey: 'taskDetail.stepRejected' },
};

const REJECTED_STATUSES = ['rejected', 'stopped', 'cancelled'];

/**
 * Zayavka holatiga qarab yo'lakchani yig'adi.
 *
 * MUHIM: "Rad etildi" — YAKUNIY holat, bosqich EMAS. Ilgari u qadamlar
 * ro'yxatida "Jarayonda" bilan "Bajarildi" orasida qotib turardi, ya'ni
 * bajarilgan (va baholangan) zayavkada ham ko'rinardi — hatto "o'tilgan
 * bosqich" sifatida yashil bo'lib. So'rovchi buni "baholadim, keyin rad
 * etilibdi" deb o'qirdi. Endi u faqat zayavka HAQIQATAN rad etilgan bo'lsa
 * chiqadi va oxirgi qadam bo'ladi.
 *
 * Baxtli yo'l: Ochiq -> Jarayonda -> Bajarildi -> Baholandi.
 * Rad etilgan yo'l: Ochiq -> Jarayonda -> Rad etildi.
 */
export const buildTaskStepper = (
  status: string | null | undefined,
  completed: boolean | undefined,
  clientRating: number | null | undefined,
): TaskStepper => {
  const normalized = (status ?? '').toLowerCase().replace(/\s+/g, '_');

  if (REJECTED_STATUSES.includes(normalized)) {
    return { steps: [STEP.todo, STEP.in_progress, STEP.rejected], currentIndex: 2 };
  }

  const steps = [STEP.todo, STEP.in_progress, STEP.done, STEP.rated];
  const isDone = normalized === 'done' || completed === true;

  // Baho faqat bajarilgan ishga qo'yiladi: eski yozuvda baho qolib ketgan
  // bo'lsa ham, ochiq zayavka "Baholandi" ga sakrab ketmasin.
  if (isDone && typeof clientRating === 'number' && clientRating > 0) {
    return { steps, currentIndex: 3 };
  }

  if (isDone) return { steps, currentIndex: 2 };
  if (normalized === 'in_progress') return { steps, currentIndex: 1 };

  return { steps, currentIndex: 0 };
};
