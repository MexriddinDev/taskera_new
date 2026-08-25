import { ITaskRepository } from '../domain/repositories/ITaskRepository';
import { AppError } from '@/shared/domain/errors/AppError';

export class DeleteTaskUseCase {
  constructor(private readonly taskRepository: ITaskRepository) {}

  async execute(id: number): Promise<void> {
    if (!id || id <= 0) {
      throw AppError.badRequest('Invalid Task ID');
    }
    await this.taskRepository.deleteTask(id);
  }
}
