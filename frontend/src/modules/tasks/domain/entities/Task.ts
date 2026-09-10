export type TaskStatus = 'todo' | 'in_progress' | 'done' | 'rejected';
export type TaskPriority = 'low' | 'medium' | 'high';
export type TargetDepartment = 'hardware' | 'software';

export interface TaskMedia {
  id: number;
  type: 'audio' | 'image' | 'video' | 'file';
  name?: string;
  url: string;
  sizeBytes?: number;
}

export type TaskDeviceKind = 'desktop' | 'mobile' | 'tablet' | 'telegram' | 'unknown';

/** Zayavka YARATILGAN paytdagi qurilma (ko'ruvchiniki emas). */
export interface TaskDevice {
  kind: TaskDeviceKind;
  os?: string | null;
  browser?: string | null;
  label: string;
}

/** Mas'ul xodim o'zgarishi — batafsil sahifada "kimdan kimga qachon" uchun. */
export interface TaskAssignmentChange {
  id: string;
  fromUser?: string | null;
  fromUserAvatar?: string | null;
  toUser?: string | null;
  toUserAvatar?: string | null;
  changedBy?: string | null;
  reason?: string | null;
  /** Oldingi ijrochi shu almashinuvgacha necha daqiqa ishlagani. */
  spentMinutes?: number | null;
  createdAt?: string | null;
  createdAtIso?: string | null;
}

/**
 * Sodda SLA bosqichi. Muddatlar zayavka kategoriyasida sozlanadi
 * (SLA ekrani), backend esa ularni zayavka vaqtlaridan hisoblab beradi.
 */
export interface TaskSlaStage {
  key: 'accept' | 'work';
  minutes: number;
  startedAt: string | null;
  dueAt: string | null;
  finishedAt: string | null;
  status: 'WAITING' | 'RUNNING' | 'MET' | 'BREACHED';
  remainingSeconds: number | null;
  overdueMinutes: number;
  slaId: number;
  slaName: string;
  description?: string | null;
  teamId: number;
  teamName: string;
}

export interface Task {
  id: number;
  ticketNumber: string;
  todo: string;
  description?: string;
  completed: boolean;
  userId: number;
  status: TaskStatus;
  priority: TaskPriority;
  targetDepartment: TargetDepartment;
  originDepartment: string;
  category: string;
  floor?: string;
  initiatorName?: string;
  initiatorAvatar?: string;
  initiatorPhone?: string;
  requesterEmail?: string;
  requesterPosition?: string;
  requesterUsername?: string;
  requesterUserId?: number;
  requesterDepartment?: string;
  deviceName?: string;
  brokenUrl?: string;
  screenshotUrl?: string;
  rejectionReason?: string;
  solutionComment?: string;
  clientRating?: number;
  isAssigned: boolean;
  assignedUserId?: number;
  assignedTo?: string;
  assignedUserAvatar?: string;
  assignmentHistory?: TaskAssignmentChange[];
  ipAddress?: string;
  browser?: string;
  sourceChannel?: string;
  source?: 'web' | 'telegram';
  device?: TaskDevice;
  telegramChatId?: string;
  audioUrl?: string;
  videoUrl?: string;
  media?: TaskMedia[];
  pinfl?: string;
  mfo?: string;
  localCode?: string;
  startedAt?: string;
  resolvedAt?: string;
  spentMinutes?: number;
  createdAt: string;
  comments?: Array<{
    id: number;
    author: string;
    authorUsername?: string;
    authorAvatar?: string;
    body: string;
    createdAt: string;
    isRead?: boolean;
    /**
     * Yechim va rad etish sababi yozishmaga ham tushadi — shu bilan zayavka
     * bir necha marta yopilib qaytarilganda ham butun tarix saqlanadi.
     * Oddiy izohda `null`.
     */
    kind?: 'solution' | 'rejection' | 'rating' | null;
  }>;
  unreadCommentCount?: number;
  startedAtIso?: string | null;
  resolvedAtIso?: string | null;
  sla?: TaskSlaStage[];
}

export interface TaskFilterParams {
  search?: string;
  status?: TaskStatus | 'all';
  priority?: TaskPriority | 'all';
  targetDepartment?: TargetDepartment | 'all';
  scope?: 'all' | 'my_submitted' | 'my_tasks';
  startDate?: string;
  endDate?: string;
  dateField?: 'created_at' | 'resolved_at' | 'closed_at';
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
  /** Bahoga ilova qilinadigan ixtiyoriy izoh — baho bilan bitta yozuvga qo'shiladi. */
  ratingComment?: string;
}

export interface TasksPaginatedResponse {
  tasks: Task[];
  total: number;
  skip: number;
  limit: number;
}
