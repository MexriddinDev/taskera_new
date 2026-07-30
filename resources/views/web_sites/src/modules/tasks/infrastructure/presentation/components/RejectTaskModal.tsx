import React, { useState } from 'react';
import { X, AlertTriangle, RotateCcw } from 'lucide-react';
import { useUpdateTask } from '../hooks/useUpdateTask';
import { Task } from '../../../domain/entities/Task';

interface RejectTaskModalProps {
  task: Task | null;
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

export const RejectTaskModal: React.FC<RejectTaskModalProps> = ({ task, isOpen, onClose, onSuccess }) => {
  const [reason, setReason] = useState('');
  const [error, setError] = useState<string | null>(null);
  const updateTaskMutation = useUpdateTask();

  if (!isOpen || !task) return null;

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!reason.trim()) {
      setError('Qaytarish sababini kiritishingiz shart');
      return;
    }

    setError(null);
    updateTaskMutation.mutate(
      {
        id: task.id,
        dto: {
          status: 'rejected',
          rejectionReason: reason,
        },
      },
      {
        onSuccess: () => {
          setReason('');
          onSuccess();
          onClose();
        },
        onError: (err: any) => {
          setError(err.message || 'Xatolik yuz berdi');
        },
      }
    );
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-fadeIn">
      <div className="bg-white dark:bg-slate-800 rounded-3xl max-w-md w-full p-6 shadow-2xl border border-slate-200 dark:border-slate-700 space-y-6 relative overflow-hidden">
        <button
          onClick={onClose}
          className="absolute top-4 right-4 p-2 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors"
        >
          <X className="w-5 h-5" />
        </button>

        <div className="w-14 h-14 rounded-full bg-error-50 dark:bg-error-700/20 text-error-500 mx-auto flex items-center justify-center border border-error-500/30">
          <AlertTriangle className="w-7 h-7" />
        </div>

        <div className="text-center">
          <h2 className="text-xl font-extrabold text-slate-900 dark:text-slate-100">Yechimni rad etish / Qaytarish</h2>
          <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
            Agar ish aytilganidek bajarilmay yopilgan bo'lsa, sababini yozib mas'ul xodimga qaytarishingiz mumkin
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
              Qaytarish sababi *
            </label>
            <textarea
              rows={3}
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              placeholder="Masalan: Kompyuter hali ham yoqilmayapti yoki printer kartridji almashtirilmagan"
              className="w-full px-4 py-3 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 text-slate-900 dark:text-slate-100 text-sm focus:ring-2 focus:ring-error-500 focus:outline-none transition-all"
              required
            />
          </div>

          <div className="flex items-center space-x-3 pt-4 border-t border-slate-100 dark:border-slate-700">
            <button
              type="button"
              onClick={onClose}
              className="flex-1 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 font-bold text-xs hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors"
            >
              Bekor qilish
            </button>

            <button
              type="submit"
              disabled={updateTaskMutation.isPending}
              className="flex-1 inline-flex items-center justify-center space-x-2 py-2.5 rounded-xl bg-error-500 hover:bg-error-600 active:bg-error-700 text-white font-bold text-xs shadow-md transition-all disabled:opacity-50 cursor-pointer"
            >
              <span>Qaytarish (Reject)</span>
              <RotateCcw className="w-4 h-4" />
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};
