export type TaskStatus = 'todo' | 'in_progress' | 'done' | 'rejected';
export type TaskPriority = 'low' | 'medium' | 'high';
export type TargetDepartment = 'hardware' | 'software';

export interface Task {
  id: number;
  ticketNumber: string;
  todo: string;
  completed: boolean;
  userId: number;
  status: TaskStatus;
  priority: TaskPriority;
  targetDepartment: TargetDepartment;
  originDepartment: string;
  category: string;
  floor?: string;
  initiatorName?: string;
  initiatorPhone?: string;
  deviceName?: string;
  brokenUrl?: string;
  screenshotUrl?: string;
  rejectionReason?: string;
  solutionComment?: string;
  clientRating?: number;
  isAssigned: boolean;
  assignedTo?: string;
  createdAt: string;
}

export interface TaskFilterParams {
  search?: string;
  status?: TaskStatus | 'all';
  priority?: TaskPriority | 'all';
  targetDepartment?: TargetDepartment | 'all';
  scope?: 'all' | 'my_submitted' | 'my_tasks';
  limit?: number;
  skip?: number;
}

export interface CreateTaskDTO {
  todo: string;
  category?: string;
  targetDepartment?: TargetDepartment;
  teamId?: number;
  originDepartment?: string;
  floor?: string;
  initiatorName?: string;
  initiatorPhone?: string;
  deviceName?: string;
  brokenUrl?: string;
  completed?: boolean;
  status?: TaskStatus;
  priority?: TaskPriority;
}

export interface UpdateTaskDTO {
  todo?: string;
  completed?: boolean;
  status?: TaskStatus;
  priority?: TaskPriority;
  assignToMe?: boolean;
  rejectionReason?: string;
  solutionComment?: string;
  clientRating?: number;
}

export interface TasksPaginatedResponse {
  tasks: Task[];
  total: number;
  skip: number;
  limit: number;
}
