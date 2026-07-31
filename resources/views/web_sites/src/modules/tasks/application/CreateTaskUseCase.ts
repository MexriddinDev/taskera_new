import { CreateTaskDTO, Task } from '../domain/entities/Task';
import { ITaskRepository } from '../domain/repositories/ITaskRepository';
import { AppError } from '@/shared/domain/errors/AppError';

export class CreateTaskUseCase {
  constructor(private readonly taskRepository: ITaskRepository) {}

  async execute(dto: CreateTaskDTO | FormData): Promise<Task> {
    if (dto instanceof FormData) {
      return await this.taskRepository.createTask(dto);
    }
    if (!dto.todo || !dto.todo.trim()) {
      throw AppError.badRequest('Task title cannot be empty');
    }
    if (dto.todo.trim().length < 3) {
      throw AppError.badRequest('Task title must be at least 3 characters');
    }

    return await this.taskRepository.createTask({
      ...dto,
      todo: dto.todo.trim(),
    });
  }
}
