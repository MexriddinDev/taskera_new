import { Task, UpdateTaskDTO } from '../domain/entities/Task';
import { ITaskRepository } from '../domain/repositories/ITaskRepository';
import { AppError } from '@/shared/domain/errors/AppError';

export class UpdateTaskUseCase {
  constructor(private readonly taskRepository: ITaskRepository) {}

  async execute(id: number, dto: UpdateTaskDTO): Promise<Task> {
    if (!id || id <= 0) {
      throw AppError.badRequest('Invalid Task ID');
    }
    if (dto.todo !== undefined && (!dto.todo || !dto.todo.trim())) {
      throw AppError.badRequest('Task title cannot be empty');
    }

    return await this.taskRepository.updateTask(id, dto);
  }
}
