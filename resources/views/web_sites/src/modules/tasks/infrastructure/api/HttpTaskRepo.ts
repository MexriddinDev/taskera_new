import { ITaskRepository } from '../../domain/repositories/ITaskRepository';
import {
  Task,
  TaskFilterParams,
  CreateTaskDTO,
  UpdateTaskDTO,
  TasksPaginatedResponse,
} from '../../domain/entities/Task';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';

interface BackendTaskResponse {
  tasks?: Task[];
  total: number;
  skip: number;
  limit: number;
}

export class HttpTaskRepo implements ITaskRepository {
  async getTasks(params: TaskFilterParams = {}): Promise<TasksPaginatedResponse> {
    const queryParams = new URLSearchParams();

    if (params.limit) queryParams.set('limit', String(params.limit));
    if (params.skip) queryParams.set('skip', String(params.skip));
    if (params.search) queryParams.set('search', params.search);
    if (params.status && params.status !== 'all') queryParams.set('status', params.status);
    if (params.priority && params.priority !== 'all') queryParams.set('priority', params.priority);
    if (params.targetDepartment && params.targetDepartment !== 'all') {
      queryParams.set('targetDepartment', params.targetDepartment);
    }
    if (params.scope) queryParams.set('scope', params.scope);
    if (params.startDate) queryParams.set('startDate', params.startDate);
    if (params.endDate) queryParams.set('endDate', params.endDate);
    if (params.dateField) queryParams.set('dateField', params.dateField);

    const url = `/tickets?${queryParams.toString()}`;
    const response = await axiosClient.get<BackendTaskResponse>(url);

    return {
      tasks: response.data.tasks || [],
      total: response.data.total,
      skip: response.data.skip,
      limit: response.data.limit,
    };
  }

  async getTaskById(id: number): Promise<Task> {
    const response = await axiosClient.get<any>(`/tickets/${id}`);
    const data = response.data?.data ?? response.data;
    return data;
  }

  async createTask(dto: CreateTaskDTO | FormData): Promise<Task> {
    const response = await axiosClient.post<Task>('/tickets', dto);
    return response.data;
  }

  async updateTask(id: number, dto: UpdateTaskDTO): Promise<Task> {
    const response = await axiosClient.put<Task>(`/tickets/${id}`, dto);
    return response.data;
  }

  async deleteTask(id: number): Promise<void> {
    await axiosClient.delete(`/tickets/${id}`);
  }
}

export const httpTaskRepo = new HttpTaskRepo();
