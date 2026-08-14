import React from 'react';
import { Modal } from '@/shared/presentation/components/Modal';
import { Button } from '@/shared/presentation/components/Button';
import { AlertTriangle } from 'lucide-react';
import { useT } from '@/shared/presentation/i18n/i18n';

interface TaskDeleteDialogProps {
  isOpen: boolean;
  onClose: () => void;
  onConfirm: () => void;
  isLoading?: boolean;
}

export const TaskDeleteDialog: React.FC<TaskDeleteDialogProps> = ({
  isOpen,
  onClose,
  onConfirm,
  isLoading = false,
}) => {
  const t = useT();
  return (
    <Modal isOpen={isOpen} onClose={onClose} title={t('taskDelete.title')}>
      <div className="flex items-start space-x-4">
        <div className="p-3 bg-red-100 dark:bg-red-950/50 text-red-600 dark:text-red-400 rounded-full flex-shrink-0">
          <AlertTriangle className="w-6 h-6" />
        </div>
        <div>
          <p className="text-sm text-gray-700 dark:text-gray-300">
            {t('taskDelete.message')}
          </p>
        </div>
      </div>

      <div className="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-100 dark:border-gray-700">
        <Button variant="secondary" onClick={onClose} disabled={isLoading}>
          {t('common.cancel')}
        </Button>
        <Button variant="danger" onClick={onConfirm} isLoading={isLoading}>
          {t('taskDelete.confirm')}
        </Button>
      </div>
    </Modal>
  );
};
