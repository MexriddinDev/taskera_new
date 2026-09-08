import React, { useState, useEffect } from 'react';
import { useSearchParams } from 'react-router-dom';
import { useTasks } from '@/modules/tasks/infrastructure/presentation/hooks/useTasks';
import { useUpdateTask } from '@/modules/tasks/infrastructure/presentation/hooks/useUpdateTask';
import { KanbanBoard } from '@/modules/tasks/infrastructure/presentation/components/KanbanBoard';
import { TaskSkeleton } from '@/modules/tasks/infrastructure/presentation/components/TaskSkeleton';
import { EmptyState } from '@/shared/presentation/components/EmptyState';
import { Task } from '@/modules/tasks/domain/entities/Task';
import { useCan } from '@/shared/presentation/hooks/useCan';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { Repeat } from 'lucide-react';
import { useT } from '@/shared/presentation/i18n/i18n';
import { SolveTaskModal } from '@/modules/tasks/infrastructure/presentation/components/SolveTaskModal';
import {
  StaffFilterStrip,
  type EmployeeAvatar,
} from '@/modules/tasks/infrastructure/presentation/components/StaffFilterStrip';

interface ReassignmentLog {
  id: number;
  ticket_id: number;
  ticket_no: string;
  subject: string;
  from_username: string;
  to_username: string;
  created_at: string;
  reason?: string;
}

export const TeamWorkloadPage: React.FC = () => {
  const t = useT();
  const { user } = useCan();
  const isSuperAdmin = user?.role === 'Super Admin' || user?.username === 'admin' || user?.username === 'superadmin';

  // Xodim URL orqali ham tanlanadi (`/team-workload?user=12`) — monitoringdagi
  // "Top xodimlar" ro'yxatidan shu manzilga o'tiladi va havolani ulashish mumkin.
  const [searchParams, setSearchParams] = useSearchParams();
  const userParam = Number(searchParams.get('user'));
  const [selectedUserId, setSelectedUserId] = useState<number | null>(userParam > 0 ? userParam : null);

  // Brauzerning "orqaga" tugmasi va tashqi havolalar bilan holat mos yuradi.
  useEffect(() => {
    setSelectedUserId(userParam > 0 ? userParam : null);
  }, [userParam]);

  const selectEmployee = (id: number | null) => {
    setSelectedUserId(id);
    setSearchParams(id ? { user: String(id) } : {}, { replace: true });
  };
  const [employeeAvatars, setEmployeeAvatars] = useState<EmployeeAvatar[]>([]);
  const [reassignments, setReassignments] = useState<ReassignmentLog[]>([]);
  const [isStatsLoading, setIsStatsLoading] = useState(false);

  const { data, isLoading, refetch } = useTasks({
    scope: 'all',
    limit: 100,
  });

  const updateTaskMutation = useUpdateTask();

  const fetchMonitoringData = () => {
    setIsStatsLoading(true);
    axiosClient.get<{ employeeAvatars: EmployeeAvatar[]; reassignments: ReassignmentLog[] }>('/tickets/monitoring')
      .then((res: { data?: { employeeAvatars: EmployeeAvatar[]; reassignments: ReassignmentLog[] } }) => {
        if (res.data?.employeeAvatars) {
          setEmployeeAvatars(res.data.employeeAvatars);
        }
        if (res.data?.reassignments) {
          setReassignments(res.data.reassignments);
        }
      })
      .catch(() => {})
      .finally(() => setIsStatsLoading(false));
  };

  useEffect(() => {
    fetchMonitoringData();
  }, []);

  // Zayavkani yopish uchun yechim izohi majburiy — oyna orqali so'raladi.
  const [solvingTask, setSolvingTask] = useState<Task | null>(null);

  const handleToggleStatus = (task: Task) => {
    if (task.status === 'done') return;

    if (task.status === 'todo' || task.status === 'rejected') {
      updateTaskMutation.mutate({
        id: task.id,
        dto: { status: 'in_progress' },
      });

      return;
    }

    // Ilgari bu yerda qat'iy yozilgan yechim matni qo'yilardi.
    setSolvingTask(task);
  };

  const handleAcceptTask = (taskId: number) => {
    updateTaskMutation.mutate(
      { id: taskId, dto: { assignToMe: true } },
      {
        onSuccess: () => {
          refetch();
          fetchMonitoringData();
        },
      }
    );
  };

  const allTasks = data?.tasks || [];

  // Filter tasks by selected employee if clicked
  const filteredTasks = selectedUserId !== null
    ? allTasks.filter((t) => t.assignedUserId === selectedUserId)
    : allTasks;

  const selectedEmployeeName = employeeAvatars.find((e) => e.userId === selectedUserId)?.name;

  return (
    <div className="w-full px-4 sm:px-8 lg:px-12 py-6 space-y-6">
      {/* Top Section: Enlarged Employee Avatars Row with Active Badges */}
      <StaffFilterStrip
        employees={employeeAvatars}
        selectedUserId={selectedUserId}
        onSelect={selectEmployee}
        onRefresh={() => { refetch(); fetchMonitoringData(); }}
        isRefreshing={isStatsLoading}
      />

      {/* Loading Skeleton */}
      {isLoading && <TaskSkeleton />}

      {/* Main Kanban Board */}
      {!isLoading && filteredTasks.length > 0 && (
        <div className="space-y-4">
          <KanbanBoard
            tasks={filteredTasks}
            onEdit={() => {}}
            onDelete={() => {}}
            onToggleStatus={handleToggleStatus}
            onAccept={handleAcceptTask}
          />
        </div>
      )}

      {/* Empty State */}
      {!isLoading && filteredTasks.length === 0 && (
        <EmptyState
          title={selectedUserId !== null ? t('teamWorkload.noTicketsForEmployee', { name: selectedEmployeeName ?? '' }) : t('teamWorkload.noTickets')}
          description={t('teamWorkload.noTicketsDesc')}
          actionLabel={t('teamWorkload.viewAll')}
          onAction={() => selectEmployee(null)}
        />
      )}

      {/* Reassignment Audit Log Table for Superadmin */}
      {isSuperAdmin && reassignments.length > 0 && (
        <div className="bg-white dark:bg-slate-800/90 rounded-3xl p-6 border border-slate-200 dark:border-slate-700 shadow-md space-y-4 mt-8">
          <div className="flex items-center justify-between border-b border-slate-100 dark:border-slate-700 pb-4">
            <div className="flex items-center space-x-2">
              <Repeat className="w-5 h-5 text-amber-500" />
              <h3 className="text-base font-black text-slate-900 dark:text-slate-100">
                {t('teamWorkload.auditTitle')}
              </h3>
            </div>
            <span className="text-xs font-bold text-slate-400">{t('teamWorkload.superadminLog')}</span>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead>
                <tr className="border-b border-slate-200 dark:border-slate-700 text-slate-400 font-bold uppercase tracking-wider">
                  <th className="pb-3 px-3">{t('teamWorkload.colTicket')}</th>
                  <th className="pb-3 px-3">{t('teamWorkload.colIssue')}</th>
                  <th className="pb-3 px-3 text-center">{t('teamWorkload.colFrom')}</th>
                  <th className="pb-3 px-3 text-center">{t('teamWorkload.colTook')}</th>
                  <th className="pb-3 px-3 text-right">{t('teamWorkload.colDate')}</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-700/60 font-medium text-slate-700 dark:text-slate-200">
                {reassignments.map((log) => (
                  <tr key={log.id} className="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                    <td className="py-3 px-3 font-extrabold text-brand-600 dark:text-brand-400">{log.ticket_no}</td>
                    <td className="py-3 px-3 font-bold truncate max-w-xs">{log.subject}</td>
                    <td className="py-3 px-3 text-center">
                      <span className="px-2 py-0.5 rounded-full bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300 font-extrabold">
                        {log.from_username || t('rateTask.unassigned')}
                      </span>
                    </td>
                    <td className="py-3 px-3 text-center">
                      <span className="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 font-extrabold">
                        {log.to_username}
                      </span>
                    </td>
                    <td className="py-3 px-3 text-right text-slate-400 font-mono">
                      {log.created_at ? new Date(log.created_at).toLocaleString('uz-UZ') : '—'}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Yakunlash — yechim izohi majburiy */}
      <SolveTaskModal
        task={solvingTask}
        isOpen={solvingTask !== null}
        onClose={() => setSolvingTask(null)}
        onSuccess={() => {
          setSolvingTask(null);
          refetch();
          fetchMonitoringData();
        }}
      />
    </div>
  );
};
