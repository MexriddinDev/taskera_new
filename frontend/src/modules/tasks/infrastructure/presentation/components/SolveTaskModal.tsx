import React, { useState } from 'react';
import { X, CheckCircle2 } from 'lucide-react';
import { useUpdateTask } from '../hooks/useUpdateTask';
import { Task } from '../../../domain/entities/Task';
import { useT } from '@/shared/presentation/i18n/i18n';

interface SolveTaskModalProps {
  task: Task | null;
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

/**
 * Zayavkani yakunlash oynasi — yechim izohi MAJBURIY.
 *
 * Ilgari zayavka uch xil joydan izohsiz yopilardi va tizim o'zi
 * "Vazifa bajarildi" degan matnni qo'yib yuborardi. Natijada yopilgan
 * zayavkalarda nima qilingani yozilmay qolardi. Endi barcha yo'llar shu
 * oynadan o'tadi va bo'sh izoh bilan yuborib bo'lmaydi.
 */
export const SolveTaskModal: React.FC<SolveTaskModalProps> = ({ task, isOpen, onClose, onSuccess }) => {
  const t = useT();
  const [comment, setComment] = useState('');
  const [error, setError] = useState<string | null>(null);
  const updateTaskMutation = useUpdateTask();

  if (!isOpen || !task) return null;

  const handleClose = () => {
    setComment('');
    setError(null);
    onClose();
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();

    if (!comment.trim()) {
      setError(t('solveTask.commentRequired'));
      return;
    }

    setError(null);
    updateTaskMutation.mutate(
      {
        id: task.id,
        dto: {
          status: 'done',
          completed: true,
          solutionComment: comment.trim(),
        },
      },
      {
        onSuccess: () => {
          setComment('');
          onSuccess();
          onClose();
        },
        onError: (err: any) => {
          setError(err.response?.data?.message || err.message || t('common.errorGeneric'));
        },
      }
    );
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-fadeIn">
      <div className="bg-white dark:bg-slate-800 rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-700 space-y-6 relative overflow-hidden">
        <button
          onClick={handleClose}
          className="absolute top-4 right-4 p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors"
        >
          <X className="w-5 h-5" />
        </button>

        <div className="w-14 h-14 rounded-full bg-success-50 dark:bg-success-700/20 text-success-500 mx-auto flex items-center justify-center border border-success-500/30">
          <CheckCircle2 className="w-7 h-7" />
        </div>

        <div className="text-center">
          <h2 className="text-xl font-extrabold text-slate-900 dark:text-slate-100">{t('solveTask.title')}</h2>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
            {task.ticketNumber}
          </p>
        </div>

        {error && (
          <div className="p-3 rounded-xl bg-error-50 dark:bg-error-700/20 border border-error-500/20 text-error-500 text-xs font-semibold">
            {error}
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
              {t('solveTask.commentLabel')} *
            </label>
            <textarea
              rows={4}
              autoFocus
              value={comment}
              onChange={(e) => setComment(e.target.value)}
              placeholder={t('solveTask.commentPlaceholder')}
              className="w-full px-4 py-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 text-slate-900 dark:text-slate-100 text-sm focus:ring-2 focus:ring-success-500 focus:outline-none transition-all"
              required
            />
          </div>

          <div className="flex items-center space-x-3 pt-4 border-t border-slate-100 dark:border-slate-700">
            <button
              type="button"
              onClick={handleClose}
              className="flex-1 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 font-bold text-xs hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors"
            >
              {t('common.cancel')}
            </button>

            <button
              type="submit"
              disabled={updateTaskMutation.isPending || !comment.trim()}
              className="flex-1 inline-flex items-center justify-center space-x-2 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 active:bg-emerald-700 text-white font-bold text-xs shadow-md transition-all disabled:opacity-50 cursor-pointer"
            >
              <span>{t('solveTask.submit')}</span>
              <CheckCircle2 className="w-4 h-4" />
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};
