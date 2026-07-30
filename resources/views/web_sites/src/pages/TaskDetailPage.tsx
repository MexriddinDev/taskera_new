import React, { useState } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { useTaskDetail } from '@/modules/tasks/infrastructure/presentation/hooks/useTaskDetail';
import { useUpdateTask } from '@/modules/tasks/infrastructure/presentation/hooks/useUpdateTask';
import { Button } from '@/shared/presentation/components/Button';
import { Modal } from '@/shared/presentation/components/Modal';
import {
  ArrowLeft,
  Clock,
  User as UserIcon,
  AlertTriangle,
  Copy,
  ExternalLink,
  Lock,
  Cpu,
  Code,
  Phone,
  Building2,
  MapPin,
  Laptop,
  Image as ImageIcon,
  Check,
  CheckCircle,
  Star,
  Camera,
  X,
  RotateCcw,
  Maximize2,
} from 'lucide-react';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { useAuthStore } from '@/shared/presentation/store/useAuthStore';

export const TaskDetailPage: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const taskId = Number(id);
  const navigate = useNavigate();

  const [copiedText, setCopiedText] = useState<string | null>(null);

  // Form states matching Flutter task_detail_screen
  const [solutionComment, setSolutionComment] = useState('');
  const [photoName, setPhotoName] = useState<string | null>(null);
  const [selectedRating, setSelectedRating] = useState<number>(5);
  const [clientRejectionReason, setClientRejectionReason] = useState('');

  // Image modal state
  const [isImageModalOpen, setIsImageModalOpen] = useState(false);

  // Message modal state
  const [isMessageModalOpen, setIsMessageModalOpen] = useState(false);
  const [messageText, setMessageText] = useState('');

  const { data: task, isLoading, isError, error, refetch } = useTaskDetail(taskId);
  const updateTaskMutation = useUpdateTask();

  const copyToClipboard = (text: string, label: string) => {
    navigator.clipboard.writeText(text);
    setCopiedText(label);
    setTimeout(() => setCopiedText(null), 2500);
  };

  // 1. Specialist Actions
  const handleAcceptTask = () => {
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

  const handleMarkAsCompleted = () => {
    if (!task) return;
    updateTaskMutation.mutate(
      {
        id: task.id,
        dto: {
          status: 'in_progress', // Pending client review
          solutionComment: solutionComment || 'Vazifa to\'liq bajarildi.',
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
      // keep modal open for retry
    }
  };

  // 2. Client Review Actions
  const handleClientApprove = () => {
    if (!task) return;
    updateTaskMutation.mutate(
      {
        id: task.id,
        dto: {
          status: 'done',
          completed: true,
          clientRating: selectedRating,
        },
      },
      {
        onSuccess: () => {
          navigate('/dashboard');
        },
      }
    );
  };

  const handleClientReject = () => {
    if (!task) return;
    updateTaskMutation.mutate(
      {
        id: task.id,
        dto: {
          status: 'rejected',
          rejectionReason: clientRejectionReason || 'Muammo to\'liq hal bo\'lmadi. Qayta ariza yuborildi.',
        },
      },
      {
        onSuccess: () => {
          refetch();
        },
      }
    );
  };

  if (isLoading) {
    return (
      <div className="w-full max-w-5xl mx-auto px-4 sm:px-8 lg:px-12 py-12">
        <div className="bg-white dark:bg-slate-800 rounded-2xl p-8 shadow-sm border border-slate-200 dark:border-slate-700 animate-pulse space-y-4">
          <div className="h-6 bg-slate-200 dark:bg-slate-700 rounded w-1/4" />
          <div className="h-8 bg-slate-200 dark:bg-slate-700 rounded w-3/4" />
          <div className="h-20 bg-slate-200 dark:bg-slate-700 rounded w-full" />
        </div>
      </div>
    );
  }

  if (isError || !task) {
    return (
      <div className="w-full max-w-xl mx-auto px-4 py-16 text-center">
        <div className="p-4 bg-error-50 text-error-500 rounded-full w-14 h-14 flex items-center justify-center mx-auto mb-4">
          <AlertTriangle className="w-8 h-8" />
        </div>
        <h2 className="text-xl font-bold text-slate-900 dark:text-slate-100 mb-2">Zayavka Topilmadi</h2>
        <p className="text-sm text-slate-500 dark:text-slate-400 mb-6">
          {error?.message || 'Bunday zayavka mavjud emas yoki o\'chirilgan bo\'lishi mumkin.'}
        </p>
        <Button variant="secondary" onClick={() => navigate('/dashboard')} leftIcon={<ArrowLeft className="w-4 h-4" />}>
          Dashboardga qaytish
        </Button>
      </div>
    );
  }

  const isSolved = task.status === 'done';
  const isRejected = task.status === 'rejected';
  const currentUser = useAuthStore((s) => s.user);
  const isAssignedToMe = task.isAssigned && currentUser && currentUser.username === task.assignedTo;
  const isOpenUnassigned = task.status === 'todo' && !task.isAssigned;
  const isInProgressAssigned = task.status === 'in_progress' && isAssignedToMe;

  return (
    <div className="w-full max-w-5xl mx-auto px-4 sm:px-8 lg:px-12 py-8 pb-32">
      {/* Navigation Topbar */}
      <div className="flex items-center justify-between mb-6">
        <Link
          to="/dashboard"
          className="inline-flex items-center text-sm font-medium text-slate-500 hover:text-brand-500 dark:text-slate-400 dark:hover:text-brand-400 transition-colors"
        >
          <ArrowLeft className="w-4 h-4 mr-1.5" />
          Orqaga
        </Link>

        {copiedText && (
          <div className="px-3.5 py-1 bg-success-50 dark:bg-success-700/30 text-success-500 text-xs font-semibold rounded-full border border-success-500/20 animate-fadeIn flex items-center space-x-1">
            <Check className="w-3.5 h-3.5" />
            <span>{copiedText}</span>
          </div>
        )}
      </div>

      {/* Main Container */}
      <div className="space-y-6">
        {/* Rejection Alert Banner (if rejected) matching Flutter */}
        {(isRejected || task.rejectionReason) && (
          <div className="p-5 rounded-2xl bg-error-50 dark:bg-error-700/20 border border-error-500/30 flex items-start space-x-3.5">
            <AlertTriangle className="w-6 h-6 text-error-500 flex-shrink-0 mt-0.5" />
            <div>
              <h4 className="text-sm font-bold text-error-500 mb-1">Qayta ariza (Mijoz rad etdi)</h4>
              <p className="text-xs text-error-500/90 font-medium">
                {task.rejectionReason || 'Muammo to\'liq hal bo\'lmagani sababli mijoz zayavkani rad etgan va qaytargan.'}
              </p>
            </div>
          </div>
        )}

        {/* Target Department & Priority Badges Header */}
        <div className="flex items-center justify-between">
          <span className="inline-flex items-center px-3.5 py-1 rounded-full text-xs font-bold bg-brand-50 text-brand-500 dark:bg-brand-950/40 border border-brand-500/20">
            {task.targetDepartment === 'hardware' ? (
              <>
                <Cpu className="w-4 h-4 mr-1.5" /> Hardware (Aparat)
              </>
            ) : (
              <>
                <Code className="w-4 h-4 mr-1.5 text-success-500" /> Software (Dasturiy)
              </>
            )}
          </span>

          <span className="px-3.5 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-600 dark:bg-amber-950/40 border border-amber-500/30">
            {task.priority.toUpperCase()} PRIORITET
          </span>
        </div>

        {/* Main Title & Description Card */}
        <div className="bg-white dark:bg-slate-800/90 rounded-2xl p-6 sm:p-8 shadow-sm border border-slate-200 dark:border-slate-700/80">
          <div className="flex flex-wrap items-center justify-between gap-4 mb-4 pb-4 border-b border-slate-100 dark:border-slate-700/60">
            <div>
              <div className="flex items-center space-x-2">
                <h2 className="text-xl font-extrabold text-slate-900 dark:text-slate-100">{task.ticketNumber}</h2>
                <button
                  onClick={() => copyToClipboard(task.ticketNumber, 'Zayavka raqami nusxalandi')}
                  className="p-1.5 text-slate-400 hover:text-brand-500 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
                  title="Ticket raqamini nusxalash"
                >
                  <Copy className="w-4 h-4" />
                </button>
              </div>
              <p className="text-xs text-slate-500 dark:text-slate-400 font-medium mt-0.5">{task.category}</p>
            </div>
          </div>

          <h1 className="text-2xl sm:text-3xl font-bold text-slate-900 dark:text-slate-100 mb-3">{task.todo}</h1>

          {task.deviceName && (
            <div className="inline-flex items-center space-x-2 px-3 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-700/50 text-xs text-slate-700 dark:text-slate-300 font-medium mb-3 border border-slate-200 dark:border-slate-700">
              <Laptop className="w-4 h-4 text-brand-500" />
              <span>Qurilma: <strong>{task.deviceName}</strong></span>
            </div>
          )}

          <div className="flex items-center space-x-2 text-xs text-slate-500 dark:text-slate-400 mt-2">
            <Clock className="w-3.5 h-3.5" />
            <span>Yaratilgan vaqt: {task.createdAt}</span>
          </div>
        </div>

        {/* Initiator & Location Card matching Flutter _buildInitiatorCard */}
        <div className="relative rounded-2xl overflow-hidden">
          {!task.isAssigned && (
            <div className="absolute inset-0 z-10 backdrop-blur-md bg-white/75 dark:bg-slate-900/75 flex items-center justify-center p-6 text-center">
              <div className="px-5 py-2.5 rounded-full bg-white/90 dark:bg-slate-800/90 border border-slate-300 dark:border-slate-700 shadow-lg flex items-center space-x-2">
                <Lock className="w-4 h-4 text-brand-500" />
                <span className="text-xs font-bold text-slate-800 dark:text-slate-200">
                  Qabul qilingach yuboruvchi ma'lumotlari ochiladi
                </span>
              </div>
            </div>
          )}

          <div className="bg-white dark:bg-slate-800/90 rounded-2xl p-6 sm:p-8 border border-slate-200 dark:border-slate-700/80 shadow-sm">
            <h3 className="text-sm font-bold text-slate-900 dark:text-slate-100 mb-4 flex items-center space-x-2">
              <UserIcon className="w-4 h-4 text-brand-500" />
              <span>Yuboruvchi & Joylashuv Ma'lumotlari</span>
            </h3>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
              <div className="p-4 rounded-xl bg-slate-50 dark:bg-slate-700/40 border border-slate-100 dark:border-slate-700 flex items-center space-x-3">
                <UserIcon className="w-4 h-4 text-slate-400" />
                <div>
                  <p className="text-[11px] text-slate-500 dark:text-slate-400 font-medium">F.I.SH</p>
                  <p className="font-semibold text-slate-900 dark:text-slate-100">{task.initiatorName || 'Biriktirilmagan'}</p>
                </div>
              </div>

              <div className="p-4 rounded-xl bg-slate-50 dark:bg-slate-700/40 border border-slate-100 dark:border-slate-700 flex items-center space-x-3">
                <Building2 className="w-4 h-4 text-slate-400" />
                <div>
                  <p className="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Boshqarma / Bo'lim</p>
                  <p className="font-semibold text-slate-900 dark:text-slate-100">{task.originDepartment}</p>
                </div>
              </div>

              <div className="p-4 rounded-xl bg-slate-50 dark:bg-slate-700/40 border border-slate-100 dark:border-slate-700 flex items-center space-x-3">
                <MapPin className="w-4 h-4 text-slate-400" />
                <div>
                  <p className="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Qavat / Xona</p>
                  <p className="font-semibold text-slate-900 dark:text-slate-100">{task.floor || 'Noma\'lum'}</p>
                </div>
              </div>

              <div className="p-4 rounded-xl bg-slate-50 dark:bg-slate-700/40 border border-slate-100 dark:border-slate-700 flex items-center justify-between">
                <div className="flex items-center space-x-3">
                  <Phone className="w-4 h-4 text-slate-400" />
                  <div>
                    <p className="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Telefon</p>
                    <p className="font-semibold text-slate-900 dark:text-slate-100">{task.initiatorPhone || '+998 90 000-00-00'}</p>
                  </div>
                </div>

                {task.initiatorPhone && (
                  <button
                    onClick={() => copyToClipboard(task.initiatorPhone!, 'Telefon raqami nusxalandi')}
                    className="p-2 rounded-lg bg-brand-50 text-brand-500 hover:bg-brand-500 hover:text-white transition-colors"
                    title="Telefonni nusxalash"
                  >
                    <Phone className="w-4 h-4" />
                  </button>
                )}
              </div>
            </div>
          </div>
        </div>

        {/* Broken Site URL Card (if present) */}
        {task.brokenUrl && (
          <div className="bg-white dark:bg-slate-800/90 rounded-2xl p-6 sm:p-8 border border-slate-200 dark:border-slate-700/80 shadow-sm">
            <h3 className="text-sm font-bold text-slate-900 dark:text-slate-100 mb-3 flex items-center space-x-2">
              <ExternalLink className="w-4 h-4 text-error-500" />
              <span>Ishlamayotgan Sayt / Tizim Linki</span>
            </h3>

            <div className="p-4 rounded-xl bg-error-50/60 dark:bg-error-700/20 border border-error-500/30 flex items-center justify-between gap-3">
              <span className="text-xs font-medium text-error-500 truncate">{task.brokenUrl}</span>

              <div className="flex items-center space-x-2 flex-shrink-0">
                <button
                  onClick={() => copyToClipboard(task.brokenUrl!, 'Sayt linki nusxalandi')}
                  className="p-2 rounded-lg bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:text-brand-500 border border-slate-200 dark:border-slate-700 transition-colors"
                  title="Linkni nusxalash"
                >
                  <Copy className="w-4 h-4" />
                </button>
                <a
                  href={task.brokenUrl}
                  target="_blank"
                  rel="noreferrer"
                  className="p-2 rounded-lg bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:text-brand-500 border border-slate-200 dark:border-slate-700 transition-colors"
                  title="Brauzerda ochish"
                >
                  <ExternalLink className="w-4 h-4" />
                </a>
              </div>
            </div>
          </div>
        )}

        {/* Attachment / Error Screenshot Card with Interactive Zoom Modal */}
        {task.screenshotUrl && (
          <div className="bg-white dark:bg-slate-800/90 rounded-2xl p-6 sm:p-8 border border-slate-200 dark:border-slate-700/80 shadow-sm">
            <h3 className="text-sm font-bold text-slate-900 dark:text-slate-100 mb-3 flex items-center space-x-2">
              <ImageIcon className="w-4 h-4 text-brand-500" />
              <span>Xatolik Skrinshoti (Rasm)</span>
            </h3>

            <div
              onClick={() => setIsImageModalOpen(true)}
              className="rounded-xl overflow-hidden border border-slate-200 dark:border-slate-700 relative group cursor-pointer"
            >
              <img
                src={task.screenshotUrl}
                alt="Task attachment"
                className="w-full max-h-[400px] object-cover group-hover:scale-105 transition-transform duration-300"
              />
              <div className="absolute bottom-3 right-3 px-3 py-1.5 rounded-full bg-slate-900/80 text-white text-xs font-bold backdrop-blur-md flex items-center space-x-1.5 shadow-md">
                <Maximize2 className="w-3.5 h-3.5" />
                <span>Kattalashtirish</span>
              </div>
            </div>
          </div>
        )}

        {/* Solution Comment Form Card (when accepted or rejected) matching Flutter lines 436-472 */}
        {(isAssignedToMe || isRejected) && (
          <div className="bg-white dark:bg-slate-800/90 rounded-2xl p-6 sm:p-8 border border-slate-200 dark:border-slate-700/80 shadow-sm space-y-4">
            <h3 className="text-sm font-bold text-slate-900 dark:text-slate-100">Yechim Izohi (Ixtiyoriy)</h3>
            <textarea
              value={solutionComment}
              onChange={(e) => setSolutionComment(e.target.value)}
              rows={3}
              placeholder="Bajarilgan ish haqida qisqacha yozing (masalan: Canon MF232w kartridji zapravka qilindi)..."
              className="w-full p-3.5 rounded-xl bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-xs font-semibold text-slate-900 dark:text-slate-100 focus:outline-none focus:border-brand-500 transition-colors"
            />

            <div className="flex items-center space-x-3">
              <button
                type="button"
                onClick={() => setPhotoName(photoName ? null : 'photo_attachment_mod.jpg')}
                className="inline-flex items-center space-x-2 px-4 py-2 rounded-xl bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors"
              >
                <Camera className="w-4 h-4 text-brand-500" />
                <span>{photoName || 'Rasm ilova qilish'}</span>
              </button>
              {photoName && (
                <button
                  type="button"
                  onClick={() => setPhotoName(null)}
                  className="p-1.5 rounded-lg text-error-500 hover:bg-error-50 transition-colors"
                  title="Rasmni o'chirish"
                >
                  <X className="w-4 h-4" />
                </button>
              )}
            </div>
          </div>
        )}

        {/* Client Evaluation Card (when pending client review / inProgress) matching Flutter lines 309-395 */}
        {isInProgressAssigned && (
          <div className="bg-white dark:bg-slate-800/90 rounded-2xl p-6 sm:p-8 border border-slate-200 dark:border-slate-700/80 shadow-sm space-y-4">
            <div className="flex items-center space-x-2">
              <Star className="w-5 h-5 text-amber-500 fill-amber-400" />
              <h3 className="text-sm font-bold text-slate-900 dark:text-slate-100">Mijoz (Abonent) Baholashi</h3>
            </div>
            <p className="text-xs text-slate-500 dark:text-slate-400">
              Vazifa topshirildi. Mijoz baholaydi va arizani yopadi yoki rad etadi.
            </p>

            {/* Interactive 5 Star Bar */}
            <div className="flex items-center justify-center space-x-2 py-2">
              {[1, 2, 3, 4, 5].map((star) => (
                <button
                  key={star}
                  onClick={() => setSelectedRating(star)}
                  className="p-1 hover:scale-125 transition-transform"
                >
                  <Star
                    className={`w-8 h-8 ${
                      star <= selectedRating ? 'text-amber-400 fill-amber-400' : 'text-slate-300 dark:text-slate-600'
                    }`}
                  />
                </button>
              ))}
            </div>

            <textarea
              value={clientRejectionReason}
              onChange={(e) => setClientRejectionReason(e.target.value)}
              rows={2}
              placeholder="Rad etish sababini kiriting (agar qayta ariza bo'lsa)..."
              className="w-full p-3.5 rounded-xl bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-xs font-semibold text-slate-900 dark:text-slate-100 focus:outline-none focus:border-brand-500 transition-colors"
            />

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
              <Button
                onClick={handleClientApprove}
                isLoading={updateTaskMutation.isPending}
                className="w-full bg-success-500 hover:bg-success-600 text-white font-bold py-3 text-xs rounded-xl shadow-sm"
                leftIcon={<CheckCircle className="w-4 h-4" />}
              >
                Baho & Yopish
              </Button>
              <Button
                onClick={handleClientReject}
                isLoading={updateTaskMutation.isPending}
                variant="danger"
                className="w-full font-bold py-3 text-xs rounded-xl shadow-sm"
                leftIcon={<RotateCcw className="w-4 h-4" />}
              >
                Qayta ariza (Rad etish)
              </Button>
            </div>
          </div>
        )}

        {/* Resolution Result Card if Solved matching Flutter lines 398-433 */}
        {isSolved && (
          <div className="bg-white dark:bg-slate-800/90 rounded-2xl p-6 sm:p-8 border border-slate-200 dark:border-slate-700/80 shadow-sm space-y-3">
            <div className="flex items-center space-x-2 text-success-500">
              <CheckCircle className="w-5 h-5" />
              <h3 className="text-sm font-bold text-slate-900 dark:text-slate-100">Bajarilgan Ish Natijasi</h3>
            </div>
            <p className="text-xs text-slate-600 dark:text-slate-300 font-medium">
              {task.solutionComment || 'Vazifa to\'liq bajarildi va mijoz tomonidan tasdiqlandi.'}
            </p>
            {task.clientRating !== undefined && task.clientRating !== null && (
              <div className="flex items-center space-x-1.5 pt-2">
                <span className="text-xs font-bold text-slate-500">Mijoz bahosi:</span>
                <div className="flex items-center space-x-1">
                  {[1, 2, 3, 4, 5].map((star) => (
                    <Star
                      key={star}
                      className={`w-4 h-4 ${
                        star <= Number(task.clientRating)
                          ? 'text-amber-400 fill-amber-400'
                          : 'text-slate-300 dark:text-slate-600'
                      }`}
                    />
                  ))}
                  <span className="text-xs font-extrabold text-amber-500 ml-1">
                    ({task.clientRating} / 5)
                  </span>
                </div>
              </div>
            )}
          </div>
        )}
      </div>

      {/* Sticky Bottom Action Bar matching Flutter bottomSheet lines 478-542 */}
      <div className="fixed bottom-0 left-0 right-0 z-40 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md border-t border-slate-200 dark:border-slate-800 p-4 sm:px-8">
        <div className="max-w-5xl mx-auto flex items-center justify-between gap-4">
          {isOpenUnassigned && (
            <Button
              onClick={handleAcceptTask}
              isLoading={updateTaskMutation.isPending}
              className="w-full py-3.5 text-sm font-bold shadow-md rounded-xl bg-brand-500 hover:bg-brand-600 text-white"
              leftIcon={<Check className="w-5 h-5" />}
            >
              Zayavkani qabul qilish
            </Button>
          )}

          {isAssignedToMe && (
            <div className="flex-1 flex items-center gap-3">
              <Button
                onClick={handleMarkAsCompleted}
                isLoading={updateTaskMutation.isPending}
                className="w-full py-3.5 text-sm font-bold shadow-md rounded-xl bg-success-500 hover:bg-success-600 text-white"
                leftIcon={<CheckCircle className="w-5 h-5" />}
              >
                {isRejected ? 'Qayta bajarildi deb belgilash' : 'Bajarildi deb belgilash'}
              </Button>

              <Button
                onClick={() => setIsMessageModalOpen(true)}
                variant="secondary"
                className="py-3.5 text-sm font-bold rounded-xl border"
              >
                Xabar yozish
              </Button>
            </div>
          )}

          {isSolved && (
            <div className="w-full text-center py-2 text-xs font-bold text-success-500 bg-success-50 dark:bg-success-700/20 rounded-xl border border-success-500/20">
              Ushbu zayavka yakunlangan va yopilgan.
            </div>
          )}
        </div>
      </div>

      {/* Screenshot Zoom Modal */}
      <Modal isOpen={isImageModalOpen} onClose={() => setIsImageModalOpen(false)} title="Xatolik Skrinshoti">
        <div className="p-2">
          {task.screenshotUrl && (
            <img src={task.screenshotUrl} alt="Task full attachment" className="w-full rounded-xl object-contain max-h-[80vh]" />
          )}
        </div>
      </Modal>

      {/* Message Modal */}
      <Modal isOpen={isMessageModalOpen} onClose={() => setIsMessageModalOpen(false)} title="Xabar yozish">
        <div className="p-2 space-y-3">
          <textarea
            value={messageText}
            onChange={(e) => setMessageText(e.target.value)}
            rows={4}
            placeholder="Xabar matnini kiriting..."
            className="w-full p-3.5 rounded-xl bg-slate-50 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-xs font-semibold text-slate-900 dark:text-slate-100 focus:outline-none focus:border-brand-500 transition-colors"
          />

          <div className="flex items-center justify-end space-x-2">
            <Button variant="secondary" onClick={() => setIsMessageModalOpen(false)}>
              Bekor qilish
            </Button>
            <Button onClick={handleSendMessage} className="bg-brand-500 text-white">
              Yuborish
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  );
};
