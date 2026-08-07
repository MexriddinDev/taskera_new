import React, { useState, useEffect } from 'react';
import { X, Star, CheckCircle, Clock, UserCheck, ClipboardList } from 'lucide-react';
import { useUpdateTask } from '../hooks/useUpdateTask';
import { Task } from '../../../domain/entities/Task';

interface RateTaskModalProps {
  task: Task | null;
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

export const RateTaskModal: React.FC<RateTaskModalProps> = ({ task, isOpen, onClose, onSuccess }) => {
  const [rating, setRating] = useState<number>(0);
  const [hoverRating, setHoverRating] = useState<number>(0);
  const updateTaskMutation = useUpdateTask();

  // Har ochilishda yulduzcha tanlanmagan (0) holatga qaytadi
  useEffect(() => {
    if (isOpen) {
      setRating(0);
      setHoverRating(0);
    }
  }, [isOpen, task?.id]);

  if (!isOpen || !task) return null;

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    updateTaskMutation.mutate(
      {
        id: task.id,
        dto: {
          status: 'done',
          completed: true,
          clientRating: rating,
        },
      },
      {
        onSuccess: () => {
          onSuccess();
          onClose();
        },
      }
    );
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-fadeIn">
      <div className="bg-white dark:bg-slate-800 rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-700 space-y-6 relative overflow-hidden text-center">
        <button
          onClick={onClose}
          className="absolute top-4 right-4 p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors"
        >
          <X className="w-5 h-5" />
        </button>

        <div className="w-16 h-16 rounded-full bg-success-50 dark:bg-success-700/20 text-success-500 mx-auto flex items-center justify-center border border-success-500/30">
          <CheckCircle className="w-8 h-8" />
        </div>

        <div>
          <h2 className="text-xl font-extrabold text-slate-900 dark:text-slate-100">Bajarilgan ishni baholash</h2>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
            Quyidagi zayavka bo'yicha xizmat sifatini baholang va yopilishini tasdiqlang
          </p>
        </div>

        {/* Baholanayotgan zayavka ma'lumotlari */}
        <div className="w-full p-4 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-left space-y-2.5">
          <div className="flex items-center justify-between">
            <span className="text-[11px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center space-x-1.5">
              <ClipboardList className="w-3.5 h-3.5" />
              <span>Baholanayotgan zayavka</span>
            </span>
            <span className="font-mono text-xs font-black text-brand-600 dark:text-brand-400">
              #{task.ticketNumber}
            </span>
          </div>

          <p className="text-sm font-bold text-slate-900 dark:text-slate-100 leading-snug line-clamp-3">
            {task.todo}
          </p>

          <div className="flex items-center justify-between text-[11px] font-medium text-slate-500 dark:text-slate-400 pt-2 border-t border-slate-200 dark:border-slate-700">
            <span className="flex items-center space-x-1.5">
              <Clock className="w-3.5 h-3.5" />
              <span>{task.createdAt}</span>
            </span>
            <span className="flex items-center space-x-1.5">
              <UserCheck className="w-3.5 h-3.5" />
              <span>{task.assignedTo || 'Biriktirilmagan'}</span>
            </span>
          </div>
        </div>

        {/* Star Rating Selection */}
        <div className="flex items-center justify-center space-x-2 py-3">
          {[1, 2, 3, 4, 5].map((star) => {
            const active = star <= (hoverRating || rating);
            return (
              <button
                key={star}
                type="button"
                onMouseEnter={() => setHoverRating(star)}
                onMouseLeave={() => setHoverRating(0)}
                onClick={() => setRating(star)}
                className="p-1 text-2xl transition-transform hover:scale-125 focus:outline-none"
              >
                <Star
                  className={`w-8 h-8 ${
                    active
                      ? 'fill-amber-400 text-amber-400'
                      : 'text-slate-300 dark:text-slate-600'
                  }`}
                />
              </button>
            );
          })}
        </div>

        <p className="text-xs font-bold text-amber-500">
          {rating === 0 && 'Iltimos, yulduzcha tanlang'}
          {rating === 5 && "A'lo barakalla!"}
          {rating === 4 && 'Yaxshi'}
          {rating === 3 && 'Qoniqarli'}
          {rating <= 2 && rating > 0 && 'Qoniqarsiz'}
        </p>

        {/* Footer Actions */}
        <div className="flex items-center space-x-3 pt-4 border-t border-slate-100 dark:border-slate-700">
          <button
            type="button"
            onClick={onClose}
            className="flex-1 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 font-bold text-xs hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors"
          >
            Bekor qilish
          </button>

          <button
            type="button"
            onClick={handleSubmit}
            disabled={rating === 0 || updateTaskMutation.isPending}
            className="flex-1 py-2.5 rounded-xl bg-success-500 hover:bg-success-600 active:bg-success-700 text-white font-bold text-xs shadow-md transition-all disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
          >
            Tasdiqlash & Yopish
          </button>
        </div>
      </div>
    </div>
  );
};
