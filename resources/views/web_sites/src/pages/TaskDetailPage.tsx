import React, { useState, useEffect, useRef } from 'react';
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
  ZoomIn,
  ZoomOut,
  Maximize,
  X,
  PlayCircle,
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

  // Image zoom modal state
  const [zoomImageUrl, setZoomImageUrl] = useState<string | null>(null);
  const [zoomScale, setZoomScale] = useState(1);
  const [panOffset, setPanOffset] = useState({ x: 0, y: 0 });
  const zoomContainerRef = useRef<HTMLDivElement | null>(null);
  const dragState = useRef<{ startX: number; startY: number; panX: number; panY: number; dragging: boolean }>({
    startX: 0,
    startY: 0,
    panX: 0,
    panY: 0,
    dragging: false,
  });

  // Live timer: qabul qilingan paytdan boshlab o'tgan vaqt (har soniyada yangilanadi)
  const [nowTick, setNowTick] = useState<number>(Date.now());
  useEffect(() => {
    const interval = setInterval(() => setNowTick(Date.now()), 1000);
    return () => clearInterval(interval);
  }, []);

  const formatElapsed = (ms: number): string => {
    if (ms < 0) ms = 0;
    const s = Math.floor(ms / 1000);
    const days = Math.floor(s / 86400);
    const hh = Math.floor((s % 86400) / 3600).toString().padStart(2, '0');
    const mm = Math.floor((s % 3600) / 60).toString().padStart(2, '0');
    const ss = (s % 60).toString().padStart(2, '0');
    return days > 0 ? `${days} kun ${hh}:${mm}:${ss}` : `${hh}:${mm}:${ss}`;
  };

  const openZoom = (url: string) => {
    setZoomScale(1);
    setPanOffset({ x: 0, y: 0 });
    setZoomImageUrl(url);
  };

  // Drag-to-pan: rasmni mishka bilan tortib surish (translate orqali)
  const handleDragStart = (e: React.MouseEvent) => {
    dragState.current = {
      startX: e.clientX,
      startY: e.clientY,
      panX: panOffset.x,
      panY: panOffset.y,
      dragging: false,
    };
  };

  const handleDragMove = (e: React.MouseEvent) => {
    const state = dragState.current;
    if (!state.startX) return;

    const dx = e.clientX - state.startX;
    const dy = e.clientY - state.startY;

    if (Math.abs(dx) + Math.abs(dy) > 5) {
      state.dragging = true;
    }

    if (state.dragging) {
      setPanOffset({ x: state.panX + dx, y: state.panY + dy });
    }
  };

  const handleDragEnd = () => {
    dragState.current.startX = 0;
  };

  const handleZoomContainerClick = () => {
    // Drag bo'lgan bo'lsa yopmaslik
    if (dragState.current.dragging) {
      dragState.current.dragging = false;
      return;
    }
    setZoomImageUrl(null);
  };

  // Close zoom lightbox with ESC
  useEffect(() => {
    if (!zoomImageUrl) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setZoomImageUrl(null);
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [zoomImageUrl]);

  // Mouse wheel zoom (mishka o'rtasi — yuqoriga: kattalash, pastga: kichraytir)
  useEffect(() => {
    if (!zoomImageUrl) return;
    const el = zoomContainerRef.current;
    if (!el) return;

    const onWheel = (e: WheelEvent) => {
      e.preventDefault();
      if (e.deltaY < 0) {
        setZoomScale((s) => Math.min(s * 1.1, 5));
      } else {
        setZoomScale((s) => Math.max(s / 1.1, 0.25));
      }
    };

    el.addEventListener('wheel', onWheel, { passive: false });
    return () => el.removeEventListener('wheel', onWheel);
  }, [zoomImageUrl]);

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
    // Qabul qilish — faqat o'ziga biriktiradi (status todo bo'lib qoladi),
    // "In Progressga O'tkazish" tugmasi alohida bosiladi.
    updateTaskMutation.mutate(
      { id: task.id, dto: { assignToMe: true } },
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

  const handleMoveToInProgress = () => {
    if (!task) return;
    updateTaskMutation.mutate(
      { id: task.id, dto: { status: 'in_progress' } },
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

  // Staff-only actions: assignment / takeover
  const isStaffUser = Boolean(currentUser?.isStaff) || currentUser?.username === 'superadmin' || currentUser?.username === 'admin';
  const isTakingOverSomeoneElse = Boolean(task.assignedUserId && task.assignedUserId !== currentUser?.id);

  // Detect voice message and clean text tags
  const hasVoiceMessage = Boolean(task.audioUrl);

  const cleanTodoText = task.todo ? task.todo.replace(/\[Ovozli xabar biriktirilgan\]/gi, '').trim() : '';
  const cleanDescriptionText = task.description ? task.description.replace(/\[Ovozli xabar biriktirilgan\]/gi, '').trim() : '';

  // All attached media (image / video / audio) from backend `media` list
  const mediaList = task.media || [];
  const imagesToShow = mediaList.filter((m) => m.type === 'image').length > 0
    ? mediaList.filter((m) => m.type === 'image').map((m) => m.url)
    : task.screenshotUrl ? [task.screenshotUrl] : [];
  const videosToShow = mediaList.filter((m) => m.type === 'video').length > 0
    ? mediaList.filter((m) => m.type === 'video')
    : task.videoUrl ? [{ id: -1, url: task.videoUrl }] : [];
  const previewImageUrl = imagesToShow[0];
  const extraImageUrls = imagesToShow.slice(1);

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
                {/* Pencil Edit Icon next to Responsible Employee (staff only) */}
                {isStaffUser && (
                  <button
                    onClick={() => { setIsAssignModalOpen(true); fetchStaffList(); }}
                    className="p-1.5 rounded-lg bg-slate-100 dark:bg-slate-700 hover:bg-amber-500 text-amber-600 dark:text-amber-300 hover:text-white transition-all cursor-pointer border border-slate-200 dark:border-slate-600 shadow-xs ml-1 flex items-center"
                    title="Xodimga biriktirish / Qayta biriktirish"
                  >
                    <Pencil className="w-3.5 h-3.5" />
                  </button>
                )}
              </span>
            </div>
          </div>
        </div>

        {/* Live timer: qabul qilinganidan beri o'tgan vaqt (katta sariq card) */}
        {task.startedAtIso && (
          <div className="px-6 py-3 rounded-2xl bg-gradient-to-br from-amber-400 via-amber-500 to-orange-500 text-white shadow-lg shadow-amber-500/30 border border-amber-300 dark:border-amber-400/70 flex-shrink-0">
            <span className="block text-2xl sm:text-3xl font-black font-mono tabular-nums tracking-tight drop-shadow-sm">
              {formatElapsed(
                isSolved && task.resolvedAtIso
                  ? new Date(task.resolvedAtIso).getTime() - new Date(task.startedAtIso).getTime()
                  : nowTick - new Date(task.startedAtIso).getTime()
              )}
            </span>
          </div>
        )}

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
                <audio controls src={task.audioUrl} className="w-full h-10 rounded-lg" />
              </div>
            ) : (
              <div className="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-800 text-xs font-semibold text-slate-400 flex items-center space-x-2">
                <Volume2 className="w-4 h-4 text-slate-400" />
                <span>Ushbu zayavkaga biriktirilgan ovozli xabar mavjud emas</span>
              </div>
            )}

            {/* Video Player Component */}
            {videosToShow.length > 0 && (
              <div className="space-y-3">
                {videosToShow.map((v, idx) => (
                  <div key={v.id} className="p-4 rounded-2xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 space-y-2">
                    <span className="text-xs font-extrabold text-brand-600 dark:text-brand-400 flex items-center space-x-2">
                      <Video className="w-4 h-4 text-brand-500" />
                      <span>Murojaatchi yuborgan video xabar{videosToShow.length > 1 ? ` (${idx + 1})` : ''}:</span>
                    </span>
                    <video controls src={v.url} className="w-full max-h-64 rounded-xl object-contain bg-black" />
                  </div>
                ))}
              </div>
            )}

            {/* Screenshots / Attachments Preview */}
            <div className="pt-1">
              <span className="text-xs font-bold text-slate-500 dark:text-slate-400 block mb-2">Ilova qilingan rasm / Screenshot:</span>
              {previewImageUrl ? (
                <div className="flex flex-wrap items-center gap-3">
                  <div
                    onClick={() => openZoom(previewImageUrl)}
                    className="w-36 h-28 rounded-2xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 overflow-hidden cursor-pointer group relative shadow-md"
                  >
                    <img
                      src={previewImageUrl}
                      alt="Screenshot preview"
                      className="w-full h-full object-cover group-hover:scale-105 transition-transform"
                    />
                    <div className="absolute inset-0 bg-black/40 group-hover:bg-black/20 transition-colors flex items-center justify-center">
                      <span className="text-[10px] font-black text-white px-2 py-0.5 rounded-full bg-black/70 backdrop-blur-xs">Kattalashtirish</span>
                    </div>
                  </div>
                  {extraImageUrls.map((imgUrl, idx) => (
                    <div
                      key={idx}
                      onClick={() => openZoom(imgUrl)}
                      className="w-24 h-20 rounded-2xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 overflow-hidden cursor-pointer group relative shadow-md"
                      title="Rasmni kattalashtirish"
                    >
                      <img
                        src={imgUrl}
                        alt={`Screenshot ${idx + 2}`}
                        className="w-full h-full object-cover group-hover:scale-105 transition-transform"
                      />
                      <div className="absolute inset-0 bg-black/40 group-hover:bg-black/20 transition-colors" />
                    </div>
                  ))}
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
                    —
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
                  {task.initiatorName || '—'}
                </span>
              </div>

              <div className="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-800">
                <span className="font-semibold text-slate-400">Username (AD)</span>
                <span className="font-bold text-slate-900 dark:text-slate-100 font-mono">
                  {task.requesterUsername || '—'}
                </span>
              </div>

              <div className="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-800">
                <span className="font-semibold text-slate-400">Email</span>
                <span className="font-bold text-slate-900 dark:text-slate-100 font-mono break-all">
                  {task.requesterEmail || '—'}
                </span>
              </div>

              <div className="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-800">
                <span className="font-semibold text-slate-400">Lavozim (AD)</span>
                <span className="font-extrabold text-slate-900 dark:text-slate-100 text-right">
                  {task.requesterPosition || '—'}
                </span>
              </div>

              <div className="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-800">
                <span className="font-semibold text-slate-400">Bo'lim (AD)</span>
                <span className="font-extrabold text-slate-900 dark:text-slate-100 text-right">
                  {task.requesterDepartment || task.originDepartment || '—'}
                </span>
              </div>

              <div className="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-800">
                <span className="font-semibold text-slate-400">Phone number</span>
                <span className="font-extrabold text-slate-900 dark:text-slate-100 font-mono">
                  {task.initiatorPhone || '—'}
                </span>
              </div>
            </div>
          </div>

          {/* Action Buttons for Specialist */}
          {!isSolved && isOpenUnassigned && isStaffUser && (
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

          {!isSolved && !isRejected && !isInProgress && !isOpenUnassigned && isStaffUser && (
            <Button
              variant="primary"
              className="w-full bg-amber-500 hover:bg-amber-600 font-extrabold border-none"
              size="lg"
              onClick={handleMoveToInProgress}
              leftIcon={<PlayCircle className="w-5 h-5" />}
            >
              In Progressga O'tkazish
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

      {/* Reassign Staff Modal (staff only) */}
      {isStaffUser && isAssignModalOpen && (
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
              {isTakingOverSomeoneElse && (
                <p className="text-[10px] font-extrabold text-rose-600 dark:text-rose-300">
                  ⚠️ Bu zayavka boshqa xodimga biriktirilgan — olish uchun quyida "Biriktirish sababi"ni kiritish MAJBURIY.
                </p>
              )}
              <Button
                variant="primary"
                className="w-full bg-amber-500 hover:bg-amber-600 border-none text-white font-extrabold"
                onClick={() => handleAssignTask(currentUser?.id)}
                isLoading={isAssigning}
                disabled={isTakingOverSomeoneElse && !reassignReason.trim()}
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
                <span className="font-bold text-slate-600 dark:text-slate-300 block">
                  Biriktirish sababi (izoh):{isTakingOverSomeoneElse && <span className="text-rose-500"> *</span>}
                </span>
                <input
                  type="text"
                  value={reassignReason}
                  onChange={(e) => setReassignReason(e.target.value)}
                  placeholder={isTakingOverSomeoneElse ? 'Sabab kiritish majburiy! Masalan: Xodim ta\'tilda, zudlik bilan hal qilish kerak...' : 'Masalan: Boshqa mutaxassisga qayta yo\'naltirildi...'}
                  className={`w-full p-2.5 rounded-xl border text-xs bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-brand-500 focus:outline-none ${
                    isTakingOverSomeoneElse && !reassignReason.trim()
                      ? 'border-rose-500 ring-2 ring-rose-500/20'
                      : 'border-slate-200 dark:border-slate-800'
                  }`}
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
                disabled={!selectedAssigneeId || (isTakingOverSomeoneElse && !reassignReason.trim())}
                leftIcon={<UserCheck className="w-4 h-4" />}
              >
                Tanlangan Xodimga Biriktirish
              </Button>
            </div>
          </div>
        </Modal>
      )}

      {/* Image Zoom Lightbox — to'liq ekran, kattalashtirish/kichraytirish bilan */}
      {zoomImageUrl && (
        <div className="fixed inset-0 z-[70] bg-black/90 backdrop-blur-sm flex flex-col animate-fadeIn">
          {/* Lightbox toolbar */}
          <div className="flex items-center justify-between px-4 sm:px-6 py-3 text-white border-b border-white/10">
            <span className="text-xs sm:text-sm font-extrabold flex items-center space-x-2">
              <ZoomIn className="w-4 h-4 text-brand-300" />
              <span>Rasmni ko'rish</span>
            </span>
            <div className="flex items-center space-x-1.5 sm:space-x-2">
              <button
                onClick={() => setZoomScale((s) => Math.max(s / 1.25, 0.25))}
                className="p-2 rounded-xl bg-white/10 hover:bg-white/20 transition-colors cursor-pointer"
                title="Kichraytirish"
              >
                <ZoomOut className="w-4 h-4" />
              </button>
              <span className="text-[11px] font-black text-brand-300 min-w-[44px] text-center">
                {Math.round(zoomScale * 100)}%
              </span>
              <button
                onClick={() => setZoomScale((s) => Math.min(s * 1.25, 5))}
                className="p-2 rounded-xl bg-white/10 hover:bg-white/20 transition-colors cursor-pointer"
                title="Kattalashtirish"
              >
                <ZoomIn className="w-4 h-4" />
              </button>
              <button
                onClick={() => {
                  setZoomScale(1);
                  setPanOffset({ x: 0, y: 0 });
                }}
                className="inline-flex items-center space-x-1.5 px-2.5 py-2 rounded-xl bg-white/10 hover:bg-white/20 transition-colors cursor-pointer"
                title="Ekran o'lchamiga moslash"
              >
                <Maximize className="w-4 h-4" />
                <span className="hidden sm:inline text-[11px] font-bold">Moslash</span>
              </button>
              <button
                onClick={() => setZoomImageUrl(null)}
                className="p-2 rounded-xl bg-rose-500/80 hover:bg-rose-500 transition-colors cursor-pointer"
                title="Yopish (Esc)"
              >
                <X className="w-4 h-4" />
              </button>
            </div>
          </div>

          {/* Scrollable image area — g'ildirak: zoom, tortish (drag): surish */}
          <div
            ref={zoomContainerRef}
            onMouseDown={handleDragStart}
            onMouseMove={handleDragMove}
            onMouseUp={handleDragEnd}
            onMouseLeave={handleDragEnd}
            onClick={handleZoomContainerClick}
            className={`flex-1 overflow-hidden p-4 sm:p-8 flex items-start justify-center select-none ${
              zoomScale > 1 ? 'cursor-grab active:cursor-grabbing' : 'cursor-default'
            }`}
          >
            <img
              src={zoomImageUrl}
              alt="Screenshot full"
              onClick={(e) => e.stopPropagation()}
              className="rounded-xl shadow-2xl select-none transition-transform duration-100 will-change-transform"
              style={{
                transform: `translate3d(${panOffset.x}px, ${panOffset.y}px, 0) scale(${zoomScale})`,
                ...(zoomScale === 1
                  ? { maxWidth: '90vw', maxHeight: '85vh', objectFit: 'contain' }
                  : { maxWidth: 'none', maxHeight: 'none' }),
              }}
            />
          </div>
        </div>
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
