import { Task, TaskFilterParams, CreateTaskDTO, UpdateTaskDTO, TasksPaginatedResponse } from '../entities/Task';

export interface ITaskRepository {
  getTasks(params: TaskFilterParams): Promise<TasksPaginatedResponse>;
  getTaskById(id: number): Promise<Task>;
  createTask(dto: CreateTaskDTO | FormData): Promise<Task>;
  updateTask(id: number, dto: UpdateTaskDTO): Promise<Task>;
  deleteTask(id: number): Promise<void>;
}
