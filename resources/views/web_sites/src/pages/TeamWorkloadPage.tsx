import React, { useState, useEffect } from 'react';
import { useTasks } from '@/modules/tasks/infrastructure/presentation/hooks/useTasks';
import { useUpdateTask } from '@/modules/tasks/infrastructure/presentation/hooks/useUpdateTask';
import { KanbanBoard } from '@/modules/tasks/infrastructure/presentation/components/KanbanBoard';
import { TaskSkeleton } from '@/modules/tasks/infrastructure/presentation/components/TaskSkeleton';
import { EmptyState } from '@/shared/presentation/components/EmptyState';
import { Task } from '@/modules/tasks/domain/entities/Task';
import { useCan } from '@/shared/presentation/hooks/useCan';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { Users, UserCheck, Repeat, RefreshCw, Filter } from 'lucide-react';

interface EmployeeAvatar {
  userId: number;
  name: string;
  username: string;
  activeCount: number;
  avatarUrl: string;
}

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
  const { user } = useCan();
  const isSuperAdmin = user?.role === 'Super Admin' || user?.username === 'admin' || user?.username === 'superadmin';

  const [selectedUserId, setSelectedUserId] = useState<number | null>(null);
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

  const handleToggleStatus = (task: Task) => {
    if (task.status === 'done') return;

    if (task.status === 'todo') {
      updateTaskMutation.mutate({
        id: task.id,
        dto: { status: 'in_progress' },
      });
    } else {
      updateTaskMutation.mutate({
        id: task.id,
        dto: { status: 'done', completed: true, solutionComment: 'Vazifa to\'liq bajarildi.' },
      });
    }
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
      {/* Top Section: Enlarged Employee Avatars Row with Active Badges (Header Removed) */}
      <div className="bg-white dark:bg-slate-800/90 rounded-3xl p-6 border border-slate-200 dark:border-slate-700 shadow-md space-y-4">
        <div className="flex items-center justify-between">
          <span className="text-xs font-black uppercase tracking-wider text-slate-400 flex items-center space-x-2">
            <UserCheck className="w-4 h-4 text-brand-500" />
            <span>Xodimlardan birini tanlang (Kanban Taxtasi Filtrlash)</span>
          </span>
          <div className="flex items-center space-x-3">
            {selectedUserId !== null && (
              <button
                onClick={() => setSelectedUserId(null)}
                className="text-xs font-bold text-brand-500 hover:underline flex items-center space-x-1"
              >
                <Filter className="w-3.5 h-3.5" />
                <span>Filtrni yechish ({selectedEmployeeName})</span>
              </button>
            )}
            <button
              onClick={() => { refetch(); fetchMonitoringData(); }}
              className="flex items-center space-x-1.5 px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-200 transition-all shadow-xs"
            >
              <RefreshCw className={`w-3.5 h-3.5 ${isStatsLoading ? 'animate-spin' : ''}`} />
              <span>Yangilash</span>
            </button>
          </div>
        </div>

        <div className="flex items-center space-x-6 overflow-x-auto pb-3 scrollbar-thin pt-2">
          {/* All Chip */}
          <button
            onClick={() => setSelectedUserId(null)}
            className={`flex flex-col items-center space-y-2 group min-w-[90px] ml-2 transition-transform ${
              selectedUserId === null ? 'scale-105' : 'opacity-70 hover:opacity-100'
            }`}
          >
            <div className={`w-24 h-24 rounded-full flex items-center justify-center border-3 transition-all ${
              selectedUserId === null
                ? 'bg-brand-500 text-white border-brand-500 shadow-xl shadow-brand-500/25 ring-4 ring-brand-500/20'
                : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 border-slate-300 dark:border-slate-600'
            }`}>
              <Users className="w-10 h-10" />
            </div>
            <span className="text-sm font-extrabold text-slate-800 dark:text-slate-200">Barchasi</span>
          </button>

          {/* Employee Avatar Badged Cards */}
          {employeeAvatars.map((emp) => {
            const isSelected = selectedUserId === emp.userId;
            return (
              <button
                key={emp.userId}
                onClick={() => setSelectedUserId(isSelected ? null : emp.userId)}
                className={`flex flex-col items-center space-y-2 relative group min-w-[90px] transition-transform ${
                  isSelected ? 'scale-105' : 'hover:scale-105 opacity-85 hover:opacity-100'
                }`}
                title={`${emp.name} (${emp.activeCount} ta faol zayavka)`}
              >
                <div className="relative">
                  <img
                    src={emp.avatarUrl}
                    alt={emp.name}
                    className={`w-24 h-24 rounded-full object-cover border-3 transition-all shadow-md ${
                      isSelected
                        ? 'border-brand-500 ring-4 ring-brand-500/25 shadow-xl shadow-brand-500/25'
                        : 'border-slate-200 dark:border-slate-700 hover:border-brand-400'
                    }`}
                  />
                  {/* Badge count at top-right of avatar */}
                  {emp.activeCount > 0 && (
                    <span className="absolute -top-1 -right-1 w-7 h-7 rounded-full bg-amber-500 text-white font-black text-sm flex items-center justify-center shadow-lg border-2 border-white dark:border-slate-800">
                      {emp.activeCount}
                    </span>
                  )}
                </div>
                <span className={`text-xs font-black truncate max-w-[96px] ${
                  isSelected ? 'text-brand-500' : 'text-slate-800 dark:text-slate-200'
                }`}>
                  {emp.name.split(' ')[0]}
                </span>
              </button>
            );
          })}
        </div>
      </div>

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
          title={selectedUserId !== null ? `${selectedEmployeeName}da zayavkalar topilmadi` : "Hali zayavkalar yo'q"}
          description="Ushbu mezon bo'yicha hech qanday zayavka mavjud emas."
          actionLabel="Barchasini ko'rish"
          onAction={() => setSelectedUserId(null)}
        />
      )}

      {/* Reassignment Audit Log Table for Superadmin */}
      {isSuperAdmin && reassignments.length > 0 && (
        <div className="bg-white dark:bg-slate-800/90 rounded-3xl p-6 border border-slate-200 dark:border-slate-700 shadow-md space-y-4 mt-8">
          <div className="flex items-center justify-between border-b border-slate-100 dark:border-slate-700 pb-4">
            <div className="flex items-center space-x-2">
              <Repeat className="w-5 h-5 text-amber-500" />
              <h3 className="text-base font-black text-slate-900 dark:text-slate-100">
                O'zlashtirishlar Auditi (Kim kimning zayafkasini olgan)
              </h3>
            </div>
            <span className="text-xs font-bold text-slate-400">Superadmin Logi</span>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead>
                <tr className="border-b border-slate-200 dark:border-slate-700 text-slate-400 font-bold uppercase tracking-wider">
                  <th className="pb-3 px-3">Zayavka #</th>
                  <th className="pb-3 px-3">Mavzu</th>
                  <th className="pb-3 px-3 text-center">Kimdan olindi</th>
                  <th className="pb-3 px-3 text-center">Kim o'zlashtirdi</th>
                  <th className="pb-3 px-3 text-right">Sana / Vaqt</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 dark:divide-slate-700/60 font-medium text-slate-700 dark:text-slate-200">
                {reassignments.map((log) => (
                  <tr key={log.id} className="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors">
                    <td className="py-3 px-3 font-extrabold text-brand-600 dark:text-brand-400">{log.ticket_no}</td>
                    <td className="py-3 px-3 font-bold truncate max-w-xs">{log.subject}</td>
                    <td className="py-3 px-3 text-center">
                      <span className="px-2 py-0.5 rounded-full bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300 font-extrabold">
                        {log.from_username || 'Biriktirilmagan'}
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
    </div>
  );
};
