import React, { useEffect, useState } from 'react';
import { Loader2, UserCheck } from 'lucide-react';
import { Task } from '../../../domain/entities/Task';
import { Modal } from '@/shared/presentation/components/Modal';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { useToastStore } from '@/shared/presentation/store/useToastStore';
import { useAuthStore } from '@/shared/presentation/store/useAuthStore';
import { useT } from '@/shared/presentation/i18n/i18n';
import { initialsAvatar } from '@/shared/presentation/components/initialsAvatar';

interface AssignableStaff {
  id: number;
  name: string;
  username: string;
  image?: string;
}

interface AssignTaskModalProps {
  task: Task | null;
  isOpen: boolean;
  onClose: () => void;
  onAssigned: () => void;
}

/**
 * Zayavkani support xodimiga biriktirish oynasi.
 *
 * Ro'yxatni backend beradi: `/tickets/assignable-staff` biriktirish huquqi
 * bo'lgan foydalanuvchiga butun tashkilot xodimlarini qaytaradi.
 */
export const AssignTaskModal: React.FC<AssignTaskModalProps> = ({ task, isOpen, onClose, onAssigned }) => {
  const t = useT();
  const toast = useToastStore();
  const currentUserId = useAuthStore((state) => state.user?.id);
  const [staff, setStaff] = useState<AssignableStaff[]>([]);
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [reason, setReason] = useState('');
  const [isSaving, setIsSaving] = useState(false);

  useEffect(() => {
    if (!isOpen) return;
    setSelectedId(null);
    setReason('');
    axiosClient.get('/tickets/assignable-staff')
      .then((res) => {
        const list = res.data?.data || [];
        setStaff(list.map((item: any) => ({
          id: item.id,
          name: item.name || item.username,
          username: item.username,
          image: item.image,
        })));
      })
      .catch(() => setStaff([]));
  }, [isOpen]);

  const submit = async () => {
    if (!task || !selectedId) return;
    setIsSaving(true);
    try {
      await axiosClient.post(`/tickets/${task.id}/assign`, {
        assignee_user_id: selectedId,
        reason: reason.trim() || t('assignModal.defaultReason'),
      });
      toast.success(t('assignModal.success'));
      onAssigned();
      onClose();
    } catch (e: any) {
      const message = e?.response?.data?.errors?.reason?.[0]
        || e?.response?.data?.message
        || t('assignModal.failed');
      toast.error(message);
    } finally {
      setIsSaving(false);
    }
  };

  if (!task) return null;

  return (
    <Modal isOpen={isOpen} onClose={onClose} title={t('assignModal.title', { ticket: task.ticketNumber || `#${task.id}` })}>
      <div className="space-y-4">
        <p className="text-xs font-semibold text-slate-500 dark:text-slate-400 line-clamp-2">{task.todo}</p>

        {/* "O'zimga" — ro'yxatdan o'zini qidirmaslik uchun yorliq. Backend
            o'ziga biriktirishni qo'llab-quvvatlaydi: egasiz zayavkani olishga
            ishlash huquqi yetadi (TicketController::assign). */}
        {currentUserId && (
          <button
            type="button"
            onClick={() => setSelectedId(currentUserId)}
            aria-pressed={selectedId === currentUserId}
            className={`w-full flex items-center gap-3 p-3 rounded-2xl border text-left font-bold text-xs transition-colors ${
              selectedId === currentUserId
                ? 'border-brand-500 bg-brand-50 dark:bg-brand-950/40 text-brand-700 dark:text-brand-300'
                : 'border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:border-brand-300 dark:hover:border-brand-700'
            }`}
          >
            <span className="w-9 h-9 rounded-full bg-brand-100 dark:bg-brand-950/60 text-brand-600 dark:text-brand-300 flex items-center justify-center flex-shrink-0">
              <UserCheck className="w-4 h-4" />
            </span>
            {t('assignModal.toMyself')}
          </button>
        )}

        <div className="space-y-2 max-h-64 overflow-y-auto pr-1 scrollbar-thin">
          {staff.map((person) => (
            <button
              key={person.id}
              type="button"
              onClick={() => setSelectedId(person.id)}
              aria-pressed={selectedId === person.id}
              className={`w-full flex items-center gap-3 p-3 rounded-2xl border text-left transition-colors ${
                selectedId === person.id
                  ? 'border-brand-500 bg-brand-50 dark:bg-brand-950/40'
                  : 'border-slate-200 dark:border-slate-700 hover:border-brand-300 dark:hover:border-brand-700'
              }`}
            >
              <img
                src={person.image || initialsAvatar(person.name, 128)}
                alt={person.name}
                loading="lazy"
                className="w-9 h-9 rounded-full object-cover flex-shrink-0"
              />
              <span className="min-w-0">
                <span className="block text-xs font-bold text-slate-800 dark:text-slate-100 truncate">{person.name}</span>
                <span className="block text-[11px] font-semibold text-slate-400 truncate">@{person.username}</span>
              </span>
            </button>
          ))}
          {staff.length === 0 && (
            <p className="p-4 text-center text-xs font-semibold text-slate-400">{t('assignModal.noStaff')}</p>
          )}
        </div>

        <label className="block space-y-1.5">
          <span className="text-xs font-bold text-slate-500 dark:text-slate-400">{t('assignModal.reasonLabel')}</span>
          <textarea
            rows={2}
            maxLength={500}
            value={reason}
            onChange={(e) => setReason(e.target.value)}
            placeholder={t('assignModal.reasonPlaceholder')}
            className="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-900/60 text-slate-900 dark:text-slate-100 text-sm font-semibold outline-none focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500"
          />
        </label>

        <div className="flex justify-end gap-2">
          <button
            type="button"
            onClick={onClose}
            disabled={isSaving}
            className="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-600 dark:text-slate-300"
          >
            {t('common.cancel')}
          </button>
          <button
            type="button"
            onClick={submit}
            disabled={isSaving || !selectedId}
            className="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-white text-xs font-bold disabled:opacity-50"
          >
            {isSaving ? <Loader2 className="w-4 h-4 animate-spin" /> : <UserCheck className="w-4 h-4" />}
            {t('assignModal.submit')}
          </button>
        </div>
      </div>
    </Modal>
  );
};
