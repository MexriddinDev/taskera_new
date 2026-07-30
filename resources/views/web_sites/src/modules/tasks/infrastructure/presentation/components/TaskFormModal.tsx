import React, { useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Modal } from '@/shared/presentation/components/Modal';
import { Input } from '@/shared/presentation/components/Input';
import { Button } from '@/shared/presentation/components/Button';
import { Task } from '../../../domain/entities/Task';

const taskSchema = z.object({
  todo: z.string().min(3, 'Title must be at least 3 characters long'),
  status: z.enum(['todo', 'in_progress', 'done', 'rejected']),
  priority: z.enum(['low', 'medium', 'high']),
});

type TaskFormData = z.infer<typeof taskSchema>;

interface TaskFormModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (data: TaskFormData) => void;
  taskToEdit?: Task | null;
  isLoading?: boolean;
}

export const TaskFormModal: React.FC<TaskFormModalProps> = ({
  isOpen,
  onClose,
  onSubmit,
  taskToEdit,
  isLoading = false,
}) => {
  const {
    register,
    handleSubmit,
    reset,
    formState: { errors },
  } = useForm<TaskFormData>({
    resolver: zodResolver(taskSchema),
    defaultValues: {
      todo: '',
      status: 'todo',
      priority: 'medium',
    },
  });

  useEffect(() => {
    if (taskToEdit) {
      reset({
        todo: taskToEdit.todo,
        status: taskToEdit.status,
        priority: taskToEdit.priority,
      });
    } else {
      reset({
        todo: '',
        status: 'todo',
        priority: 'medium',
      });
    }
  }, [taskToEdit, reset, isOpen]);

  const handleFormSubmit = (data: TaskFormData) => {
    onSubmit(data);
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      title={taskToEdit ? 'Edit Task' : 'Create New Task'}
    >
      <form onSubmit={handleSubmit(handleFormSubmit)} className="space-y-4">
        <Input
          label="Task Title / Description"
          placeholder="e.g. Implement OAuth logic for user login"
          error={errors.todo?.message}
          {...register('todo')}
        />

        <div className="grid grid-cols-2 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Status
            </label>
            <select
              {...register('status')}
              className="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm py-2.5 px-3 focus:outline-none focus:ring-2 focus:ring-brand-500 text-gray-900 dark:text-gray-100"
            >
              <option value="todo">To Do</option>
              <option value="in_progress">In Progress</option>
              <option value="done">Done</option>
            </select>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
              Priority
            </label>
            <select
              {...register('priority')}
              className="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-sm py-2.5 px-3 focus:outline-none focus:ring-2 focus:ring-brand-500 text-gray-900 dark:text-gray-100"
            >
              <option value="low">Low</option>
              <option value="medium">Medium</option>
              <option value="high">High</option>
            </select>
          </div>
        </div>

        <div className="flex justify-end gap-3 pt-4 border-t border-gray-100 dark:border-gray-700">
          <Button type="button" variant="secondary" onClick={onClose}>
            Cancel
          </Button>
          <Button type="submit" isLoading={isLoading}>
            {taskToEdit ? 'Save Changes' : 'Create Task'}
          </Button>
        </div>
      </form>
    </Modal>
  );
};
