import React, { useState, useEffect } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { useTaskDetail } from '@/modules/tasks/infrastructure/presentation/hooks/useTaskDetail';
import { useUpdateTask } from '@/modules/tasks/infrastructure/presentation/hooks/useUpdateTask';
import { Button } from '@/shared/presentation/components/Button';
import { Modal } from '@/shared/presentation/components/Modal';
import {
  ArrowLeft,
  User as UserIcon,
  AlertTriangle,
  Copy,
  Laptop,
  Check,
  CheckCircle,
  Star,
  MessageSquare,
  Volume2,
  Send,
  UserCheck,
  Zap,
  Pencil,
  Activity,
  Video,
} from 'lucide-react';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { useAuthStore } from '@/shared/presentation/store/useAuthStore';

export const TaskDetailPage: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const taskId = Number(id);
  const navigate = useNavigate();
  const currentUser = useAuthStore((s) => s.user);

  const [copiedText, setCopiedText] = useState<string | null>(null);

  // Solution / Review states
  const [solutionComment, setSolutionComment] = useState('');

  // Image modal state
  const [isImageModalOpen, setIsImageModalOpen] = useState(false);

  // Message modal state
  const [isMessageModalOpen, setIsMessageModalOpen] = useState(false);
  const [messageText, setMessageText] = useState('');

  // Assign / Reassign modal state
  const [isAssignModalOpen, setIsAssignModalOpen] = useState(false);
  const [staffList, setStaffList] = useState<Array<{ id: number; name: string; username: string; image?: string }>>([]);
  const [selectedAssigneeId, setSelectedAssigneeId] = useState<number | null>(null);
  const [reassignReason, setReassignReason] = useState('');
  const [isAssigning, setIsAssigning] = useState(false);

  const { data: task, isLoading, isError, error, refetch } = useTaskDetail(taskId);
  const updateTaskMutation = useUpdateTask();

  const fetchStaffList = () => {
    axiosClient.get('/tickets/monitoring')
      .then((res) => {
        if (res.data?.employees) {
          setStaffList(res.data.employees.map((e: any) => ({
            id: e.id,
            name: `${e.first_name || ''} ${e.last_name || ''}`.trim() || e.username,
            username: e.username,
            image: e.image,
          })));
        }
      })
      .catch(() => {});
  };

  useEffect(() => {
    fetchStaffList();
    const interval = setInterval(() => {
      refetch();
    }, 5000);
    return () => clearInterval(interval);
  }, [refetch]);

  const copyToClipboard = (text: string, label: string) => {
    navigator.clipboard.writeText(text);
    setCopiedText(label);
    setTimeout(() => setCopiedText(null), 2500);
  };

  // 1. Specialist Actions
  const handleAcceptTask = () => {
    if (!task) return;
    updateTaskMutation.mutate(
      { id: task.id, dto: { status: 'in_progress', assignToMe: true } },
      {
        onSuccess: () => {
          refetch();
        },
      }
    );
  };

  const handleMarkAsCompleted = () => {
    if (!task) return;
    updateTaskMutation.mutate(
      {
        id: task.id,
        dto: {
          status: 'done',
          completed: true,
          solutionComment: solutionComment || 'Vazifa to\'liq bajarildi va muammo hal etildi.',
        },
      },
      {
        onSuccess: () => {
          refetch();
        },
      }
    );
  };

  const handleSendMessage = async () => {
    if (!task || !messageText.trim()) return;
    try {
      await axiosClient.post(`/tickets/${task.id}/comments`, { body: messageText });
      setMessageText('');
      setIsMessageModalOpen(false);
      refetch();
    } catch (e) {
      console.error('Failed to send message', e);
    }
  };

  // 2. Reassign Action
  const handleAssignTask = async (targetUserId?: number) => {
    if (!task) return;
    const assigneeId = targetUserId || selectedAssigneeId || currentUser?.id;
    if (!assigneeId) return;

    setIsAssigning(true);
    try {
      await axiosClient.post(`/tickets/${task.id}/assign`, {
        assignee_user_id: assigneeId,
        reason: reassignReason || 'Zayavka biriktirildi.',
      });
      setIsAssignModalOpen(false);
      setReassignReason('');
      refetch();
    } catch (e) {
      console.error('Failed to assign ticket', e);
    } finally {
      setIsAssigning(false);
    }
  };

  if (isLoading) {
    return (
      <div className="w-full px-4 sm:px-8 lg:px-12 py-12">
        <div className="bg-white dark:bg-slate-900 rounded-3xl p-8 shadow-md border border-slate-200 dark:border-slate-800 animate-pulse space-y-6">
          <div className="h-12 bg-slate-200 dark:bg-slate-800 rounded-2xl w-full" />
          <div className="h-14 bg-slate-200 dark:bg-slate-800 rounded-2xl w-full" />
          <div className="grid grid-cols-3 gap-6">
            <div className="col-span-2 h-80 bg-slate-200 dark:bg-slate-800 rounded-2xl" />
            <div className="col-span-1 h-80 bg-slate-200 dark:bg-slate-800 rounded-2xl" />
          </div>
        </div>
      </div>
    );
  }

  if (isError || !task) {
    return (
      <div className="w-full max-w-xl mx-auto px-4 py-16 text-center">
        <div className="p-4 bg-rose-100 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4 border border-rose-300 dark:border-rose-800">
          <AlertTriangle className="w-8 h-8" />
        </div>
        <h2 className="text-xl font-black text-slate-900 dark:text-slate-100 mb-2">Zayavka Topilmadi</h2>
        <p className="text-sm text-slate-500 dark:text-slate-400 mb-6">
          {error?.message || 'Bunday zayavka mavjud emas yoki o\'chirilgan bo\'lishi mumkin.'}
        </p>
        <Button variant="secondary" onClick={() => navigate('/dashboard')} leftIcon={<ArrowLeft className="w-4 h-4" />}>
          Dashboardga qaytish
        </Button>
      </div>
    );
  }

  // Robust Status Parsing Logic
  const statusStr = (task.status || '').toLowerCase();
  const isSolved = statusStr === 'done' || task.completed;
  const isRejected = statusStr === 'rejected' || statusStr === 'stopped' || statusStr === 'cancelled';
  const isInProgress = statusStr === 'in_progress' || statusStr === 'in progress';
  const isOpenUnassigned = statusStr === 'todo' && !task.isAssigned;

  // Stepper lifecycle items (TODO -> IN PROGRESS -> REJECTED / STOPPED -> DONE)
  const stepperSteps = [
    { key: 'todo', label: '1. TODO' },
    { key: 'in_progress', label: '2. IN PROGRESS' },
    { key: 'stopped', label: '3. REJECTED / STOPPED' },
    { key: 'done', label: '4. DONE' },
  ];

  // Active step index calculation
  const currentStepIndex = isSolved ? 3 : isRejected ? 2 : isInProgress ? 1 : 0;

  // Detect voice message and clean text tags
  const hasVoiceMessage = Boolean(
    task.audioUrl ||
    (task.todo && task.todo.includes('[Ovozli xabar')) ||
    (task.description && task.description.includes('[Ovozli xabar'))
  );

  const cleanTodoText = task.todo ? task.todo.replace(/\[Ovozli xabar biriktirilgan\]/gi, '').trim() : '';
  const cleanDescriptionText = task.description ? task.description.replace(/\[Ovozli xabar biriktirilgan\]/gi, '').trim() : '';

  return (
    <div className="w-full px-4 sm:px-8 lg:px-12 py-6 pb-32 space-y-6 font-sans">
      {/* Navigation & Alert Toast */}
      <div className="flex items-center justify-between">
        <Link
          to="/dashboard"
          className="inline-flex items-center text-xs font-black uppercase tracking-wider text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white transition-colors"
        >
          <ArrowLeft className="w-4 h-4 mr-1.5" />
          Dashboardga qaytish
        </Link>

        {copiedText && (
          <div className="px-3.5 py-1 bg-emerald-100 dark:bg-emerald-950/80 text-emerald-800 dark:text-emerald-300 text-xs font-extrabold rounded-full border border-emerald-300 dark:border-emerald-700 flex items-center space-x-1 shadow-sm">
            <Check className="w-3.5 h-3.5" />
            <span>{copiedText} nusxalandi</span>
          </div>
        )}
      </div>

      {/* 1. SERIOUS ENTERPRISE HEADER BANNER (Light & Dark Theme Compatible) */}
      <div className="bg-white dark:bg-slate-900 text-slate-900 dark:text-white rounded-3xl p-5 sm:p-6 shadow-md flex flex-wrap items-center justify-between gap-4 border border-slate-200 dark:border-slate-800">
        <div className="flex items-center space-x-4">
          <div className="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 text-brand-600 dark:text-brand-400 flex items-center justify-center border border-slate-200 dark:border-slate-700 flex-shrink-0 shadow-xs">
            <UserCheck className="w-6 h-6" />
          </div>
          <div>
            <div className="flex items-center space-x-3">
              <span className="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Holati:</span>
              <span className={`px-3 py-1 rounded-lg text-xs font-black tracking-wider uppercase border ${
                isSolved
                  ? 'bg-emerald-100 dark:bg-emerald-950/80 text-emerald-800 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800'
                  : isRejected
                  ? 'bg-rose-100 dark:bg-rose-950/80 text-rose-800 dark:text-rose-300 border-rose-300 dark:border-rose-800'
                  : isInProgress
                  ? 'bg-amber-100 dark:bg-amber-950/80 text-amber-800 dark:text-amber-300 border-amber-300 dark:border-amber-800'
                  : 'bg-slate-100 dark:bg-slate-700 text-slate-800 dark:text-slate-200 border-slate-300 dark:border-slate-600'
              }`}>
                {isSolved ? 'DONE' : isRejected ? 'REJECTED / STOPPED' : isInProgress ? 'IN PROGRESS' : 'TODO'}
              </span>
            </div>
            <div className="flex flex-wrap items-center gap-x-6 gap-y-1 mt-2 text-xs text-slate-600 dark:text-slate-300">
              <span>Boshlangan sana: <strong className="text-slate-900 dark:text-white font-mono">{task.createdAt}</strong></span>
              <span className="flex items-center space-x-2">
                <span className="text-slate-500 dark:text-slate-400">Mas'ul xodim:</span>
                <strong className="text-emerald-600 dark:text-emerald-400 font-extrabold">{task.assignedTo || 'Biriktirilmagan'}</strong>
                {/* Pencil Edit Icon next to Responsible Employee */}
                <button
                  onClick={() => { setIsAssignModalOpen(true); fetchStaffList(); }}
                  className="p-1.5 rounded-lg bg-slate-100 dark:bg-slate-700 hover:bg-amber-500 text-amber-600 dark:text-amber-300 hover:text-white transition-all cursor-pointer border border-slate-200 dark:border-slate-600 shadow-xs ml-1 flex items-center"
                  title="Xodimga biriktirish / Qayta biriktirish"
                >
                  <Pencil className="w-3.5 h-3.5" />
                </button>
              </span>
            </div>
          </div>
        </div>

        <div className="flex items-center space-x-3">
          <span className="px-3.5 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-100 border border-slate-200 dark:border-slate-700 text-xs font-black uppercase tracking-wider shadow-xs">
            PRIORITET: {task.priority?.toUpperCase() || 'MEDIUM'}
          </span>
          <button
            onClick={() => copyToClipboard(`#${task.ticketNumber}: ${task.todo}`, 'Zayavka ma\'lumoti')}
            className="p-2.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700 transition-colors"
            title="Nusxalash"
          >
            <Copy className="w-4 h-4" />
          </button>
        </div>
      </div>

      {/* 2. ENTERPRISE PIPELINE STEPPER BAR */}
      <div className="bg-white dark:bg-slate-900 rounded-3xl p-3 border border-slate-200 dark:border-slate-800 shadow-sm overflow-x-auto scrollbar-none">
        <div className="flex items-center justify-between min-w-[650px] gap-2">
          {stepperSteps.map((step, idx) => {
            const isCurrent = idx === currentStepIndex;
            const isPassed = idx < currentStepIndex;

            return (
              <div
                key={step.key}
                className={`flex-1 text-center py-2.5 px-4 text-xs font-black uppercase tracking-wider rounded-2xl transition-all border ${
                  isCurrent
                    ? step.key === 'stopped'
                      ? 'bg-rose-600 text-white border-rose-500 shadow-lg shadow-rose-600/30'
                      : 'bg-brand-600 text-white border-brand-500 shadow-lg shadow-brand-600/30'
                    : isPassed
                    ? 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-900'
                    : 'bg-slate-100 dark:bg-slate-800/60 text-slate-400 dark:text-slate-500 border-slate-200 dark:border-slate-800'
                }`}
              >
                {step.label}
              </div>
            );
          })}
        </div>
      </div>

      {/* MAIN TWO-COLUMN GRID (Left: Chat & History, Right: Device & User Info) */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        {/* LEFT COLUMN: Chat Box, Media, Workflow History */}
        <div className="lg:col-span-2 space-y-6">
          {/* Chat Box (User prompt speech bubble & specialist reply) */}
          <div className="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4 text-slate-900 dark:text-slate-100">
            <div className="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
              <span className="text-xs font-black uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center space-x-2">
                <MessageSquare className="w-4 h-4 text-brand-500 dark:text-brand-400" />
                <span>Chat Box & Murojaat Xabari</span>
              </span>
              <span className="text-xs font-black text-brand-600 dark:text-brand-400 font-mono">#{task.ticketNumber}</span>
            </div>

            {/* Initiator Message Bubble (Theme-Responsive Card) */}
            <div className="p-5 rounded-3xl bg-white dark:bg-slate-800/90 text-slate-900 dark:text-white border border-slate-200 dark:border-slate-700 space-y-3 shadow-sm">
              <div className="flex items-center justify-between text-xs text-slate-500 dark:text-slate-300 border-b border-slate-100 dark:border-slate-700 pb-2">
                <span className="font-extrabold text-slate-900 dark:text-white flex items-center space-x-2.5 text-sm">
                  <span className="w-8 h-8 rounded-full bg-brand-600 text-white flex items-center justify-center font-black text-xs border border-brand-500 shadow-xs">
                    {task.initiatorName ? task.initiatorName.charAt(0).toUpperCase() : 'M'}
                  </span>
                  <span>{task.initiatorName || 'Murojaatchi'} (Murojaat Xabari)</span>
                </span>
                <span className="font-mono text-xs text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-900 px-3 py-1 rounded-lg border border-slate-200 dark:border-slate-700">{task.createdAt}</span>
              </div>
              <p className="text-base font-bold text-slate-900 dark:text-slate-100 leading-relaxed pt-1">
                {cleanTodoText || task.todo}
              </p>
              {cleanDescriptionText && cleanDescriptionText !== cleanTodoText && (
                <p className="text-xs text-slate-600 dark:text-slate-300 pt-2 border-t border-slate-100 dark:border-slate-700/60">
                  {cleanDescriptionText}
                </p>
              )}
            </div>

            {/* Specialist Solution Reply Bubble (Crisp Emerald Card If Solved) */}
            {isSolved && task.solutionComment && (
              <div className="p-5 rounded-3xl bg-emerald-900/60 dark:bg-emerald-950/80 border border-emerald-700/80 text-emerald-100 space-y-3 ml-4 sm:ml-8 shadow-md">
                <div className="flex items-center justify-between text-xs text-emerald-300 border-b border-emerald-800/80 pb-2">
                  <span className="font-extrabold text-emerald-200 flex items-center space-x-2.5 text-sm">
                    <span className="w-8 h-8 rounded-full bg-emerald-600 text-white flex items-center justify-center font-black text-xs border border-emerald-400 shadow-sm">
                      {task.assignedTo ? task.assignedTo.charAt(0).toUpperCase() : 'A'}
                    </span>
                    <span>{task.assignedTo || 'Ijrochi Xodim'} (Bajarilgan Ishlar Izohi)</span>
                  </span>
                  <span className="font-mono text-xs text-emerald-300 bg-emerald-900/90 px-3 py-1 rounded-lg border border-emerald-700">{task.resolvedAt || 'Yopilgan'}</span>
                </div>
                <p className="text-base font-bold text-emerald-50 leading-relaxed pt-1">
                  {task.solutionComment}
                </p>
              </div>
            )}

            {/* Dynamic Comments & Chat Thread */}
            {task.comments && task.comments.length > 0 && (
              <div className="space-y-3 pt-2 border-t border-slate-200 dark:border-slate-800">
                <span className="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">Yozishmalar tarixi ({task.comments.length}):</span>
                {task.comments.map((comment) => (
                  <div key={comment.id} className="p-3.5 rounded-2xl bg-slate-100 dark:bg-slate-800/90 border border-slate-200 dark:border-slate-700 space-y-1">
                    <div className="flex items-center justify-between text-[11px]">
                      <span className="font-extrabold text-brand-600 dark:text-brand-300">@{comment.author}</span>
                      <span className="text-slate-400 font-mono">{comment.createdAt}</span>
                    </div>
                    <p className="text-xs font-semibold text-slate-800 dark:text-slate-100">{comment.body}</p>
                  </div>
                ))}
              </div>
            )}

            {/* Send Message Button inside Chat Box */}
            <div className="pt-2 flex justify-end">
              <button
                onClick={() => setIsMessageModalOpen(true)}
                className="px-4 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-500 text-white font-extrabold text-xs flex items-center space-x-2 shadow-md transition-all cursor-pointer border-none"
              >
                <Send className="w-3.5 h-3.5" />
                <span>Xabar Yuborish</span>
              </button>
            </div>
          </div>

          {/* Always-Visible Media & Voice Messages Box */}
          <div className="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4 text-slate-900 dark:text-slate-100">
            <span className="text-xs font-black uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center space-x-2 border-b border-slate-100 dark:border-slate-800 pb-3">
              <Volume2 className="w-4 h-4 text-purple-500 dark:text-purple-400" />
              <span>Ovozli, Video va Ilova Qilingan Media Fayllar</span>
            </span>

            {/* Audio Voice Player Component */}
            {hasVoiceMessage ? (
              <div className="p-4 rounded-2xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 space-y-2">
                <span className="text-xs font-extrabold text-emerald-600 dark:text-emerald-400 flex items-center space-x-2">
                  <Volume2 className="w-4 h-4 text-emerald-500 animate-pulse" />
                  <span>Murojaatchi yuborgan ovozli xabar (Voice Note):</span>
                </span>
                <audio controls src={task.audioUrl || "https://www.w3schools.com/html/horse.ogg"} className="w-full h-10 rounded-lg" />
              </div>
            ) : (
              <div className="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-800 text-xs font-semibold text-slate-400 flex items-center space-x-2">
                <Volume2 className="w-4 h-4 text-slate-400" />
                <span>Ushbu zayavkaga biriktirilgan ovozli xabar mavjud emas</span>
              </div>
            )}

            {/* Video Player Component */}
            {task.videoUrl ? (
              <div className="p-4 rounded-2xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 space-y-2">
                <span className="text-xs font-extrabold text-brand-600 dark:text-brand-400 flex items-center space-x-2">
                  <Video className="w-4 h-4 text-brand-500" />
                  <span>Murojaatchi yuborgan video xabar:</span>
                </span>
                <video controls src={task.videoUrl} className="w-full max-h-64 rounded-xl object-contain bg-black" />
              </div>
            ) : null}

            {/* Screenshots / Attachments Preview */}
            <div className="pt-1">
              <span className="text-xs font-bold text-slate-500 dark:text-slate-400 block mb-2">Ilova qilingan rasm / Screenshot:</span>
              {task.screenshotUrl ? (
                <div className="flex items-center space-x-3">
                  <div
                    onClick={() => setIsImageModalOpen(true)}
                    className="w-36 h-28 rounded-2xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 overflow-hidden cursor-pointer group relative shadow-md"
                  >
                    <img
                      src={task.screenshotUrl}
                      alt="Screenshot preview"
                      className="w-full h-full object-cover group-hover:scale-105 transition-transform"
                    />
                    <div className="absolute inset-0 bg-black/40 group-hover:bg-black/20 transition-colors flex items-center justify-center">
                      <span className="text-[10px] font-black text-white px-2 py-0.5 rounded-full bg-black/70 backdrop-blur-xs">Kattalashtirish</span>
                    </div>
                  </div>
                </div>
              ) : (
                <div className="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-800 text-xs text-slate-400 font-semibold italic">
                  Ushbu zayavkaga biriktirilgan rasm yoki fayl mavjud emas
                </div>
              )}
            </div>
          </div>

          {/* Workflow Timeline Box */}
          <div className="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4 text-slate-900 dark:text-slate-100">
            <span className="text-xs font-black uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center space-x-2 border-b border-slate-100 dark:border-slate-800 pb-3">
              <Activity className="w-4 h-4 text-amber-500 dark:text-amber-400" />
              <span>Workflow (Harakatlar Tarixi)</span>
            </span>

            <div className="space-y-3 font-medium text-xs">
              <div className="flex items-start space-x-3 p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700">
                <CheckCircle className="w-4 h-4 text-emerald-500 flex-shrink-0 mt-0.5" />
                <div className="space-y-1">
                  <div className="flex items-center space-x-2">
                    <span className={`px-2 py-0.5 rounded-lg text-[10px] font-extrabold uppercase border ${
                      isSolved
                        ? 'bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800'
                        : isRejected
                        ? 'bg-rose-100 dark:bg-rose-950 text-rose-700 dark:text-rose-300 border-rose-300 dark:border-rose-800'
                        : 'bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-300 border-amber-300 dark:border-amber-800'
                    }`}>
                      {isSolved ? 'DONE' : isRejected ? 'REJECTED' : 'IN PROGRESS'}
                    </span>
                  </div>
                  <p className="text-slate-800 dark:text-slate-200 font-semibold">
                    Comment left: {task.solutionComment || 'Zayavka ko\'rib chiqildi.'}
                  </p>
                  <p className="text-[11px] text-slate-400 font-mono">
                    Begin date: {task.createdAt} | By whom: {task.assignedTo || 'admin'}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>

        {/* RIGHT COLUMN: Device Info & User Info Boxes (Unified Clean Colors) */}
        <div className="lg:col-span-1 space-y-6">
          {/* 1. Device Info Box */}
          <div className="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4 text-slate-900 dark:text-slate-100">
            <div className="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
              <span className="text-xs font-black uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center space-x-2">
                <Laptop className="w-4 h-4 text-slate-400" />
                <span>Device Info</span>
              </span>
              <span className="px-2 py-0.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-[10px] font-bold text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                Quick response
              </span>
            </div>

            <div className="space-y-3 text-xs">
              <div className="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-800">
                <span className="font-semibold text-slate-400">Computer name</span>
                <span className="font-bold text-slate-900 dark:text-slate-100 font-mono text-[11px] truncate max-w-[170px]" title={task.deviceName || 'Linux 70db6885b8ae'}>
                  {task.deviceName || 'Linux 70db6885b8ae 3.10.0-1160.102.1.el7....'}
                </span>
              </div>

              <div className="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-800">
                <span className="font-semibold text-slate-400">IP</span>
                <span className="font-extrabold text-slate-900 dark:text-slate-100 font-mono">
                  {task.ipAddress || '172.27.108.142'}
                </span>
              </div>

              <div className="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-800">
                <span className="font-semibold text-slate-400">Browser</span>
                <span className="font-bold text-slate-900 dark:text-slate-100">
                  {task.browser || 'Google Chrome'}
                </span>
              </div>

              <div className="flex justify-between py-1.5">
                <span className="font-semibold text-slate-400">Link</span>
                {task.brokenUrl ? (
                  <a href={task.brokenUrl} target="_blank" rel="noreferrer" className="font-bold text-brand-600 dark:text-brand-400 hover:underline font-mono truncate max-w-[160px]">
                    {task.brokenUrl}
                  </a>
                ) : (
                  <span className="font-bold text-brand-600 dark:text-brand-400 hover:underline font-mono">
                    http://172.28.7.100/profile
                  </span>
                )}
              </div>
            </div>
          </div>

          {/* 2. User Info Box (Unified Clean Colors) */}
          <div className="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4 text-slate-900 dark:text-slate-100">
            <div className="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-3">
              <span className="text-xs font-black uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center space-x-2">
                <UserIcon className="w-4 h-4 text-slate-400" />
                <span>User Info</span>
              </span>
              <span className="text-xs font-bold text-slate-600 dark:text-slate-300">
                {task.sourceChannel || 'Web Portal'}
              </span>
            </div>

            <div className="space-y-3 text-xs">
              <div className="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-800">
                <span className="font-semibold text-slate-400">Full name</span>
                <span className="font-extrabold text-slate-900 dark:text-slate-100 text-right">
                  {task.initiatorName || 'superadmin'}
                </span>
              </div>

              <div className="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-800">
                <span className="font-semibold text-slate-400">User ID</span>
                <span className="font-bold text-slate-900 dark:text-slate-100 font-mono">
                  {task.userId || '1'}
                </span>
              </div>

              <div className="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-800">
                <span className="font-semibold text-slate-400">MFO</span>
                <span className="font-bold text-slate-900 dark:text-slate-100 font-mono">
                  {task.mfo || '37149'}
                </span>
              </div>

              <div className="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-800">
                <span className="font-semibold text-slate-400">Phone number</span>
                <span className="font-extrabold text-slate-900 dark:text-slate-100 font-mono">
                  {task.initiatorPhone || '(93) 224-64-65'}
                </span>
              </div>

              <div className="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-800">
                <span className="font-semibold text-slate-400">PINFL</span>
                <span className="font-bold text-slate-900 dark:text-slate-100 font-mono">
                  {task.pinfl || '33110804070014'}
                </span>
              </div>

              <div className="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-800">
                <span className="font-semibold text-slate-400">Local code</span>
                <span className="font-bold text-slate-900 dark:text-slate-100 font-mono">
                  {task.localCode || '017160'}
                </span>
              </div>

              <div className="flex justify-between py-1.5">
                <span className="font-semibold text-slate-400">Floor / Etaj</span>
                <span className="font-extrabold text-slate-900 dark:text-slate-100 font-mono">
                  {task.floor || '3-qavat'}
                </span>
              </div>
            </div>
          </div>

          {/* Action Buttons for Specialist */}
          {!isSolved && isOpenUnassigned && (
            <Button
              variant="primary"
              className="w-full bg-brand-600 hover:bg-brand-500 font-extrabold border-none"
              size="lg"
              onClick={handleAcceptTask}
              leftIcon={<CheckCircle className="w-5 h-5" />}
            >
              Zayavkani Qabul Qilish
            </Button>
          )}

          {!isSolved && task.status === 'in_progress' && (
            <div className="p-5 rounded-3xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-3">
              <span className="text-xs font-black text-slate-900 dark:text-slate-100 block">Zayavkani Yopish Izohi:</span>
              <textarea
                value={solutionComment}
                onChange={(e) => setSolutionComment(e.target.value)}
                placeholder="Bajarilgan ishlar bo'yicha qisqacha izoh kiriting..."
                className="w-full p-3 rounded-2xl border border-slate-200 dark:border-slate-800 text-xs bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-brand-500 focus:outline-none"
                rows={3}
              />
              <Button
                variant="primary"
                className="w-full bg-emerald-600 hover:bg-emerald-500 border-none font-extrabold text-white"
                onClick={handleMarkAsCompleted}
                leftIcon={<CheckCircle className="w-5 h-5" />}
              >
                Bajarildi Deb Belgilash
              </Button>
            </div>
          )}
        </div>
      </div>

      {/* Reassign Staff Modal */}
      {isAssignModalOpen && (
        <Modal isOpen={isAssignModalOpen} onClose={() => setIsAssignModalOpen(false)} title="Zayavkani Xodimga Biriktirish">
          <div className="space-y-5 p-4 text-xs bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 rounded-2xl">
            {/* Quick Takeover Option */}
            <div className="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 space-y-2">
              <div className="flex items-center justify-between">
                <span className="font-extrabold text-amber-900 dark:text-amber-300 text-sm">⚡ O'zlashtirish (Takeover)</span>
                <span className="text-[10px] font-bold text-amber-700 dark:text-amber-400">Tezkor</span>
              </div>
              <p className="text-slate-600 dark:text-slate-300">
                Ushbu zayavka boshqa xodimda turgan bo'lsa ham, uni darhol <strong>o'zingizga biriktirib</strong> ({currentUser?.username || 'admin'}) yechim kiritishingiz mumkin.
              </p>
              <Button
                variant="primary"
                className="w-full bg-amber-500 hover:bg-amber-600 border-none text-white font-extrabold"
                onClick={() => handleAssignTask(currentUser?.id)}
                isLoading={isAssigning}
                leftIcon={<Zap className="w-4 h-4" />}
              >
                Zayavkani O'zimga Biriktirish
              </Button>
            </div>

            <div className="border-t border-slate-100 dark:border-slate-800 pt-4 space-y-3">
              <span className="font-extrabold text-slate-800 dark:text-slate-200 block">Yoki Bo'lim Xodimlaridan Birini Tanlang:</span>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-48 overflow-y-auto p-1">
                {staffList.map((emp) => {
                  const isSelected = selectedAssigneeId === emp.id;
                  return (
                    <div
                      key={emp.id}
                      onClick={() => setSelectedAssigneeId(emp.id)}
                      className={`p-2.5 rounded-xl border cursor-pointer flex items-center space-x-2 transition-all ${
                        isSelected
                          ? 'border-brand-500 bg-brand-50/60 dark:bg-brand-950/60 ring-2 ring-brand-500/20 font-bold'
                          : 'border-slate-200 dark:border-slate-800 hover:border-brand-300 dark:hover:border-brand-700 bg-slate-50/50 dark:bg-slate-800/40'
                      }`}
                    >
                      <img
                        src={emp.image || `https://ui-avatars.com/api/?name=${encodeURIComponent(emp.username)}&size=512&bold=true&background=0D8ABC&color=fff`}
                        alt={emp.name}
                        className="w-7 h-7 rounded-full object-cover border border-slate-200 dark:border-slate-700"
                      />
                      <div className="truncate">
                        <span className="block font-extrabold text-slate-800 dark:text-slate-200 truncate">{emp.name}</span>
                        <span className="block text-[10px] text-slate-400 font-mono">@{emp.username}</span>
                      </div>
                    </div>
                  );
                })}
              </div>

              <div className="space-y-1.5 pt-2">
                <span className="font-bold text-slate-600 dark:text-slate-300 block">Biriktirish sababi (izoh):</span>
                <input
                  type="text"
                  value={reassignReason}
                  onChange={(e) => setReassignReason(e.target.value)}
                  placeholder="Masalan: Boshqa mutaxassisga qayta yo'naltirildi..."
                  className="w-full p-2.5 rounded-xl border border-slate-200 dark:border-slate-800 text-xs bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-brand-500 focus:outline-none"
                />
              </div>
            </div>

            <div className="flex justify-end space-x-2 pt-2">
              <Button variant="secondary" onClick={() => setIsAssignModalOpen(false)}>
                Bekor qilish
              </Button>
              <Button
                variant="primary"
                onClick={() => handleAssignTask()}
                isLoading={isAssigning}
                disabled={!selectedAssigneeId}
                leftIcon={<UserCheck className="w-4 h-4" />}
              >
                Tanlangan Xodimga Biriktirish
              </Button>
            </div>
          </div>
        </Modal>
      )}

      {/* Image Zoom Modal */}
      {isImageModalOpen && task.screenshotUrl && (
        <Modal isOpen={isImageModalOpen} onClose={() => setIsImageModalOpen(false)} title="Rasmni ko'rish">
          <div className="p-4 text-center bg-white dark:bg-slate-900 rounded-2xl">
            <img
              src={task.screenshotUrl}
              alt="Screenshot full"
              className="max-h-[80vh] mx-auto rounded-2xl object-contain shadow-lg border border-slate-200 dark:border-slate-700"
            />
          </div>
        </Modal>
      )}

      {/* Send Message Modal */}
      {isMessageModalOpen && (
        <Modal isOpen={isMessageModalOpen} onClose={() => setIsMessageModalOpen(false)} title="Xabar yuborish">
          <div className="space-y-4 p-4 text-xs bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 rounded-2xl">
            <textarea
              value={messageText}
              onChange={(e) => setMessageText(e.target.value)}
              placeholder="Foydalanuvchiga yuboriladigan izoh yoki xabarni kiriting..."
              className="w-full p-3 rounded-2xl border border-slate-200 dark:border-slate-800 text-xs bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-brand-500 focus:outline-none"
              rows={4}
            />
            <div className="flex justify-end space-x-2">
              <Button variant="secondary" onClick={() => setIsMessageModalOpen(false)}>
                Bekor qilish
              </Button>
              <Button variant="primary" onClick={handleSendMessage} leftIcon={<Send className="w-4 h-4" />}>
                Yuborish
              </Button>
            </div>
          </div>
        </Modal>
      )}
    </div>
  );
};
