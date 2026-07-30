import { Task } from '../domain/entities/Task';
import { ITaskRepository } from '../domain/repositories/ITaskRepository';
import { AppError } from '@/shared/domain/errors/AppError';

export class GetTaskByIdUseCase {
  constructor(private readonly taskRepository: ITaskRepository) {}

  async execute(id: number): Promise<Task> {
    if (!id || id <= 0) {
      throw AppError.badRequest('Invalid Task ID');
    }
    return await this.taskRepository.getTaskById(id);
  }
}
