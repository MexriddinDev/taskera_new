import React, { useState, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTasks } from '@/modules/tasks/infrastructure/presentation/hooks/useTasks';
import { CreateTaskModal } from '@/modules/tasks/infrastructure/presentation/components/CreateTaskModal';
import { RateTaskModal } from '@/modules/tasks/infrastructure/presentation/components/RateTaskModal';
import { RejectTaskModal } from '@/modules/tasks/infrastructure/presentation/components/RejectTaskModal';
import { KanbanBoard } from '@/modules/tasks/infrastructure/presentation/components/KanbanBoard';
import { Task, TaskStatus } from '@/modules/tasks/domain/entities/Task';
import { Plus, Clock, CheckCircle2, AlertTriangle, Star, RotateCcw, ClipboardList, Image, Video, Mic, Eye, MessageSquare, LayoutGrid, List } from 'lucide-react';

export const MyRequestsPage: React.FC = () => {
  const [isCreateOpen, setIsCreateOpen] = useState(false);
  const [selectedTaskForRate, setSelectedTaskForRate] = useState<Task | null>(null);
  const [selectedTaskForReject, setSelectedTaskForReject] = useState<Task | null>(null);
  const [selectedStatusFilter, setSelectedStatusFilter] = useState<number>(0);
  const [viewMode, setViewMode] = useState<'kanban' | 'list'>('kanban');
  const rateQueueRef = useRef<Task[]>([]);
  const rateFlowRef = useRef(false);
  const navigate = useNavigate();

  const filterTabs = ['Barchasi', 'Ochiq (Yangi)', 'Jarayonda', 'Bajarildi', 'Yopildi', 'Reject'];
  const statusMapping: (TaskStatus | 'all')[] = ['all', 'todo', 'in_progress', 'done', 'done', 'rejected'];

  const currentStatusFilter = viewMode === 'kanban' ? 'all' : statusMapping[selectedStatusFilter];

  const { data: tasksData, isLoading: isTasksLoading, refetch } = useTasks({
    scope: 'my_submitted',
    status: currentStatusFilter as TaskStatus | 'all',
    limit: 50,
  });

  const submittedTasks = tasksData?.tasks || [];

  // "Zayavka Yaratish" bosilganda: avval baholanmagan eski zayavkalarni baholash
  const handleCreateClick = () => {
    const unrated = submittedTasks.filter((t) => t.status === 'done' && !t.clientRating);
    if (unrated.length > 0) {
      rateFlowRef.current = true;
      rateQueueRef.current = unrated;
      setSelectedTaskForRate(unrated[0]);
    } else {
      setIsCreateOpen(true);
    }
  };

  const handleRateSuccess = () => {
    const next = rateQueueRef.current.slice(1);
    rateQueueRef.current = next;
    if (next.length > 0) {
      setSelectedTaskForRate(next[0]);
    } else {
      setSelectedTaskForRate(null);
      refetch();
      if (rateFlowRef.current) {
        rateFlowRef.current = false;
        setIsCreateOpen(true);
      }
    }
  };

  const handleRateClose = () => {
    rateQueueRef.current = [];
    rateFlowRef.current = false;
    setSelectedTaskForRate(null);
  };

  const getStatusBadge = (status: TaskStatus, clientRating?: number) => {
    if (status === 'done' && clientRating) {
      return { bg: 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300', label: 'Yopildi (Baholangan)' };
    }
    switch (status) {
      case 'done':
        return { bg: 'bg-success-50 text-success-600 dark:bg-success-700/20 border border-success-500/20', label: 'Bajarildi' };
      case 'in_progress':
        return { bg: 'bg-warning-50 text-warning-600 dark:bg-warning-700/20 border border-warning-500/20', label: 'Jarayonda' };
      case 'rejected':
        return { bg: 'bg-error-50 text-error-600 dark:bg-error-700/20 border border-error-500/20', label: 'Reject bo\'lgan' };
      default:
        return { bg: 'bg-brand-50 text-brand-600 dark:bg-brand-950/40 border border-brand-500/20', label: 'Yangi / Ochiq' };
    }
  };

  return (
    <div className="w-full px-4 sm:px-8 lg:px-12 py-8 space-y-6">
      {/* Header & Create Button */}
      <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-extrabold text-slate-900 dark:text-slate-100 flex items-center space-x-3">
            <ClipboardList className="w-8 h-8 text-brand-500" />
            <span>Zayavkalarim</span>
          </h1>
        </div>

        <div className="flex items-center gap-3">
          <div className="flex items-center p-1 rounded-2xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
            <button
              onClick={() => setViewMode('kanban')}
              className={`flex items-center space-x-1.5 px-3.5 py-2 rounded-xl text-xs font-extrabold transition-all cursor-pointer ${
                viewMode === 'kanban'
                  ? 'bg-white dark:bg-slate-900 text-brand-500 shadow-sm border border-slate-200 dark:border-slate-700'
                  : 'text-slate-500 dark:text-slate-400 hover:text-slate-700'
              }`}
            >
              <LayoutGrid className="w-4 h-4" />
              <span>Kanban</span>
            </button>
            <button
              onClick={() => setViewMode('list')}
              className={`flex items-center space-x-1.5 px-3.5 py-2 rounded-xl text-xs font-extrabold transition-all cursor-pointer ${
                viewMode === 'list'
                  ? 'bg-white dark:bg-slate-900 text-brand-500 shadow-sm border border-slate-200 dark:border-slate-700'
                  : 'text-slate-500 dark:text-slate-400 hover:text-slate-700'
              }`}
            >
              <List className="w-4 h-4" />
              <span>Ro'yxat</span>
            </button>
          </div>

          <button
            onClick={handleCreateClick}
            className="inline-flex items-center space-x-2 px-6 py-3 rounded-2xl bg-brand-500 hover:bg-brand-600 active:bg-brand-700 text-white font-extrabold text-sm shadow-md hover:shadow-lg transition-all cursor-pointer"
          >
            <Plus className="w-5 h-5" />
            <span>Zayavka Yaratish</span>
          </button>
        </div>
      </div>

      {/* Filter Tabs (Ro'yxat rejimida) */}
      {viewMode === 'list' && (
        <div className="flex items-center space-x-2 overflow-x-auto pb-2 scrollbar-none">
          {filterTabs.map((tab, idx) => {
            const isSelected = selectedStatusFilter === idx;
            return (
              <button
                key={idx}
                onClick={() => setSelectedStatusFilter(idx)}
                className={`px-4 py-2 rounded-full text-xs font-bold whitespace-nowrap transition-all ${
                  isSelected
                    ? 'bg-brand-500 text-white shadow-sm'
                    : 'bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 hover:bg-slate-50'
                }`}
              >
                {tab}
              </button>
            );
          })}
        </div>
      )}

      {/* Loading State */}
      {isTasksLoading && (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {[1, 2, 3].map((n) => (
            <div key={n} className="h-36 bg-slate-200 dark:bg-slate-800 rounded-2xl animate-pulse" />
          ))}
        </div>
      )}

      {/* Kanban View: yuborilgan zayavkalarning holati ustunlar bo'yicha */}
      {viewMode === 'kanban' && !isTasksLoading && submittedTasks.length > 0 && (
        <KanbanBoard
          tasks={submittedTasks}
          onEdit={() => {}}
          onDelete={() => {}}
          onToggleStatus={() => {}}
          onRate={(t) => setSelectedTaskForRate(t)}
          onReject={(t) => setSelectedTaskForReject(t)}
        />
      )}

      {/* Submitted Tasks List (Ro'yxat rejimida) */}
      {viewMode === 'list' && !isTasksLoading && submittedTasks.length > 0 && (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {submittedTasks.map((task) => {
            const statusInfo = getStatusBadge(task.status, task.clientRating);
            const isResolvedUnconfirmed = task.status === 'done' && !task.clientRating;

            return (
              <div
                key={task.id}
                onClick={() => navigate(`/task/${task.id}`)}
                className={`bg-white dark:bg-slate-800/90 rounded-2xl p-5 border shadow-sm transition-all flex flex-col justify-between space-y-4 cursor-pointer group ${
                  isResolvedUnconfirmed
                    ? 'border-success-500/50 ring-2 ring-success-500/20'
                    : 'border-slate-200 dark:border-slate-700/80 hover:border-brand-300 dark:hover:border-brand-700 hover:shadow-lg'
                }`}
              >
                <div className="space-y-3">
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <span className="text-[11px] font-bold text-slate-400">{task.ticketNumber}</span>
                    <h3 className="font-bold text-base text-slate-900 dark:text-slate-100 group-hover:text-brand-500 dark:group-hover:text-brand-400 transition-colors line-clamp-2">{task.todo}</h3>
                  </div>
                  <div className="flex items-center justify-end gap-1.5 flex-shrink-0">
                    <span className={`px-3 py-1 rounded-full text-xs font-bold whitespace-nowrap ${statusInfo.bg}`}>
                      {statusInfo.label}
                    </span>
                    {(task.unreadCommentCount ?? 0) > 0 && (
                      <span
                        className="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-black bg-rose-500 text-white shadow-sm shadow-rose-500/40"
                        title="O'qilmagan xabarlar bor"
                      >
                        <MessageSquare className="w-3 h-3" />
                        {task.unreadCommentCount}
                      </span>
                    )}
                  </div>
                </div>

                  {/* Media Indicators (Rasm / Video / Ovozli xabar) */}
                  <div className="flex items-center space-x-2.5">
                    {task.screenshotUrl && (
                      <span className="inline-flex items-center space-x-1 text-[11px] font-bold text-emerald-600 dark:text-emerald-400">
                        <Image className="w-3.5 h-3.5" />
                        <span>Rasm</span>
                      </span>
                    )}
                    {task.videoUrl && (
                      <span className="inline-flex items-center space-x-1 text-[11px] font-bold text-rose-500 dark:text-rose-400">
                        <Video className="w-3.5 h-3.5" />
                        <span>Video</span>
                      </span>
                    )}
                    {task.audioUrl && (
                      <span className="inline-flex items-center space-x-1 text-[11px] font-bold text-brand-500 dark:text-brand-400">
                        <Mic className="w-3.5 h-3.5" />
                        <span>Ovozli xabar</span>
                      </span>
                    )}
                    <span className="inline-flex items-center space-x-1 text-[11px] font-bold text-slate-400 group-hover:text-brand-400">
                      <Eye className="w-3.5 h-3.5" />
                      <span>Ko'rish</span>
                    </span>
                  </div>

                  {/* Location / Department */}
                  {task.originDepartment && (
                    <p className="text-xs text-slate-500 dark:text-slate-400 font-medium">
                      Bo'lim: {task.originDepartment}
                    </p>
                  )}

                  {/* Notification Banner when Resolved but not yet rated/confirmed */}
                  {isResolvedUnconfirmed && (
                    <div className="p-3.5 rounded-xl bg-success-50 dark:bg-success-700/20 border border-success-500/30 space-y-3">
                      <div className="flex items-start space-x-2 text-success-600 dark:text-success-400">
                        <CheckCircle2 className="w-5 h-5 flex-shrink-0 mt-0.5" />
                        <div>
                          <p className="text-xs font-extrabold">Zayafkangiz bajarildi!</p>
                          <p className="text-[11px] font-medium opacity-90 mt-0.5">
                            Mas'ul xodim yechim berdi. Bajarilgan ishni baholang yoki narozilaringiz bo'lsa qaytaring.
                          </p>
                        </div>
                      </div>

                      <div className="flex items-center space-x-2 pt-1">
                        <button
                          onClick={(e) => { e.stopPropagation(); setSelectedTaskForRate(task); }}
                          className="flex-1 inline-flex items-center justify-center space-x-1.5 px-3 py-2 rounded-lg bg-success-500 hover:bg-success-600 text-white font-bold text-xs shadow-sm transition-all cursor-pointer"
                        >
                          <Star className="w-3.5 h-3.5 fill-white" />
                          <span>Baholash & Yopish</span>
                        </button>

                        <button
                          onClick={(e) => { e.stopPropagation(); setSelectedTaskForReject(task); }}
                          className="inline-flex items-center justify-center space-x-1.5 px-3 py-2 rounded-lg bg-error-500 hover:bg-error-600 text-white font-bold text-xs shadow-sm transition-all cursor-pointer"
                        >
                          <RotateCcw className="w-3.5 h-3.5" />
                          <span>Reject</span>
                        </button>
                      </div>
                    </div>
                  )}

                  {/* Rejection Reason Display */}
                  {task.status === 'rejected' && task.rejectionReason && (
                    <div className="p-3 rounded-xl bg-error-50 dark:bg-error-700/20 border border-error-500/20 flex items-start space-x-2">
                      <AlertTriangle className="w-4 h-4 text-error-500 flex-shrink-0 mt-0.5" />
                      <div>
                        <p className="text-xs font-bold text-error-500">Rad etish sababi:</p>
                        <p className="text-xs text-error-500/90 font-medium">{task.rejectionReason}</p>
                      </div>
                    </div>
                  )}

                  {/* Rated Display */}
                  {task.clientRating && (
                    <div className="flex items-center space-x-1 text-xs text-amber-500 font-bold">
                      <span>Sizning bahoingiz:</span>
                      <div className="flex items-center space-x-0.5">
                        {[...Array(task.clientRating)].map((_, i) => (
                          <Star key={i} className="w-4 h-4 fill-amber-400 text-amber-400" />
                        ))}
                      </div>
                    </div>
                  )}
                </div>

                <div className="pt-3 border-t border-slate-100 dark:border-slate-700/60 flex items-center justify-between text-xs text-slate-400">
                  <div className="flex items-center space-x-1">
                    <Clock className="w-3.5 h-3.5" />
                    <span>{task.createdAt}</span>
                  </div>
                  {task.assignedTo && (
                    <span className="font-semibold text-slate-500 dark:text-slate-400">
                      Mas'ul: {task.assignedTo}
                    </span>
                  )}
                </div>
              </div>
            );
          })}
        </div>
      )}

      {/* Empty State */}
      {!isTasksLoading && submittedTasks.length === 0 && (
        <div className="p-12 text-center bg-white dark:bg-slate-800 rounded-3xl border border-slate-200 dark:border-slate-700 space-y-3">
          <ClipboardList className="w-12 h-12 text-slate-300 dark:text-slate-600 mx-auto" />
          <h3 className="text-lg font-extrabold text-slate-900 dark:text-slate-100">Sizda yuborilgan zayavkalar yo'q</h3>
          <p className="text-xs text-slate-500 dark:text-slate-400 max-w-sm mx-auto">
            Muammo yoki so'rovlaringiz bo'lsa "Zayavka Yaratish" tugmasini bosib yuborishingiz mumkin.
          </p>
          <button
            onClick={handleCreateClick}
            className="inline-flex items-center space-x-2 px-5 py-2.5 rounded-xl bg-brand-500 text-white font-bold text-xs shadow-md transition-all mt-2 cursor-pointer"
          >
            <Plus className="w-4 h-4" />
            <span>Zayavka Yaratish</span>
          </button>
        </div>
      )}

      {/* Modals */}
      <CreateTaskModal
        isOpen={isCreateOpen}
        onClose={() => setIsCreateOpen(false)}
        onSuccess={() => refetch()}
      />

      <RateTaskModal
        task={selectedTaskForRate}
        isOpen={Boolean(selectedTaskForRate)}
        onClose={handleRateClose}
        onSuccess={handleRateSuccess}
      />

      <RejectTaskModal
        task={selectedTaskForReject}
        isOpen={Boolean(selectedTaskForReject)}
        onClose={() => setSelectedTaskForReject(null)}
        onSuccess={() => refetch()}
      />
    </div>
  );
};
