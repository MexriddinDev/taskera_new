import { TaskFilterParams, TasksPaginatedResponse } from '../domain/entities/Task';
import { ITaskRepository } from '../domain/repositories/ITaskRepository';

export class GetTasksUseCase {
  constructor(private readonly taskRepository: ITaskRepository) {}

  async execute(params: TaskFilterParams = {}): Promise<TasksPaginatedResponse> {
    return await this.taskRepository.getTasks(params);
  }
}
