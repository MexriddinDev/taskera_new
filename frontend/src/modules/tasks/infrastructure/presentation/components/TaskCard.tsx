import React from 'react';
import { Link } from 'react-router-dom';
import { Task, TaskPriority, TaskStatus } from '../../../domain/entities/Task';
import { CheckCircle2, Cpu, Code, Copy, AlertTriangle, MapPin, Eye, Lock, Loader2, Star, MessageSquare, RotateCcw } from 'lucide-react';
import { useT } from '@/shared/presentation/i18n/i18n';
import { DeviceBadge } from './DeviceBadge';

interface TaskCardProps {
  task: Task;
  onEdit: (task: Task) => void;
  onDelete: (id: number) => void;
  onToggleStatus: (task: Task) => void;
  /** When true, the card content is hidden/blurred until the task is accepted. */
  blurred?: boolean;
  /** Navbat (queue) ustuni belgisi — blur holatda "Navbatda" pill ko'rsatiladi. */
  queueLabel?: boolean;
  onAccept?: (id: number) => void;
  /**
   * Navbatda faqat ENG TEPADAGI zayavka qabul qilinadi. Qolganlari qulflangan
   * ko'rinishda qoladi va o'z o'rnini ko'rsatadi — shunda eski zayavkalar
   * navbatda qolib ketmaydi.
   */
  canAccept?: boolean;
  /** Navbatdagi o'rni (1 dan boshlab) — qabul qilib bo'lmaydiganlarda ko'rsatiladi. */
  queuePosition?: number;
  /**
   * Xodimda yopilmagan qaytarilgan zayavka bor. Navbat qulflanganini sababi
   * bilan tushuntiramiz — aks holda tugma nega yo'qolgani noma'lum qolardi.
   */
  acceptBlocked?: boolean;
  isAccepting?: boolean;
  /** Baholash ("Baholash & Yopish") — bajarilgan, hali baholanmagan zayavkalar uchun. */
  onRate?: (task: Task) => void;
  /** Reject — bajarilgan, hali baholanmagan zayavkalar uchun. */
  onReject?: (task: Task) => void;
  /**
   * Kuzatuvchi ko'rinishi — zayavka yuborgan foydalanuvchi uchun.
   * "Jarayonga o'tkazish" kabi ijrochi amallari ko'rsatilmaydi, o'rniga
   * zayavkaning holati yoziladi.
   */
  readOnly?: boolean;
}

/**
 * Kartochkadagi holat yozuvi.
 *
 * Rad etilgan zayavka "Jarayonda" deb yoziladi: u xodimning ochiq ishi va
 * "Jarayonda" ustunida turadi. Oddiy jarayondagidan farqi rangda — badge ham,
 * ramka ham qizil (`getStatusBadge` / `getCardBorder`), ostida esa rad etish
 * sababi ko'rinadi.
 */
const STATUS_LABEL_KEY: Record<TaskStatus, string> = {
  todo: 'status.todo',
  in_progress: 'status.inProgress',
  rejected: 'status.inProgress',
  done: 'status.done',
};

export const TaskCard: React.FC<TaskCardProps> = ({
  task,
  onEdit: _onEdit,
  onDelete: _onDelete,
  onToggleStatus,
  blurred = false,
  queueLabel = false,
  onAccept,
  canAccept = true,
  queuePosition,
  acceptBlocked = false,
  isAccepting = false,
  onRate,
  onReject,
  readOnly = false,
}) => {
  const t = useT();
  const [copied, setCopied] = React.useState(false);

  const handleCopyTicket = (e: React.MouseEvent) => {
    e.preventDefault();
    e.stopPropagation();
    navigator.clipboard.writeText(task.ticketNumber);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  const getStatusBadge = (status: TaskStatus) => {
    switch (status) {
      case 'done':
        return 'bg-success-50 text-success-500 border-success-500/20 dark:bg-success-700/30 dark:text-emerald-300';
      case 'in_progress':
        return 'bg-brand-50 text-brand-500 border-brand-500/20 dark:bg-brand-950/50 dark:text-brand-300';
      case 'rejected':
        return 'bg-error-50 text-error-500 border-error-500/20 dark:bg-error-700/30 dark:text-red-300';
      default:
        return 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300';
    }
  };

  /**
   * Kartochka ramkasi zayavka holatining o'z rangida bo'ladi — Kanban ustun
   * sarlavhalari va "Mening vazifalarim" dagi hisob kartalari bilan bir xil
   * rang tizimi: ochiq — ko'k, jarayonda — sariq, rad etilgan — qizil,
   * yechilgan — yashil.
   */
  const getCardBorder = (status: TaskStatus) => {
    switch (status) {
      case 'todo':
        return 'border-2 border-brand-400 dark:border-brand-600';
      case 'in_progress':
        return 'border-2 border-warning-400 dark:border-warning-600';
      case 'rejected':
        return 'border-2 border-error-400 dark:border-error-600';
      case 'done':
        return 'border-2 border-success-400 dark:border-success-600';
      default:
        return 'border border-gray-200 dark:border-gray-700/80';
    }
  };

  /** Pastdagi holat yozuvi: jarayonda — sariq, rad etilgan — qizil. */
  const getStatusPill = (status: TaskStatus) =>
    status === 'rejected'
      ? 'bg-error-50 text-error-600 border-error-300 dark:bg-error-950/60 dark:text-error-300 dark:border-error-800'
      : 'bg-amber-100 text-amber-700 border-amber-300 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800';

  const getPriorityBadge = (priority: TaskPriority) => {
    switch (priority) {
      case 'high':
        return 'bg-error-50 text-error-500 border-error-500/20 dark:bg-error-700/30 dark:text-red-300';
      case 'medium':
        return 'bg-warning-50 text-warning-500 border-warning-500/20 dark:bg-warning-700/30 dark:text-amber-300';
      default:
        return 'bg-slate-100 text-slate-600 border-slate-200 dark:bg-slate-800 dark:text-slate-400';
    }
  };

  // Blurred / locked state — task content is hidden until the admin accepts it.
  if (blurred) {
    return (
      <div className="relative bg-white dark:bg-gray-800/90 rounded-2xl border border-gray-200 dark:border-gray-700/80 shadow-sm overflow-hidden">
        {/* Blurred underlying content (unreadable) */}
        <div className="p-5 select-none pointer-events-none blur-md opacity-70" aria-hidden="true">
          <div className="flex items-center justify-between mb-3">
            <span className="font-bold text-sm text-gray-900 dark:text-gray-100">{task.ticketNumber}</span>
            <span className="text-xs text-gray-400">{task.category}</span>
          </div>
          <div className="h-4 w-3/4 rounded bg-gray-300 dark:bg-gray-600 mb-2" />
          <div className="h-4 w-2/3 rounded bg-gray-200 dark:bg-gray-700 mb-4" />
          <div className="h-3 w-1/2 rounded bg-gray-200 dark:bg-gray-700" />
        </div>

        {/* Lock overlay with Accept button */}
        <div className="absolute inset-0 flex flex-col items-center justify-center bg-white/40 dark:bg-gray-900/40 backdrop-blur-[2px] space-y-3 px-4">
          {queueLabel && (
            <span className="inline-flex items-center px-3 py-1 rounded-full text-[10px] font-black uppercase tracking-wide bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-300 border border-slate-300 dark:border-slate-600">
              <Lock className="w-3 h-3 mr-1" />
              {t('myTasks.inQueue')}
            </span>
          )}
          <div className="flex items-center space-x-2 text-gray-500 dark:text-gray-300">
            <Lock className="w-4 h-4" />
            <span className="text-xs font-bold">{t('taskCard.lockedTitle')}</span>
          </div>
          {canAccept ? (
            <button
              onClick={(e) => {
                e.preventDefault();
                e.stopPropagation();
                onAccept?.(task.id);
              }}
              disabled={isAccepting}
              className="inline-flex items-center space-x-2 px-4 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 active:bg-brand-700 text-white font-extrabold text-xs shadow-md transition-all disabled:opacity-50 cursor-pointer"
            >
              {isAccepting ? <Loader2 className="w-4 h-4 animate-spin" /> : <CheckCircle2 className="w-4 h-4" />}
              <span>{t('taskCard.accept')}</span>
            </button>
          ) : acceptBlocked ? (
            <span className="text-[11px] font-bold text-error-500 dark:text-error-400 text-center leading-relaxed">
              {t('taskCard.acceptBlockedRejected')}
            </span>
          ) : (
            <span className="text-[11px] font-bold text-gray-400 text-center">
              {t('taskCard.queuePosition', { position: String(queuePosition ?? '') })}
            </span>
          )}
        </div>
      </div>
    );
  }

  return (
    <div
      className={`render-optimized group rounded-2xl p-5 shadow-sm hover:shadow-lg transition-all duration-200 flex flex-col justify-between relative overflow-hidden bg-white dark:bg-gray-800/90 ${getCardBorder(task.status)}`}
    >
      <div>
        {/* Ticket Header & Quick Copy */}
        <div className="flex items-start justify-between gap-2 mb-3 pb-3 border-b border-gray-100 dark:border-gray-700/60">
          <div className="flex min-w-0 flex-wrap items-center gap-1.5">
            <span className="font-bold text-sm text-gray-900 dark:text-gray-100">{task.ticketNumber}</span>
            <button
              onClick={handleCopyTicket}
              className="text-gray-400 hover:text-brand-500 p-1 rounded transition-colors"
              title={t('taskCard.copyTitle')}
              aria-label={t('taskCard.copyTitle')}
            >
              <Copy className="w-3.5 h-3.5" />
            </button>
            {copied && <span className="text-[10px] text-success-500 font-medium animate-pulse">{t('taskCard.copied')}</span>}
            <DeviceBadge device={task.device} source={task.source} />
          </div>
          <span className="max-w-[45%] truncate text-right text-xs text-gray-400 font-medium" title={task.category}>{task.category}</span>
        </div>

        {/* Badges: Department & Priority */}
        <div className="flex flex-wrap items-center justify-between gap-2 mb-3">
          <span className="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-brand-50 dark:bg-brand-950/40 text-brand-500 border border-brand-500/20">
            {task.targetDepartment === 'hardware' ? (
              <>
                <Cpu className="w-3.5 h-3.5 mr-1" /> {t('dept.hardware')}
              </>
            ) : (
              <>
                <Code className="w-3.5 h-3.5 mr-1 text-success-500" /> {t('dept.software')}
              </>
            )}
          </span>

          <div className="flex flex-wrap items-center gap-1.5">
            {(task.unreadCommentCount ?? 0) > 0 && (
              <span
                className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-black bg-rose-500 text-white shadow-sm shadow-rose-500/40"
                title={t('taskCard.unreadComments')}
              >
                <MessageSquare className="w-3 h-3" />
                {task.unreadCommentCount}
              </span>
            )}
            <span className={`px-2.5 py-0.5 rounded-full text-[11px] font-semibold border ${getStatusBadge(task.status)}`}>
              {t(STATUS_LABEL_KEY[task.status] ?? `status.${task.status}`)}
            </span>
            <span className={`px-2.5 py-0.5 rounded-full text-[11px] font-semibold border ${getPriorityBadge(task.priority)}`}>
              {t(`priority.${task.priority}`)}
            </span>
          </div>
        </div>

        {/* Task Title & Description */}
        <div className="mb-4">
          <Link
            to={`/task/${task.id}`}
            className="font-bold text-base text-gray-900 dark:text-gray-100 hover:text-brand-500 dark:hover:text-brand-400 transition-colors line-clamp-2 mb-1"
          >
            {task.todo}
          </Link>

          {task.status !== 'done' && task.rejectionReason && (
            <div className="mt-2 p-2.5 rounded-lg bg-error-50 dark:bg-error-700/20 border border-error-500/20 flex items-start space-x-2">
              <AlertTriangle className="w-4 h-4 text-error-500 flex-shrink-0 mt-0.5" />
              <p className="text-xs text-error-500 font-medium line-clamp-2">{task.rejectionReason}</p>
            </div>
          )}

          {task.status === 'done' && task.clientRating != null && task.clientRating > 0 && (
            <div className="mt-2 flex items-center space-x-1" title={t('taskCard.ratedTitle', { rating: task.clientRating })}>
              {[1, 2, 3, 4, 5].map((n) => (
                <Star
                  key={n}
                  className={`w-3.5 h-3.5 ${
                    n <= (task.clientRating ?? 0)
                      ? 'text-amber-400 fill-amber-400'
                      : 'text-gray-300 dark:text-gray-600'
                  }`}
                />
              ))}
            </div>
          )}
        </div>
      </div>

      {/* Footer Info & Actions */}
      <div className="pt-3 border-t border-gray-100 dark:border-gray-700/60 flex flex-wrap items-center justify-between gap-3 text-xs text-gray-500 dark:text-gray-400">
        <div className="flex items-center space-x-3">
          {task.floor && (
            <div className="flex items-center space-x-1">
              <MapPin className="w-3.5 h-3.5 text-gray-400" />
              <span>{task.floor}</span>
            </div>
          )}

          {/* Standalone Circular User Avatar (No text label, hover shows ONLY full name/username) */}
          {task.assignedUserId && (
            <div
              className="relative group/user cursor-pointer"
              title={task.assignedTo || t('taskCard.employee')}
            >
              <img
                src={task.assignedUserAvatar || `https://ui-avatars.com/api/?name=${encodeURIComponent(task.assignedTo || '')}&size=512&bold=true&background=0D8ABC&color=fff`}
                alt={task.assignedTo || t('taskCard.employee')}
                loading="lazy"
                decoding="async"
                className="ml-2 w-8 h-8 rounded-full object-cover border-2 border-white dark:border-slate-700 group-hover/user:scale-110 transition-transform"
              />
            </div>
          )}
        </div>

        <div className="flex min-w-0 flex-wrap items-center justify-end gap-2">
          {/* "Batafsil" — ilgari bu faqat ko'z ikonkasi edi va bosilishi
              bilinmasdi. Endi yozuvi bilan aniq tugma. */}
          <Link
            to={`/task/${task.id}`}
            className="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-gray-600 dark:text-gray-300 text-xs font-bold hover:bg-brand-50 dark:hover:bg-slate-700 hover:text-brand-600 dark:hover:text-brand-400 hover:border-brand-300 dark:hover:border-brand-700 transition-colors"
            title={t('taskCard.viewDetails')}
          >
            <Eye className="w-4 h-4 flex-shrink-0" />
            <span>{t('taskCard.details')}</span>
          </Link>

          {task.status === 'done' && !task.clientRating && onRate && onReject ? (
            <div className="flex items-center space-x-1.5">
              <button
                onClick={(e) => {
                  e.preventDefault();
                  e.stopPropagation();
                  onRate(task);
                }}
                className="inline-flex items-center space-x-1 px-2.5 py-1.5 rounded-xl font-extrabold text-[11px] shadow-sm transition-all cursor-pointer bg-success-500 hover:bg-success-600 text-white"
              >
                <Star className="w-3.5 h-3.5 fill-white" />
                <span>{t('taskCard.rateAndClose')}</span>
              </button>
              <button
                onClick={(e) => {
                  e.preventDefault();
                  e.stopPropagation();
                  onReject(task);
                }}
                className="inline-flex items-center space-x-1 px-2.5 py-1.5 rounded-xl font-extrabold text-[11px] shadow-sm transition-all cursor-pointer bg-error-500 hover:bg-error-600 text-white"
                title={t('taskCard.rejectTitle')}
              >
                <RotateCcw className="w-3.5 h-3.5" />
              </button>
            </div>
          ) : task.status === 'done' ? (
            <span
              className="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-xl font-extrabold text-xs shadow-sm bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300"
            >
              <CheckCircle2 className="w-3.5 h-3.5" />
              <span>{t('status.done')}</span>
            </span>
          ) : readOnly ? (
            /* Zayavka yuborgan foydalanuvchi ijrochi emas — unga faqat
               zayavkasi qaysi holatda ekani ko'rsatiladi. */
            <span className={`inline-flex items-center px-3 py-1.5 rounded-xl font-extrabold text-xs shadow-sm border ${getStatusBadge(task.status)}`}>
              {t(STATUS_LABEL_KEY[task.status] ?? 'status.todo')}
            </span>
          ) : task.status === 'todo' ? (
            <button
              onClick={(e) => {
                e.preventDefault();
                e.stopPropagation();
                onToggleStatus(task);
              }}
              className="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-xl font-extrabold text-xs shadow-sm transition-all cursor-pointer text-white bg-brand-500 hover:bg-brand-600 active:bg-brand-700"
            >
              <span>{t('taskCard.moveToProgress')}</span>
            </button>
          ) : (
            /* Jarayondagi va rad etilgan zayavka kartochkadan yopilmaydi:
               yakunlash uchun yechim izohi majburiy, u esa "Batafsil" ichidagi
               oynada so'raladi. Bu yerda faqat holat ko'rsatiladi — rad
               etilganda yozuvi ham "Jarayonda", faqat rangi qizil. */
            <span className={`inline-flex items-center px-3 py-1.5 rounded-xl font-extrabold text-xs shadow-sm border ${getStatusPill(task.status)}`}>
              <span>{t('status.inProgress')}</span>
            </span>
          )}
        </div>
      </div>
    </div>
  );
};
