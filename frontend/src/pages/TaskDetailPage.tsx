import React, { useState, useEffect, useRef } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { useTaskDetail } from '@/modules/tasks/infrastructure/presentation/hooks/useTaskDetail';
import { useUpdateTask } from '@/modules/tasks/infrastructure/presentation/hooks/useUpdateTask';
import { Button } from '@/shared/presentation/components/Button';
import { Modal } from '@/shared/presentation/components/Modal';
import {
  ArrowLeft,
  ArrowRight,
  User as UserIcon,
  AlertTriangle,
  Laptop,
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
  RotateCcw,
  Clock,
  Paperclip,
  Download,
  PhoneCall,
  PhoneOff,
  Mic,
} from 'lucide-react';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { useAuthStore } from '@/shared/presentation/store/useAuthStore';
import { useCan } from '@/shared/presentation/hooks/useCan';
import { useToastStore } from '@/shared/presentation/store/useToastStore';
import { useT } from '@/shared/presentation/i18n/i18n';
import { DeviceBadge } from '@/modules/tasks/infrastructure/presentation/components/DeviceBadge';
import { SolveTaskModal } from '@/modules/tasks/infrastructure/presentation/components/SolveTaskModal';
import { RateTaskModal } from '@/modules/tasks/infrastructure/presentation/components/RateTaskModal';
import { RejectTaskModal } from '@/modules/tasks/infrastructure/presentation/components/RejectTaskModal';

/** Biriktirma hajmi — 1 MB dan kichigi KB da ko'rsatiladi. */
const formatFileSize = (bytes?: number) =>
  !bytes ? '' : bytes >= 1024 * 1024
    ? `${(bytes / 1024 / 1024).toFixed(1)} MB`
    : `${Math.max(1, Math.round(bytes / 1024))} KB`;

/**
 * Xodim avatari — rasm bo'lmasa ui-avatars orqali bosh harflar chiziladi.
 * Mas'ul xodim kim ekanini bir qarashda bilish uchun.
 */
const UserAvatar: React.FC<{ name?: string | null; src?: string | null; className?: string }> = ({
  name,
  src,
  className = 'w-6 h-6 text-[10px]',
}) => (
  <img
    src={src || `https://ui-avatars.com/api/?name=${encodeURIComponent(name || '?')}&size=256&bold=true&background=0D8ABC&color=fff`}
    alt={name || ''}
    title={name || ''}
    className={`${className} rounded-full object-cover border border-white/70 dark:border-slate-700 flex-shrink-0`}
  />
);

/** Sekundomer o'z state'iga ega — uning har soniyalik tick'i katta detail sahifani qayta chizmaydi. */
const ElapsedTimer: React.FC<{
  startedAtIso: string;
  resolvedAtIso?: string | null;
}> = React.memo(({ startedAtIso, resolvedAtIso }) => {
  const t = useT();
  const [now, setNow] = useState(Date.now());

  useEffect(() => {
    if (resolvedAtIso) return;

    const tick = () => {
      if (document.visibilityState === 'visible') setNow(Date.now());
    };
    const interval = window.setInterval(tick, 1000);
    document.addEventListener('visibilitychange', tick);
    return () => {
      window.clearInterval(interval);
      document.removeEventListener('visibilitychange', tick);
    };
  }, [resolvedAtIso]);

  const end = resolvedAtIso ? new Date(resolvedAtIso).getTime() : now;
  const elapsedMs = Math.max(0, end - new Date(startedAtIso).getTime());
  const seconds = Math.floor(elapsedMs / 1000);
  const days = Math.floor(seconds / 86400);
  const hh = Math.floor((seconds % 86400) / 3600).toString().padStart(2, '0');
  const mm = Math.floor((seconds % 3600) / 60).toString().padStart(2, '0');
  const ss = (seconds % 60).toString().padStart(2, '0');
  const time = `${hh}:${mm}:${ss}`;

  return <>{days > 0 ? t('taskDetail.elapsedDays', { days, time }) : time}</>;
});

/**
 * SLA muddatigacha qolgan vaqt — teskari yuruvchi sekundomer (soniya bilan).
 *
 * Ilgari bu yerda qotib qolgan "29d" turardi: raqam faqat sahifa yangilanganda
 * o'zgarardi va xodim muddat qachon tugashini his qilmasdi. Endi u har soniyada
 * kamayib boradi (00:29:59 -> 00:29:58). Tick komponent ichida — katta detail
 * sahifa qayta chizilmaydi.
 */
const SlaCountdown: React.FC<{ dueAt: string | null }> = React.memo(({ dueAt }) => {
  const t = useT();
  const [now, setNow] = useState(Date.now());

  useEffect(() => {
    const tick = () => {
      if (document.visibilityState === 'visible') setNow(Date.now());
    };
    const interval = window.setInterval(tick, 1000);
    document.addEventListener('visibilitychange', tick);
    return () => {
      window.clearInterval(interval);
      document.removeEventListener('visibilitychange', tick);
    };
  }, []);

  if (!dueAt) return null;

  const seconds = Math.max(0, Math.floor((new Date(dueAt).getTime() - now) / 1000));
  const days = Math.floor(seconds / 86400);
  const hh = Math.floor((seconds % 86400) / 3600).toString().padStart(2, '0');
  const mm = Math.floor((seconds % 3600) / 60).toString().padStart(2, '0');
  const ss = (seconds % 60).toString().padStart(2, '0');
  // Bir soatdan kam qolganda soat ko'rsatilmaydi: "29:58" sekundomerdek
  // o'qiladi. Uzoq muddatda esa to'liq "01:29:58".
  const clock = seconds < 3600 ? `${mm}:${ss}` : `${hh}:${mm}:${ss}`;

  return <span className="font-mono tabular-nums">{days > 0 ? t('taskDetail.elapsedDays', { days, time: clock }) : clock}</span>;
});

/** Ishlash muddati oshgach kechikishni sahifani yangilamasdan minutda oshiradi. */
const SlaOverdueMinutes: React.FC<{
  dueAt: string | null;
  finishedAt: string | null;
  initial: number;
}> = React.memo(({ dueAt, finishedAt, initial }) => {
  const calculate = () => {
    if (!dueAt) return initial;
    const end = finishedAt ? new Date(finishedAt).getTime() : Date.now();
    return Math.max(0, Math.ceil((end - new Date(dueAt).getTime()) / 60000));
  };
  const [minutes, setMinutes] = useState(calculate);

  useEffect(() => {
    setMinutes(calculate());
    if (finishedAt) return;
    const timer = window.setInterval(() => setMinutes(calculate()), 30000);
    return () => window.clearInterval(timer);
  }, [dueAt, finishedAt, initial]);

  return <>{minutes}</>;
});

/** SLA muddati — faqat soat:daqiqa, kun bugungidan farq qilsa sana ham. */
const slaTime = (iso: string): string => {
  const date = new Date(iso);
  const today = new Date();
  const sameDay = date.toDateString() === today.toDateString();
  const time = date.toLocaleTimeString('uz-UZ', { hour: '2-digit', minute: '2-digit' });
  return sameDay ? time : `${date.toLocaleDateString('uz-UZ', { day: '2-digit', month: '2-digit' })} ${time}`;
};

/**
 * Davomiylik to'liq so'z bilan: 7800 → "2 soat 10 daqiqa".
 *
 * Qisqartma ("2s 10d") o'qilmasdi — "d" ni kun deb tushunish ham mumkin edi.
 */
const humanDuration = (seconds: number | null, t: (key: string, params?: Record<string, string | number>) => string): string => {
  const total = Math.max(0, Math.floor((seconds ?? 0) / 60));
  const hours = Math.floor(total / 60);
  const minutes = total % 60;

  if (hours > 0) {
    return minutes > 0
      ? `${t('taskDetail.durationHours', { count: hours })} ${t('taskDetail.durationMinutes', { count: minutes })}`
      : t('taskDetail.durationHours', { count: hours });
  }

  return t('taskDetail.durationMinutes', { count: minutes });
};

export const TaskDetailPage: React.FC = () => {
  const t = useT();
  const { id } = useParams<{ id: string }>();
  const taskId = Number(id);
  const navigate = useNavigate();
  const currentUser = useAuthStore((s) => s.user);
  const { can } = useCan();
  const toast = useToastStore();

  // Cisco Finesse qo'ng'irog'i — raqam backendda zayavkadan olinadi.
  const [isCalling, setIsCalling] = useState(false);
  // Qo'ng'iroq boshlangach tugma "Tugatish"ga almashadi. Holat faqat shu
  // sahifada saqlanadi: Finesse'da qo'ng'iroq telefon go'shagidan ham
  // tugatilishi mumkin, shuning uchun DROP "faol qo'ng'iroq yo'q" desa ham
  // holat tiklanadi va tugma qayta "Qo'ng'iroq" bo'ladi.
  const [isCallActive, setIsCallActive] = useState(false);

  // Suhbat yozuvi. DIQQAT: bu BRAUZER MIKROFONI, ya'ni telefon liniyasi emas —
  // operator gapirgani yoziladi. Qo'ng'iroq boshlanganda yozuv boshlanadi,
  // tugatilganda to'xtaydi va zayavkaga biriktirma bo'lib yuklanadi.
  const recorderRef = useRef<MediaRecorder | null>(null);
  const chunksRef = useRef<Blob[]>([]);

  const startRecording = async () => {
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      const recorder = new MediaRecorder(stream);
      chunksRef.current = [];
      recorder.ondataavailable = (e) => {
        if (e.data.size > 0) chunksRef.current.push(e.data);
      };
      recorder.start();
      recorderRef.current = recorder;
    } catch {
      // Mikrofonga ruxsat berilmasa qo'ng'iroqning o'zi to'xtamasligi kerak.
      toast.error(t('taskDetail.micDenied'));
    }
  };

  const stopRecordingAndUpload = async (ticketId: number) => {
    const recorder = recorderRef.current;
    if (!recorder || recorder.state === 'inactive') return;

    const blob: Blob = await new Promise((resolve) => {
      recorder.onstop = () => resolve(new Blob(chunksRef.current, { type: 'audio/webm' }));
      recorder.stop();
    });

    recorder.stream.getTracks().forEach((track) => track.stop());
    recorderRef.current = null;

    if (blob.size === 0) return;

    // Maxsus endpoint: yozuvni `call_recording` deb belgilaydi va shu bilan
    // uni murojaatchidan yashiradi. Umumiy /attachments/upload buni qila olmaydi.
    const form = new FormData();
    form.append('file', blob, `call-${ticketId}-${Date.now()}.webm`);

    try {
      await axiosClient.post(`/tickets/${ticketId}/call-recording`, form);
      toast.success(t('taskDetail.recordingSaved'));
      refetch();
    } catch (error: any) {
      // Xatoni yutmaymiz — nima bo'lganini bilmasa, muammoni topib bo'lmaydi.
      toast.error(error?.response?.data?.message || t('taskDetail.recordingError'));
    }
  };

  const handleCall = async () => {
    if (!task) return;

    setIsCalling(true);
    try {
      await axiosClient.post(`/tickets/${task.id}/call`);
      setIsCallActive(true);
      toast.success(t('taskDetail.callStarted'));
      await startRecording();
    } catch (error: any) {
      toast.error(error?.response?.data?.message || t('taskDetail.callError'));
    } finally {
      setIsCalling(false);
    }
  };

  const handleDrop = async () => {
    if (!task) return;

    setIsCalling(true);
    try {
      await axiosClient.post('/finesse/drop');
      toast.success(t('taskDetail.callEnded'));
    } catch (error: any) {
      toast.error(error?.response?.data?.message || t('taskDetail.dropError'));
    } finally {
      // Yozuv qo'ng'iroq qanday tugaganidan qat'i nazar saqlanadi — DROP xato
      // bersa ham (go'shak telefondan qo'yilgan bo'lishi mumkin) suhbat bo'lgan.
      await stopRecordingAndUpload(task.id);
      setIsCallActive(false);
      setIsCalling(false);
    }
  };

  // Solution / Review states
  // Yakunlash yechim izohi bilan alohida oynada so'raladi (majburiy).
  const [isSolveOpen, setIsSolveOpen] = useState(false);
  const [isRateOpen, setIsRateOpen] = useState(false);
  const [isReturnOpen, setIsReturnOpen] = useState(false);

  // Image zoom modal state
  const [zoomImageUrl, setZoomImageUrl] = useState<string | null>(null);
  const [zoomScale, setZoomScale] = useState(1);
  const [panOffset, setPanOffset] = useState({ x: 0, y: 0 });
  const zoomContainerRef = useRef<HTMLDivElement | null>(null);
  const dragState = useRef<{ startX: number; startY: number; panX: number; panY: number; dragging: boolean; active: boolean }>({
    startX: 0,
    startY: 0,
    panX: 0,
    panY: 0,
    dragging: false,
    // Sichqoncha tugmasi bosilgan holatda turibdimi. `startX` ga qarab
    // hukm qilish ishonchsiz edi: 0 ham haqiqiy koordinata bo'lishi mumkin.
    active: false,
  });

  // Audio manzilini bir marta qulflab qo'yamiz.
  //
  // Sahifa har 5 soniyada refetch qiladi va imzolangan havola vaqti-vaqti bilan
  // yangilanadi. <audio src> o'zgarsa brauzer faylni qaytadan yuklaydi va ijro
  // uzilib qoladi. Shu sabab birinchi kelgan manzil zayavka uchun saqlanadi.
  //
  // E'lon shu yerda — pastroqda `isLoading` / `isError` uchun erta return'lar
  // bor, hook esa har renderda bir xil tartibda chaqirilishi shart.
  const stableAudioUrlRef = useRef<{ taskId: number; url: string } | null>(null);

  const openZoom = (url: string) => {
    setZoomScale(1);
    setPanOffset({ x: 0, y: 0 });
    setZoomImageUrl(url);
  };

  // G'ildirak bilan zoom. React'ning `onWheel` i passiv rejimda biriktiriladi
  // va `preventDefault()` ishlamaydi — natijada sahifa orqa fonda siljib
  // ketardi. Shuning uchun native listener, `passive: false` bilan.
  useEffect(() => {
    const container = zoomContainerRef.current;
    if (!container || !zoomImageUrl) return;

    const onWheel = (event: WheelEvent) => {
      event.preventDefault();
      zoomAt(event.clientX, event.clientY, event.deltaY < 0 ? 1.15 : 1 / 1.15);
    };

    container.addEventListener('wheel', onWheel, { passive: false });

    return () => container.removeEventListener('wheel', onWheel);
    // zoomScale/panOffset zoomAt ichida o'qiladi — listener ular bilan yangilanadi.
  }, [zoomImageUrl, zoomScale, panOffset]);

  // Escape — oynani yopadi, bu lightbox uchun kutilgan xatti-harakat.
  useEffect(() => {
    if (!zoomImageUrl) return;

    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') setZoomImageUrl(null);
    };

    window.addEventListener('keydown', onKey);

    return () => window.removeEventListener('keydown', onKey);
  }, [zoomImageUrl]);

  // Rasmni tortib surish. Pointer Capture ishlatiladi: tugma konteynerdan
  // TASHQARIDA qo'yib yuborilsa ham `pointerup` shu elementga keladi.
  // Ilgari `mouseup` faqat konteyner ustida ushlanardi va bir marta bosib
  // sirtga chiqilsa "bosilgan" holat qolib ketardi — rasm sichqonchaga
  // yopishib, u bilan birga yurardi.
  const handlePointerDown = (e: React.PointerEvent) => {
    if (e.button !== 0) return;
    e.currentTarget.setPointerCapture(e.pointerId);
    dragState.current = {
      startX: e.clientX,
      startY: e.clientY,
      panX: panOffset.x,
      panY: panOffset.y,
      dragging: false,
      active: true,
    };
  };

  const handlePointerMove = (e: React.PointerEvent) => {
    const state = dragState.current;
    if (!state.active) return;

    const dx = e.clientX - state.startX;
    const dy = e.clientY - state.startY;

    // Kichik siljish — bu bosish, surish emas (oynani yopish uchun).
    if (!state.dragging && Math.abs(dx) + Math.abs(dy) > 4) {
      state.dragging = true;
    }

    if (state.dragging) {
      setPanOffset({ x: state.panX + dx, y: state.panY + dy });
    }
  };

  const handlePointerUp = (e: React.PointerEvent) => {
    if (e.currentTarget.hasPointerCapture(e.pointerId)) {
      e.currentTarget.releasePointerCapture(e.pointerId);
    }
    dragState.current.active = false;
  };

  const handleZoomContainerClick = () => {
    // Surishdan keyin oyna yopilmaydi — faqat toza bosishda.
    if (dragState.current.dragging) {
      dragState.current.dragging = false;
      return;
    }
    setZoomImageUrl(null);
  };

  /** Ikki marta bosish: 1x <-> 2x, bosilgan nuqtani markazda ushlab. */
  const handleDoubleClick = (e: React.MouseEvent) => {
    e.stopPropagation();
    if (zoomScale > 1) {
      setZoomScale(1);
      setPanOffset({ x: 0, y: 0 });

      return;
    }
    zoomAt(e.clientX, e.clientY, 2 / zoomScale);
  };

  /**
   * KURSOR turgan nuqtaga qarab kattalashtirish — Photoshop'dagidek.
   * Oddiy `scale` markazdan kattalashtiradi va kerakli joy ekrandan chiqib
   * ketadi; bu yerda kursor ostidagi nuqta joyida qoladi.
   */
  const zoomAt = (clientX: number, clientY: number, factor: number) => {
    const container = zoomContainerRef.current;
    if (!container) return;

    const rect = container.getBoundingClientRect();
    // Konteyner markaziga nisbatan koordinata (transform ham markazdan ishlaydi).
    const cx = clientX - rect.left - rect.width / 2;
    const cy = clientY - rect.top - rect.height / 2;

    const next = Math.min(Math.max(zoomScale * factor, 0.25), 8);
    const ratio = next / zoomScale;

    setPanOffset({
      x: cx - (cx - panOffset.x) * ratio,
      y: cy - (cy - panOffset.y) * ratio,
    });
    setZoomScale(next);
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
  const [messageError, setMessageError] = useState<string | null>(null);
  const [isSendingMessage, setIsSendingMessage] = useState(false);

  // Assign / Reassign modal state
  const [isAssignModalOpen, setIsAssignModalOpen] = useState(false);
  const [staffList, setStaffList] = useState<Array<{ id: number; name: string; username: string; image?: string }>>([]);
  const [selectedAssigneeId, setSelectedAssigneeId] = useState<number | null>(null);
  const [reassignReason, setReassignReason] = useState('');
  const [isAssigning, setIsAssigning] = useState(false);

  const { data: task, isLoading, isError, error, refetch } = useTaskDetail(taskId);
  const updateTaskMutation = useUpdateTask();

  // Biriktirish oynasi uchun xodimlar ro'yxati.
  // Ilgari bu /tickets/monitoring dan olinardi, lekin u javobda `employees`
  // kalitini qaytarmaydi — shuning uchun ro'yxat doim bo'sh bo'lib, hech kimni
  // tanlab bo'lmasdi.
  const fetchStaffList = () => {
    axiosClient.get('/tickets/assignable-staff')
      .then((res) => {
        const list = res.data?.data || [];
        setStaffList(list.map((e: any) => ({
          id: e.id,
          name: e.name || e.username,
          username: e.username,
          image: e.image,
        })));
      })
      .catch(() => {});
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

    setIsSendingMessage(true);
    setMessageError(null);
    try {
      await axiosClient.post(`/tickets/${task.id}/comments`, { body: messageText });
      setMessageText('');
      setIsMessageModalOpen(false);
      refetch();
    } catch (e: any) {
      // Ilgari xato faqat console.error ga yozilardi — foydalanuvchi uchun
      // tugma "ishlamayotgandek" ko'rinardi. Endi sabab oynada ko'rsatiladi.
      const msg = e?.response?.data?.message || e?.message || t('common.errorGeneric');
      setMessageError(msg);
      console.error('Xabar yuborilmadi', e);
    } finally {
      setIsSendingMessage(false);
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
        reason: reassignReason || t('taskDetail.defaultAssignReason'),
      });
      setIsAssignModalOpen(false);
      setReassignReason('');
      refetch();
    } catch (e: any) {
      // Ilgari xato faqat konsolga chiqardi: foydalanuvchi uchun tugma
      // "hech narsa qilmayotgandek" ko'rinardi (huquq yo'q, sabab majburiy,
      // zayavka yopilgan — hammasi jimgina yutilardi).
      const message = e?.response?.data?.errors?.reason?.[0]
        || e?.response?.data?.message
        || t('taskDetail.assignFailed');
      toast.error(message);
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
        <h2 className="text-xl font-black text-slate-900 dark:text-slate-100 mb-2">{t('taskDetail.notFoundTitle')}</h2>
        <p className="text-sm text-slate-500 dark:text-slate-400 mb-6">
          {error?.message || t('taskDetail.notFoundDesc')}
        </p>
        <Button variant="secondary" onClick={() => navigate('/dashboard')} leftIcon={<ArrowLeft className="w-4 h-4" />}>
          {t('taskDetail.backToDashboard')}
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
    { key: 'todo', label: t('taskDetail.stepTodo') },
    { key: 'in_progress', label: t('taskDetail.stepInProgress') },
    { key: 'stopped', label: t('taskDetail.stepRejected') },
    { key: 'done', label: t('taskDetail.stepDone') },
  ];

  // Active step index calculation
  const currentStepIndex = isSolved ? 3 : isRejected ? 2 : isInProgress ? 1 : 0;

  // Zayavka shu foydalanuvchiniki bo'lsa, u bajarilgan ishni baholay yoki
  // qaytara oladi. Ilgari bu faqat "Mening zayavkalarim" ro'yxatida bor edi —
  // zayavka ichiga kirgan odam hech narsa qila olmasdi.
  const isRequester = Boolean(
    task.requesterUserId && currentUser?.id && task.requesterUserId === currentUser.id
  );
  const canRateOrReturn = isSolved && !task.clientRating && isRequester;

  // Staff-only actions: assignment / takeover
  const isStaffUser = Boolean(currentUser?.isStaff) || currentUser?.username === 'superadmin' || currentUser?.username === 'admin';

  // Amallar endi o'z huquqiga bog'langan: navbatni ko'rish (`tickets.view`)
  // biriktirish yoki holat o'zgartirish huquqini bermaydi — backend ham
  // aynan shunday tekshiradi.
  //
  // Egasiz zayavkani O'ZIGA olish ishlashning bir qismi (`tickets.transition`),
  // boshqa xodimga biriktirish esa dispetcherlik amali (`tickets.assign`).
  const canAssignTickets = isStaffUser && can(['tickets.assign']);
  const canTransitionTickets = isStaffUser && can(['tickets.transition']);
  const canTakeTickets = canAssignTickets || canTransitionTickets;

  // Boshqa xodimda turgan zayavka ustida ishlab bo'lmaydi — avval uni o'ziga
  // olish kerak. Backend ham aynan shunday javob beradi (403).
  const isAssignee = Boolean(task.assignedUserId && currentUser?.id && task.assignedUserId === currentUser.id);
  const isTicketFree = !task.assignedUserId;
  const canWorkOnTicket = canTransitionTickets && (isAssignee || isTicketFree);
  const isTakingOverSomeoneElse = Boolean(task.assignedUserId && task.assignedUserId !== currentUser?.id);

  // Zayavka yopilgan (bajarilgan yoki rad etilgan) bo'lsa — mas'ul xodimni
  // o'zgartirish qulflanadi.
  const isTaskClosed = isSolved || isRejected;

  // Yozishma esa faqat zayavka BAJARILGANDA yopiladi. Rad etilgan zayavka
  // yakunlangan emas — u xodimning ochiq ishi va "Jarayonda" ustunida turadi;
  // ilgari `isTaskClosed` uni ham yopiq deb hisoblab, qaytarilgan zayavkaning
  // yozishmasini o'chirib qo'yardi — aynan shu paytda tomonlar sababni
  // muhokama qilishi kerak edi.
  const isChatOpen = !isSolved;

  // Yozishmaga FAQAT xodimlar yozadi. Oddiy foydalanuvchi (zayavka muallifi)
  // yozishmani o'qiydi, lekin xabar qo'sha olmaydi — backendda ham shunday
  // (CommentController::store).
  // Begona zayavka yozishmasiga faqat dispetcher (admin/superadmin) yozadi.
  const canWriteInChat = isChatOpen && isStaffUser && (isAssignee || isTicketFree || canAssignTickets);

  /** Yozishmada shu turdagi yozuv bormi (yechim / rad etish sababi). */
  const hasThreadEntry = (kind: 'solution' | 'rejection') =>
    (task.comments ?? []).some((c) => c.kind === kind);

  const assignmentHistory = task.assignmentHistory ?? [];

  // Chat ko'rinishi: o'z xabaring o'ngda, boshqalarniki chapda.
  // Pufakchalar butun kenglikni egallamaydi — yarmidan sal ko'p.
  const isOwnAuthor = (author?: string | null): boolean =>
    Boolean(author && currentUser?.username && author.toLowerCase() === currentUser.username.toLowerCase());

  /** Xabar muallifi zayavka egasimi (murojaatchimi). */
  const isRequesterAuthor = (author?: string | null): boolean =>
    Boolean(author && task.requesterUsername && author.toLowerCase() === task.requesterUsername.toLowerCase());

  // Yozishma ishtirokchisi — murojaatchi yoki mas'ul xodim.
  //
  // Chetdan kuzatuvchi (admin/superadmin) uchun "o'z xabari" degan tushuncha
  // yo'q, shu sabab uning ekranida barcha pufakchalar chap tomonda tizilib
  // qolardi va suhbat o'qilmasdi. Bunday kuzatuvchiga yozishma XODIM
  // ko'zi bilan ko'rsatiladi: murojaatchi chapda, xodim o'ngda.
  const isChatParticipant = isRequester || isAssignee;

  const alignRight = (author?: string | null): boolean =>
    isChatParticipant ? isOwnAuthor(author) : ! isRequesterAuthor(author);

  const bubbleRow = (own: boolean) => `flex ${own ? 'justify-end' : 'justify-start'}`;
  const bubbleWidth = 'w-full max-w-[96%] sm:max-w-[78%]';

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
  // Rasm/video/ovozdan boshqa biriktirmalar (zip, pdf, docx...). Ilgari bular
  // hech qayerda chizilmagani uchun zayavkaga yuklangani bilan ko'rinmasdi.
  const filesToShow = mediaList.filter((m) => m.type === 'file');
  // Qo'ng'iroq yozuvlari — backend ularni faqat xodimlarga yuboradi
  // (murojaatchida `media` ro'yxatida umuman bo'lmaydi).
  const callRecordings = mediaList.filter((m) => m.kind === 'call_recording');
  // Ref YUQORIDA e'lon qilingan (hooklar shartsiz chaqirilishi shart) —
  // bu yerda faqat qiymatini yangilaymiz.
  if (task.audioUrl && stableAudioUrlRef.current?.taskId !== task.id) {
    stableAudioUrlRef.current = { taskId: task.id, url: task.audioUrl };
  }
  const stableAudioUrl = stableAudioUrlRef.current?.url ?? task.audioUrl;

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
          {t('taskDetail.backToDashboard')}
        </Link>

      </div>

      {/* Guruhga biriktirilgan faol SLA va real vaqtdagi kechikish. */}
      {task.sla && task.sla.length > 0 && (
        <div className="bg-white dark:bg-slate-900 rounded-3xl p-4 sm:p-5 border border-slate-200 dark:border-slate-800 shadow-sm space-y-2.5">
          <div className="flex items-center justify-between gap-3 border-b border-slate-100 dark:border-slate-800 pb-2.5">
            <div className="flex items-center gap-2 min-w-0">
              <Clock className="w-4 h-4 text-brand-500 dark:text-brand-400 flex-shrink-0" />
              <span className="text-xs font-black uppercase tracking-wider text-slate-500 dark:text-slate-400 truncate">
                SLA · {task.sla[0]?.slaName} · {task.sla[0]?.teamName}
              </span>
            </div>

            {/* Murojaatchiga qo'ng'iroq — SLA qatorining o'ng tomonida.
                Telefon raqami yo'q bo'lsa tugma YASHIRILMAYDI, o'chirilgan
                holatda sababi bilan qoladi: ilgari jimgina yo'qolardi va
                operator qo'ng'iroq umuman yo'q deb o'ylardi. */}
            <div className="flex flex-col items-end gap-1 flex-shrink-0">
              <button
                type="button"
                onClick={isCallActive ? handleDrop : handleCall}
                disabled={isCalling || !task.initiatorPhone}
                title={
                  !task.initiatorPhone
                    ? t('taskDetail.noPhoneHint')
                    : isCallActive
                      ? t('taskDetail.dropTitle')
                      : t('taskDetail.callTitle')
                }
                className={`inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-white text-xs font-extrabold shadow-md transition-all disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer border-none ${
                  !task.initiatorPhone
                    ? 'bg-slate-400 dark:bg-slate-600'
                    : isCallActive
                      ? 'bg-rose-600 hover:bg-rose-500 active:bg-rose-700'
                      : 'bg-emerald-600 hover:bg-emerald-500 active:bg-emerald-700'
                }`}
              >
                {isCallActive ? (
                  <PhoneOff className={`w-4 h-4 flex-shrink-0 ${isCalling ? 'animate-pulse' : ''}`} />
                ) : (
                  <PhoneCall className={`w-4 h-4 flex-shrink-0 ${isCalling ? 'animate-pulse' : ''}`} />
                )}
                <span className="hidden sm:inline">
                  {!task.initiatorPhone
                    ? t('taskDetail.noPhone')
                    : isCalling
                      ? t('taskDetail.calling')
                      : isCallActive
                        ? t('taskDetail.dropTitle')
                        : t('taskDetail.callTitle')}
                </span>
              </button>

              {/* Yozuv brauzer mikrofonidan olinadi — suhbatdoshning ovozi
                  telefon go'shagida bo'lgani uchun unga tushmaydi. Buni
                  aytib qo'yish shart: aks holda operator suhbat to'liq
                  yozilyapti deb o'ylaydi. Qo'ng'iroq imkonsiz bo'lsa bu
                  eslatma ham keraksiz. */}
              {task.initiatorPhone && (
                <span className="inline-flex items-center gap-1 text-[10px] font-semibold text-slate-400 dark:text-slate-500 whitespace-nowrap">
                  <Mic className="w-3 h-3 flex-shrink-0" />
                  {t('taskDetail.recordsOperatorOnly')}
                </span>
              )}
            </div>
          </div>

          {task.sla.map((stage) => {
            const tone =
              stage.status === 'BREACHED'
                ? 'text-rose-600 dark:text-rose-400'
                : stage.status === 'MET'
                  ? 'text-emerald-600 dark:text-emerald-400'
                  : stage.status === 'RUNNING'
                    ? 'text-amber-600 dark:text-amber-400'
                    : 'text-slate-400 dark:text-slate-500';

            const mark = stage.status === 'MET' ? '✓' : stage.status === 'BREACHED' ? '✕' : stage.status === 'RUNNING' ? '⏱' : '·';

            // Bosqichlar faqat ikkita: qabul qilish va ishlash (sla_rules).
            const label = t(stage.key === 'accept' ? 'slaBlock.accept' : 'slaBlock.work');

            return (
              <div key={stage.key} className="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
                <span className={`w-4 text-center font-black ${tone}`}>{mark}</span>
                <span className="font-extrabold text-slate-800 dark:text-slate-100 min-w-[110px]">{label}</span>
                <span className="font-mono text-slate-500 dark:text-slate-400">
                  {stage.dueAt ? t('slaBlock.until', { time: slaTime(stage.dueAt) }) : '—'}
                </span>
                <span className={`font-bold ${tone}`}>
                  {stage.status === 'MET' && t('slaBlock.met')}
                  {stage.status === 'BREACHED' && t('slaBlock.breached')}
                  {stage.status === 'RUNNING' && (
                    <><SlaCountdown dueAt={stage.dueAt} /> {t('slaBlock.remainingSuffix')}</>
                  )}
                  {stage.status === 'WAITING' && t('slaBlock.waiting')}
                </span>
                {stage.key === 'work' && stage.status === 'BREACHED' && (
                  <span className="font-black text-rose-600 dark:text-rose-400">
                    Kechikish: <SlaOverdueMinutes dueAt={stage.dueAt} finishedAt={stage.finishedAt} initial={stage.overdueMinutes} /> daqiqa
                  </span>
                )}
              </div>
            );
          })}
        </div>
      )}

      {/* 1. SERIOUS ENTERPRISE HEADER BANNER (Light & Dark Theme Compatible) */}
      <div className="bg-white dark:bg-slate-900 text-slate-900 dark:text-white rounded-3xl p-5 sm:p-6 shadow-md flex flex-wrap items-center justify-between gap-4 border border-slate-200 dark:border-slate-800">
        <div className="flex items-center space-x-4">
          <div className="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-slate-800 text-brand-600 dark:text-brand-400 flex items-center justify-center border border-slate-200 dark:border-slate-700 flex-shrink-0 shadow-xs">
            <UserCheck className="w-6 h-6" />
          </div>
          <div>
            <div className="flex items-center space-x-3">
              <span className="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{t('taskDetail.statusLabel')}:</span>
              <span className={`px-3 py-1 rounded-lg text-xs font-black tracking-wider uppercase border ${
                isSolved
                  ? 'bg-emerald-100 dark:bg-emerald-950/80 text-emerald-800 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800'
                  : isRejected
                  ? 'bg-rose-100 dark:bg-rose-950/80 text-rose-800 dark:text-rose-300 border-rose-300 dark:border-rose-800'
                  : isInProgress
                  ? 'bg-amber-100 dark:bg-amber-950/80 text-amber-800 dark:text-amber-300 border-amber-300 dark:border-amber-800'
                  : 'bg-slate-100 dark:bg-slate-700 text-slate-800 dark:text-slate-200 border-slate-300 dark:border-slate-600'
              }`}>
                {isSolved ? t('taskDetail.badgeDone') : isRejected ? t('taskDetail.badgeRejectedStopped') : isInProgress ? t('taskDetail.badgeInProgress') : t('taskDetail.badgeTodo')}
              </span>
            </div>
            <div className="flex flex-wrap items-center gap-x-6 gap-y-1 mt-2 text-xs text-slate-600 dark:text-slate-300">
              <span>{t('taskDetail.receivedDate')}: <strong className="text-slate-900 dark:text-white font-mono">{task.createdAt}</strong></span>
              {task.startedAt && (
                <span>{t('taskDetail.startedDate')}: <strong className="text-slate-900 dark:text-white font-mono">{task.startedAt}</strong></span>
              )}
              <span className="flex items-center space-x-2">
                <span className="text-slate-500 dark:text-slate-400">{t('taskDetail.responsibleEmployee')}:</span>
                {task.assignedTo && <UserAvatar name={task.assignedTo} src={task.assignedUserAvatar} className="w-6 h-6 text-[10px]" />}
                <strong className="text-emerald-600 dark:text-emerald-400 font-extrabold">{task.assignedTo || t('rateTask.unassigned')}</strong>
                {/* Pencil Edit Icon next to Responsible Employee (staff only).
                    Zayavka yopilgach o'zgartirishga umuman ruxsat yo'q. */}
                {canAssignTickets && !isTaskClosed && (
                  <button
                    onClick={() => { setIsAssignModalOpen(true); fetchStaffList(); }}
                    className="p-1.5 rounded-lg bg-slate-100 dark:bg-slate-700 hover:bg-amber-500 text-amber-600 dark:text-amber-300 hover:text-white transition-all cursor-pointer border border-slate-200 dark:border-slate-600 shadow-xs ml-1 flex items-center"
                    title={t('taskDetail.assignReassignTitle')}
                  >
                    <Pencil className="w-3.5 h-3.5" />
                  </button>
                )}
              </span>
            </div>

            {/* Mas'ul xodim o'zgarishlari: kimdan kimga, qachon, kim o'tkazgan */}
            {assignmentHistory.length > 0 && (
              <div className="mt-3 space-y-1.5">
                <span className="text-[10px] font-black uppercase tracking-wider text-slate-400 dark:text-slate-500 block">
                  {t('taskDetail.assignmentHistoryTitle')}
                </span>
                {assignmentHistory.map((change) => (
                  <div
                    key={change.id}
                    className="flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-slate-600 dark:text-slate-300"
                  >
                    <span className="font-mono text-slate-400 dark:text-slate-500">{change.createdAt}</span>
                    {change.fromUser ? (
                      <span className="flex items-center gap-1.5">
                        <UserAvatar name={change.fromUser} src={change.fromUserAvatar} className="w-5 h-5 text-[9px]" />
                        <span className="font-bold">{change.fromUser}</span>
                      </span>
                    ) : (
                      <span className="italic text-slate-400">{t('rateTask.unassigned')}</span>
                    )}
                    <ArrowRight className="w-3 h-3 text-slate-400 flex-shrink-0" />
                    <span className="flex items-center gap-1.5">
                      <UserAvatar name={change.toUser} src={change.toUserAvatar} className="w-5 h-5 text-[9px]" />
                      <span className="font-bold text-emerald-600 dark:text-emerald-400">{change.toUser}</span>
                    </span>
                    {change.changedBy && (
                      <span className="text-slate-400 dark:text-slate-500">
                        ({t('taskDetail.assignmentChangedBy')}: {change.changedBy})
                      </span>
                    )}
                    {typeof change.spentMinutes === 'number' && change.spentMinutes > 0 && (
                      <span
                        className="inline-flex items-center gap-1 rounded-full px-2 py-0.5 bg-amber-50 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 font-bold"
                        title={t('taskDetail.previousSpentHint')}
                      >
                        <Clock className="w-3 h-3" />
                        {t('taskDetail.previousSpent', { time: humanDuration(change.spentMinutes * 60, t) })}
                      </span>
                    )}
                    {change.reason && (
                      <span className="text-slate-500 dark:text-slate-400 italic truncate max-w-[260px]" title={change.reason}>
                        — {change.reason}
                      </span>
                    )}
                  </div>
                ))}
              </div>
            )}
          </div>
        </div>

        {/* Live timer: qabul qilinganidan beri o'tgan vaqt (katta sariq card) */}
        {task.startedAtIso && (
          <div className="px-6 py-3 rounded-2xl bg-gradient-to-br from-amber-400 via-amber-500 to-orange-500 text-white shadow-lg shadow-amber-500/30 border border-amber-300 dark:border-amber-400/70 flex-shrink-0">
            <span className="block text-2xl sm:text-3xl font-black font-mono tabular-nums tracking-tight drop-shadow-sm">
              <ElapsedTimer
                startedAtIso={task.startedAtIso}
                resolvedAtIso={isSolved ? task.resolvedAtIso : null}
              />
            </span>
          </div>
        )}

        <div className="flex items-center space-x-3">
          <span className="px-3.5 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-100 border border-slate-200 dark:border-slate-700 text-xs font-black uppercase tracking-wider shadow-xs">
            {t('taskDetail.priorityValue', {
              priority: t(`priority.${['low', 'medium', 'high'].includes(task.priority) ? task.priority : 'medium'}`),
            })}
          </span>
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
          <div className="bg-white dark:bg-slate-900 rounded-3xl p-4 sm:p-5 border border-slate-200 dark:border-slate-800 shadow-sm space-y-3 text-slate-900 dark:text-slate-100">
            <div className="flex items-center justify-between border-b border-slate-100 dark:border-slate-800 pb-2.5">
              <span className="text-xs font-black uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center space-x-2">
                <MessageSquare className="w-4 h-4 text-brand-500 dark:text-brand-400" />
                <span>{t('taskDetail.chatBoxTitle')}</span>
              </span>
              <span className="text-xs font-black text-brand-600 dark:text-brand-400 font-mono">#{task.ticketNumber}</span>
            </div>

            {/* Initiator Message Bubble (Theme-Responsive Card).
                Murojaatchi o'z zayavkasini ochganda so'rov matni — uning
                o'z xabari, shuning uchun o'ng tomonda turadi; xodimga esa
                suhbatdoshning xabari sifatida chapda ko'rinadi. */}
            <div className={bubbleRow(isRequester)}>
            <div className={`${bubbleWidth} p-4 rounded-2xl bg-white dark:bg-slate-800/90 text-slate-900 dark:text-white border border-slate-200 dark:border-slate-700 space-y-2 shadow-sm`}>
              <div className="flex items-center justify-between text-xs text-slate-500 dark:text-slate-300 border-b border-slate-100 dark:border-slate-700 pb-2">
                <span className="font-extrabold text-slate-900 dark:text-white flex items-center space-x-2.5 text-sm">
                  <UserAvatar name={task.initiatorName} src={task.initiatorAvatar} className="w-7 h-7 text-[10px]" />
                  <span>{task.initiatorName || t('taskDetail.initiator')} ({t('taskDetail.requestMessageLabel')})</span>
                </span>
                <span className="font-mono text-xs text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-900 px-3 py-1 rounded-lg border border-slate-200 dark:border-slate-700">{task.createdAt}</span>
              </div>
              <p className="text-[13px] font-bold text-slate-900 dark:text-slate-100 leading-relaxed pt-0.5 whitespace-pre-wrap break-words">
                {cleanTodoText || task.todo}
              </p>
              {cleanDescriptionText && cleanDescriptionText !== cleanTodoText && (
                <p className="text-[11px] text-slate-600 dark:text-slate-300 pt-2 border-t border-slate-100 dark:border-slate-700/60 whitespace-pre-wrap break-words">
                  {cleanDescriptionText}
                </p>
              )}
            </div>
            </div>

            {/* Dynamic Comments & Chat Thread */}
            {task.comments && task.comments.length > 0 && (
              <div className="space-y-2 pt-2 border-t border-slate-200 dark:border-slate-800">
                <span className="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider block">{t('taskDetail.commentsHistory', { count: task.comments.length })}:</span>
                {task.comments.map((comment) => {
                  const isNew = comment.isRead === false;
                  const isOwn = alignRight(comment.authorUsername ?? comment.author);
                  // Yechim va rad etish sababi yozishmada oddiy izohdan
                  // ajralib turadi — yashil va qizil ramkada.
                  // Yashil rang FAQAT yechimni bildiradi. Ilgari o'qilmagan
                  // oddiy izoh ham yashil chiqardi va bir necha soniyadan keyin
                  // kulrangga aylanardi — xabar go'yo "yopilgan"dek ko'rinardi.
                  const bubbleTone =
                    comment.kind === 'solution'
                      ? 'bg-emerald-50 dark:bg-emerald-950/60 border-2 border-emerald-500 dark:border-emerald-600'
                      : comment.kind === 'rejection'
                        ? 'bg-rose-50 dark:bg-rose-950/60 border-2 border-rose-500 dark:border-rose-700'
                        : comment.kind === 'rating'
                          ? 'bg-blue-50 dark:bg-blue-950/60 border-2 border-blue-500 dark:border-blue-600'
                          : isNew
                            ? 'bg-slate-100 dark:bg-slate-800/90 border border-brand-400/60 ring-1 ring-brand-400/30'
                            : 'bg-slate-100 dark:bg-slate-800/90 border border-slate-200 dark:border-slate-700';

                  // Yorliq rangi va matni — uchala tur uchun bir joyda, ternary
                  // zanjiri o'sib ketmasligi uchun.
                  const kindBadge = comment.kind
                    ? {
                        solution: { bg: 'bg-emerald-500', label: 'taskDetail.solutionLabel' },
                        rejection: { bg: 'bg-rose-500', label: 'taskDetail.rejectionLabel' },
                        rating: { bg: 'bg-blue-500', label: 'taskDetail.ratingLabel' },
                      }[comment.kind]
                    : null;

                  // Tomon har doim MUALLIF bo'yicha: o'zing yozgan xabar
                  // (rad etish sababi ham) o'ngda turadi. Ilgari rad etish
                  // kim yozganidan qat'i nazar chapda qolar, murojaatchi o'z
                  // qaytarish sababini suhbatdoshning xabari kabi ko'rardi.
                  const alignOwn = isOwn;

                  return (
                    <div key={comment.id} className={bubbleRow(alignOwn)}>
                    {/* Tizim yozuvlari (yechim / rad etish / baho) bir xil eng
                        kam balandlikka ega: baho matni bir qatorlik bo'lgani
                        uchun ko'k pufakcha yashilidan sezilarli yassi chiqardi.
                        Aniq tenglik mumkin emas — pufakchalar alohida qatorlarda
                        va yashilining balandligi matnga qarab o'zgaradi. */}
                    <div className={`${bubbleWidth} p-3 rounded-2xl space-y-1 ${kindBadge ? 'min-h-[128px]' : ''} ${bubbleTone}`}>
                      {kindBadge && (
                        <span
                          className={`inline-flex items-center px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider text-white ${kindBadge.bg}`}
                        >
                          {t(kindBadge.label)}
                        </span>
                      )}
                      <div className="flex items-center justify-between text-[11px] gap-2">
                        <div className="flex items-center space-x-2 min-w-0">
                          <UserAvatar name={comment.author} src={comment.authorAvatar} className="w-6 h-6 text-[9px]" />
                          <span className="font-extrabold truncate text-brand-600 dark:text-brand-300">
                            {comment.author}
                          </span>
                          {isNew && (
                            <span className="inline-flex items-center px-2 py-0.5 rounded-full bg-brand-500 text-white text-[9px] font-black uppercase tracking-wider flex-shrink-0">
                              {t('taskDetail.newComment')}
                            </span>
                          )}
                        </div>
                        <span className="text-slate-400 font-mono flex-shrink-0">{comment.createdAt}</span>
                      </div>
                      <p className={`text-[11px] font-semibold pl-8 leading-relaxed whitespace-pre-wrap break-words ${isNew ? 'text-success-900 dark:text-success-100' : 'text-slate-800 dark:text-slate-100'}`}>
                        {comment.body}
                      </p>
                    </div>
                    </div>
                  );
                })}
              </div>
            )}

            {/* Yozishma tugmasi. Ikki shart: zayavka yopilmagan bo'lsin VA
                foydalanuvchi xodim bo'lsin. Oddiy foydalanuvchida bu bo'lim
                UMUMAN chizilmaydi — na tugma, na izoh matni: u faqat o'qiydi.
                "Zayavka yopilgan" eslatmasi esa xodimga ko'rinadi. */}
            {canWriteInChat ? (
              <div className="pt-2 flex justify-end">
                <button
                  onClick={() => setIsMessageModalOpen(true)}
                  className="px-4 py-2.5 rounded-xl bg-brand-600 hover:bg-brand-500 text-white font-extrabold text-xs flex items-center space-x-2 shadow-md transition-all cursor-pointer border-none"
                >
                  <Send className="w-3.5 h-3.5" />
                  <span>{t('taskDetail.sendMessage')}</span>
                </button>
              </div>
            ) : isStaffUser ? (
              <div className="pt-2 text-center text-[11px] font-bold text-slate-400 dark:text-slate-500">
                {t('taskDetail.chatClosedNotice')}
              </div>
            ) : null}

            {/* Yechim va rad etish sababi endi yozishmaga ham yoziladi
                (TicketController::appendThreadEntry) — shu bilan zayavka bir
                necha marta yopilib qaytarilganda butun tarix saqlanadi.
                Quyidagi ikki pufakcha ESKI zayavkalar uchun zaxira: yozishmada
                shunday yozuv bo'lmasa, ustundagi matn ko'rsatiladi. */}
            {task.solutionComment && !hasThreadEntry('solution') && (
              <div className={bubbleRow(true)}>
              <div className={`${bubbleWidth} p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 border-2 border-emerald-500 dark:border-emerald-600 space-y-2 shadow-md`}>
                <div className="flex items-center justify-between text-xs border-b border-emerald-300 dark:border-emerald-800 pb-2">
                  <span className="font-extrabold text-emerald-800 dark:text-emerald-200 flex items-center space-x-2.5 text-sm">
                    <UserAvatar name={task.assignedTo} src={task.assignedUserAvatar} className="w-7 h-7 text-[10px]" />
                    <span>{task.assignedTo || t('taskDetail.executor')} ({t('taskDetail.solutionLabel')})</span>
                  </span>
                  <span className="font-mono text-xs text-emerald-700 dark:text-emerald-300 bg-emerald-100 dark:bg-emerald-900/80 px-3 py-1 rounded-lg border border-emerald-300 dark:border-emerald-700">{task.resolvedAt || t('taskDetail.closed')}</span>
                </div>
                <p className="text-[13px] font-bold text-emerald-900 dark:text-emerald-50 leading-relaxed pt-0.5 whitespace-pre-wrap break-words">
                  {task.solutionComment}
                </p>
              </div>
              </div>
            )}

            {task.rejectionReason && !hasThreadEntry('rejection') && (
              <div className={bubbleRow(false)}>
              <div className={`${bubbleWidth} p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/60 border-2 border-rose-500 dark:border-rose-700 space-y-2 shadow-md`}>
                <div className="flex items-center justify-between text-xs border-b border-rose-300 dark:border-rose-800 pb-2">
                  <span className="font-extrabold text-rose-800 dark:text-rose-200 flex items-center space-x-2.5 text-sm">
                    <UserAvatar name={task.initiatorName} src={task.initiatorAvatar} className="w-7 h-7 text-[10px]" />
                    <span>{task.initiatorName || t('taskDetail.initiator')} ({t('taskDetail.rejectionLabel')})</span>
                  </span>
                </div>
                <p className="text-[13px] font-bold text-rose-900 dark:text-rose-50 leading-relaxed pt-0.5 whitespace-pre-wrap break-words">
                  {task.rejectionReason}
                </p>
              </div>
              </div>
            )}
          </div>

          {/* Always-Visible Media & Voice Messages Box */}
          <div className="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4 text-slate-900 dark:text-slate-100">
            <span className="text-xs font-black uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center space-x-2 border-b border-slate-100 dark:border-slate-800 pb-3">
              <Volume2 className="w-4 h-4 text-purple-500 dark:text-purple-400" />
              <span>{t('taskDetail.mediaTitle')}</span>
            </span>

            {/* Audio Voice Player Component */}
            {hasVoiceMessage ? (
              <div className="p-4 rounded-2xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 space-y-2">
                <span className="text-xs font-extrabold text-emerald-600 dark:text-emerald-400 flex items-center space-x-2">
                  <Volume2 className="w-4 h-4 text-emerald-500 animate-pulse" />
                  <span>{t('taskDetail.voiceNoteLabel')}</span>
                </span>
                <audio controls src={stableAudioUrl} className="w-full h-10 rounded-lg" />
              </div>
            ) : (
              <div className="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-800 text-xs font-semibold text-slate-400 flex items-center space-x-2">
                <Volume2 className="w-4 h-4 text-slate-400" />
                <span>{t('taskDetail.noVoiceMessage')}</span>
              </div>
            )}

            {/* Video Player Component */}
            {videosToShow.length > 0 && (
              <div className="space-y-3">
                {videosToShow.map((v, idx) => (
                  <div key={v.id} className="p-4 rounded-2xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 space-y-2">
                    <span className="text-xs font-extrabold text-brand-600 dark:text-brand-400 flex items-center space-x-2">
                      <Video className="w-4 h-4 text-brand-500" />
                      <span>{t('taskDetail.videoLabel', { num: videosToShow.length > 1 ? ` (${idx + 1})` : '' })}</span>
                    </span>
                    <video controls src={v.url} className="w-full max-h-64 rounded-xl object-contain bg-black" />
                  </div>
                ))}
              </div>
            )}

            {/* Screenshots / Attachments Preview */}
            <div className="pt-1">
              <span className="text-xs font-bold text-slate-500 dark:text-slate-400 block mb-2">{t('taskDetail.screenshotLabel')}</span>
              {previewImageUrl ? (
                <div className="flex flex-wrap items-center gap-3">
                  <div
                    onClick={() => openZoom(previewImageUrl)}
                    className="w-36 h-28 rounded-2xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 overflow-hidden cursor-pointer group relative shadow-md"
                  >
                    <img
                      src={previewImageUrl}
                      alt={t('taskDetail.screenshotPreviewAlt')}
                      className="w-full h-full object-cover group-hover:scale-105 transition-transform"
                    />
                    <div className="absolute inset-0 bg-black/40 group-hover:bg-black/20 transition-colors flex items-center justify-center">
                      <span className="text-[10px] font-black text-white px-2 py-0.5 rounded-full bg-black/70 backdrop-blur-xs">{t('taskDetail.zoomIn')}</span>
                    </div>
                  </div>
                  {extraImageUrls.map((imgUrl, idx) => (
                    <div
                      key={idx}
                      onClick={() => openZoom(imgUrl)}
                      className="w-24 h-20 rounded-2xl bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 overflow-hidden cursor-pointer group relative shadow-md"
                      title={t('taskDetail.zoomImageTitle')}
                    >
                      <img
                        src={imgUrl}
                        alt={t('taskDetail.screenshotAlt', { num: idx + 2 })}
                        className="w-full h-full object-cover group-hover:scale-105 transition-transform"
                      />
                      <div className="absolute inset-0 bg-black/40 group-hover:bg-black/20 transition-colors" />
                    </div>
                  ))}
                </div>
              ) : (
                <div className="p-3.5 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-800 text-xs text-slate-400 font-semibold italic">
                  {t('taskDetail.noScreenshot')}
                </div>
              )}
            </div>

            {/* Qo'ng'iroq yozuvlari — faqat admin/superadmin/support ko'radi.
                Murojaatchiga backend ularni umuman yubormaydi. */}
            {callRecordings.length > 0 && (
              <div className="pt-1">
                <span className="text-xs font-bold text-slate-500 dark:text-slate-400 flex items-center gap-1.5 mb-2">
                  <PhoneCall className="w-3.5 h-3.5 text-emerald-500" />
                  {t('taskDetail.callRecordings')}
                  <span className="px-1.5 py-0.5 rounded-full bg-slate-200 dark:bg-slate-700 text-[9px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-300">
                    {t('taskDetail.staffOnly')}
                  </span>
                </span>
                <div className="space-y-2">
                  {callRecordings.map((rec) => (
                    <div
                      key={rec.id}
                      className="p-3 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 space-y-2"
                    >
                      <p className="text-[11px] font-bold text-emerald-800 dark:text-emerald-300 truncate">{rec.name}</p>
                      <audio controls preload="none" src={rec.url} className="w-full h-9" />
                    </div>
                  ))}
                </div>
              </div>
            )}

            {/* Hujjat biriktirmalar (zip, pdf, docx...). Rasm/video emas —
                shuning uchun ko'rsatilmaydi, yuklab olish uchun beriladi. */}
            {filesToShow.length > 0 && (
              <div className="pt-1">
                <span className="text-xs font-bold text-slate-500 dark:text-slate-400 block mb-2">{t('taskDetail.filesLabel')}</span>
                <div className="space-y-2">
                  {filesToShow.map((file) => (
                    <a
                      key={file.id}
                      href={file.url}
                      download={file.name}
                      className="flex items-center gap-3 p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-slate-800 hover:border-brand-400 dark:hover:border-brand-600 transition-colors group"
                    >
                      <Paperclip className="w-4 h-4 text-slate-400 group-hover:text-brand-500 flex-shrink-0" />
                      <span className="min-w-0 flex-1">
                        <span className="block text-xs font-bold text-slate-700 dark:text-slate-200 truncate">{file.name || t('taskDetail.fileFallbackName')}</span>
                        {file.sizeBytes ? <span className="block text-[11px] font-semibold text-slate-400">{formatFileSize(file.sizeBytes)}</span> : null}
                      </span>
                      <Download className="w-4 h-4 text-slate-400 group-hover:text-brand-500 flex-shrink-0" />
                    </a>
                  ))}
                </div>
              </div>
            )}
          </div>

          {/* Workflow Timeline Box */}
          <div className="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4 text-slate-900 dark:text-slate-100">
            <span className="text-xs font-black uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center space-x-2 border-b border-slate-100 dark:border-slate-800 pb-3">
              <Activity className="w-4 h-4 text-amber-500 dark:text-amber-400" />
              <span>{t('taskDetail.workflowTitle')}</span>
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
                      {isSolved ? t('taskDetail.badgeDone') : isRejected ? t('taskDetail.badgeRejected') : t('taskDetail.badgeInProgress')}
                    </span>
                  </div>
                  <p className="text-slate-800 dark:text-slate-200 font-semibold">
                    {t('taskDetail.commentLeft', { comment: task.solutionComment || t('taskDetail.defaultReviewed') })}
                  </p>
                  <p className="text-[11px] text-slate-400 font-mono">
                    {t('taskDetail.beginDate', { date: task.startedAt || task.createdAt, by: task.assignedTo || 'admin' })}
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
                <span>{t('taskDetail.deviceInfo')}</span>
              </span>
            </div>

            <div className="space-y-3 text-xs">
              <div className="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-800">
                <span className="font-semibold text-slate-400">{t('taskDetail.computerName')}</span>
                <span className="font-bold text-slate-900 dark:text-slate-100 font-mono text-[11px] truncate max-w-[170px]" title={task.deviceName || 'Linux 70db6885b8ae'}>
                  {task.deviceName || 'Linux 70db6885b8ae 3.10.0-1160.102.1.el7....'}
                </span>
              </div>

              <div className="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-800">
                <span className="font-semibold text-slate-400">{t('taskDetail.ipLabel')}</span>
                <span className="font-extrabold text-slate-900 dark:text-slate-100 font-mono">
                  {task.ipAddress || '172.27.108.142'}
                </span>
              </div>

              <div className="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-800">
                <span className="font-semibold text-slate-400">{t('taskDetail.browserLabel')}</span>
                <span className="font-bold text-slate-900 dark:text-slate-100">
                  {task.browser || task.device?.browser || '—'}
                </span>
              </div>

              <div className="flex justify-between py-1.5">
                <span className="font-semibold text-slate-400">{t('taskDetail.linkLabel')}</span>
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
                <span>{t('taskDetail.userInfo')}</span>
              </span>
              <DeviceBadge device={task.device} source={task.source} variant="full" />
            </div>

            <div className="space-y-3 text-xs">
              <div className="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-800">
                <span className="font-semibold text-slate-400">{t('taskDetail.fullName')}</span>
                <span className="font-extrabold text-slate-900 dark:text-slate-100 text-right">
                  {task.initiatorName || '—'}
                </span>
              </div>

              <div className="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-800">
                <span className="font-semibold text-slate-400">{t('taskDetail.usernameAd')}</span>
                <span className="font-bold text-slate-900 dark:text-slate-100 font-mono">
                  {task.requesterUsername || '—'}
                </span>
              </div>

              <div className="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-800">
                <span className="font-semibold text-slate-400">{t('taskDetail.emailLabel')}</span>
                <span className="font-bold text-slate-900 dark:text-slate-100 font-mono break-all">
                  {task.requesterEmail || '—'}
                </span>
              </div>

              <div className="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-800">
                <span className="font-semibold text-slate-400">{t('taskDetail.positionAd')}</span>
                <span className="font-extrabold text-slate-900 dark:text-slate-100 text-right">
                  {task.requesterPosition || '—'}
                </span>
              </div>

              <div className="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-800">
                <span className="font-semibold text-slate-400">{t('taskDetail.departmentAd')}</span>
                <span className="font-extrabold text-slate-900 dark:text-slate-100 text-right">
                  {task.requesterDepartment || task.originDepartment || '—'}
                </span>
              </div>

              <div className="flex justify-between py-1.5 border-b border-slate-100 dark:border-slate-800">
                <span className="font-semibold text-slate-400">{t('taskDetail.phoneNumber')}</span>
                <span className="font-extrabold text-slate-900 dark:text-slate-100 font-mono">
                  {task.initiatorPhone || '—'}
                </span>
              </div>

            </div>
          </div>

          {/* Action Buttons for Specialist */}
          {!isSolved && isOpenUnassigned && canTakeTickets && (
            <Button
              variant="primary"
              className="w-full bg-brand-600 hover:bg-brand-500 font-extrabold border-none"
              size="lg"
              onClick={handleAcceptTask}
              leftIcon={<CheckCircle className="w-5 h-5" />}
            >
              {t('taskDetail.acceptTask')}
            </Button>
          )}

          {!isSolved && !isRejected && !isInProgress && !isOpenUnassigned && canWorkOnTicket && (
            <Button
              variant="primary"
              className="w-full bg-amber-500 hover:bg-amber-600 font-extrabold border-none"
              size="lg"
              onClick={handleMoveToInProgress}
              leftIcon={<PlayCircle className="w-5 h-5" />}
            >
              {t('taskDetail.moveToProgress')}
            </Button>
          )}

          {/* Yakunlash — faqat ijrochi amali (`isStaffUser` sherigi qo'shni
              tugmalarda bor edi, bu yerda tushib qolgan edi).
              Rad etilgan zayavka ham xodimning ochiq ishi: u kartochkada
              "Jarayonda" ko'rinishida turadi va shu yerdan yakunlanadi —
              aks holda uni yopishning yo'li qolmasdi va xodim yopilmagan
              qaytarilgan zayavka tufayli yangi zayavka ham ololmasdi. */}
          {!isSolved && (task.status === 'in_progress' || task.status === 'rejected') && canWorkOnTicket && (
            <Button
              variant="primary"
              className="w-full bg-emerald-600 hover:bg-emerald-500 border-none font-extrabold text-white"
              size="lg"
              onClick={() => setIsSolveOpen(true)}
              leftIcon={<CheckCircle className="w-5 h-5" />}
            >
              {t('taskDetail.markAsDone')}
            </Button>
          )}

          {/* Bajarilgan zayavka — so'rovchi baholaydi yoki qaytaradi */}
          {canRateOrReturn && (
            <div className="p-5 rounded-3xl bg-success-50 dark:bg-success-700/20 border border-success-500/30 space-y-3">
              <p className="text-xs font-extrabold text-success-700 dark:text-success-300">
                {t('myRequests.doneBannerTitle')}
              </p>
              <p className="text-[11px] font-medium text-slate-600 dark:text-slate-300">
                {t('myRequests.doneBannerDesc')}
              </p>

              <Button
                variant="primary"
                className="w-full bg-success-500 hover:bg-success-600 border-none font-extrabold text-white"
                onClick={() => setIsRateOpen(true)}
                leftIcon={<Star className="w-4 h-4" />}
              >
                {t('taskCard.rateAndClose')}
              </Button>

              <Button
                variant="primary"
                className="w-full bg-rose-600 hover:bg-rose-500 border-none font-extrabold text-white"
                onClick={() => setIsReturnOpen(true)}
                leftIcon={<RotateCcw className="w-4 h-4" />}
              >
                {t('myRequests.reject')}
              </Button>
            </div>
          )}
        </div>
      </div>

      {/* Reassign Staff Modal (staff only) */}
      {canAssignTickets && isAssignModalOpen && (
        <Modal isOpen={isAssignModalOpen} onClose={() => setIsAssignModalOpen(false)} title={t('taskDetail.assignModalTitle')}>
          <div className="space-y-5 p-4 text-xs bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 rounded-2xl">
            {/* Quick Takeover Option */}
            <div className="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 space-y-2">
              <div className="flex items-center justify-between">
                <span className="font-extrabold text-amber-900 dark:text-amber-300 text-sm">⚡ {t('taskDetail.takeover')}</span>
                <span className="text-[10px] font-bold text-amber-700 dark:text-amber-400">{t('taskDetail.quick')}</span>
              </div>
              <p className="text-slate-600 dark:text-slate-300">
                {t('taskDetail.takeoverDescBefore')} <strong>{t('taskDetail.takeoverDescStrong')}</strong> ({currentUser?.username || 'admin'}) {t('taskDetail.takeoverDescAfter')}
              </p>
              {isTakingOverSomeoneElse && (
                <p className="text-[10px] font-extrabold text-rose-600 dark:text-rose-300">
                  ⚠️ {t('taskDetail.takeoverWarning')}
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
                {t('taskDetail.assignToMe')}
              </Button>
            </div>

            <div className="border-t border-slate-100 dark:border-slate-800 pt-4 space-y-3">
              <span className="font-extrabold text-slate-800 dark:text-slate-200 block">{t('taskDetail.selectEmployee')}</span>
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
                  {t('taskDetail.assignReasonLabel')}{isTakingOverSomeoneElse && <span className="text-rose-500"> *</span>}
                </span>
                <input
                  type="text"
                  value={reassignReason}
                  onChange={(e) => setReassignReason(e.target.value)}
                  placeholder={isTakingOverSomeoneElse ? t('taskDetail.reasonRequiredPlaceholder') : t('taskDetail.reasonPlaceholder')}
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
                {t('common.cancel')}
              </Button>
              <Button
                variant="primary"
                onClick={() => handleAssignTask()}
                isLoading={isAssigning}
                disabled={!selectedAssigneeId || (isTakingOverSomeoneElse && !reassignReason.trim())}
                leftIcon={<UserCheck className="w-4 h-4" />}
              >
                {t('taskDetail.assignSelected')}
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
              <span>{t('taskDetail.viewImage')}</span>
            </span>
            <div className="flex items-center space-x-1.5 sm:space-x-2">
              <button
                onClick={() => setZoomScale((s) => Math.max(s / 1.25, 0.25))}
                className="p-2 rounded-xl bg-white/10 hover:bg-white/20 transition-colors cursor-pointer"
                title={t('taskDetail.zoomOutTitle')}
              >
                <ZoomOut className="w-4 h-4" />
              </button>
              <span className="text-[11px] font-black text-brand-300 min-w-[44px] text-center">
                {Math.round(zoomScale * 100)}%
              </span>
              <button
                onClick={() => setZoomScale((s) => Math.min(s * 1.25, 5))}
                className="p-2 rounded-xl bg-white/10 hover:bg-white/20 transition-colors cursor-pointer"
                title={t('taskDetail.zoomInTitle')}
              >
                <ZoomIn className="w-4 h-4" />
              </button>
              <button
                onClick={() => {
                  setZoomScale(1);
                  setPanOffset({ x: 0, y: 0 });
                }}
                className="inline-flex items-center space-x-1.5 px-2.5 py-2 rounded-xl bg-white/10 hover:bg-white/20 transition-colors cursor-pointer"
                title={t('taskDetail.fitScreenTitle')}
              >
                <Maximize className="w-4 h-4" />
                <span className="hidden sm:inline text-[11px] font-bold">{t('taskDetail.fitScreen')}</span>
              </button>
              <button
                onClick={() => setZoomImageUrl(null)}
                className="p-2 rounded-xl bg-rose-500/80 hover:bg-rose-500 transition-colors cursor-pointer"
                title={t('taskDetail.closeEscTitle')}
              >
                <X className="w-4 h-4" />
              </button>
            </div>
          </div>

          {/* Scrollable image area — g'ildirak: zoom, tortish (drag): surish */}
          <div
            ref={zoomContainerRef}
            onPointerDown={handlePointerDown}
            onPointerMove={handlePointerMove}
            onPointerUp={handlePointerUp}
            onPointerCancel={handlePointerUp}
            onClick={handleZoomContainerClick}
            onDoubleClick={handleDoubleClick}
            className="flex-1 overflow-hidden p-4 sm:p-8 flex items-center justify-center select-none touch-none cursor-grab active:cursor-grabbing"
          >
            <img
              src={zoomImageUrl}
              alt={t('taskDetail.screenshotFullAlt')}
              draggable={false}
              onClick={(e) => e.stopPropagation()}
              className="rounded-xl shadow-2xl select-none will-change-transform"
              style={{
                // Surish paytida `transition` bo'lmaydi — aks holda rasm
                // kursordan orqada sudralib, "qo'lga yopishmagandek" tuyuladi.
                transition: dragState.current.dragging ? 'none' : 'transform 100ms',
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
        <Modal isOpen={isMessageModalOpen} onClose={() => setIsMessageModalOpen(false)} title={t('taskDetail.sendMessageModalTitle')}>
          <div className="space-y-4 p-4 text-xs bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 rounded-2xl">
            <textarea
              autoFocus
              value={messageText}
              onChange={(e) => setMessageText(e.target.value)}
              placeholder={t('taskDetail.messagePlaceholder')}
              className="w-full p-3 rounded-2xl border border-slate-200 dark:border-slate-800 text-xs bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-100 focus:ring-2 focus:ring-brand-500 focus:outline-none"
              rows={4}
            />
            {messageError && (
              <div className="p-3 rounded-xl bg-error-50 dark:bg-error-700/20 border border-error-500/20 text-error-500 text-xs font-semibold">
                {messageError}
              </div>
            )}

            <div className="flex justify-end space-x-2">
              <Button variant="secondary" onClick={() => setIsMessageModalOpen(false)}>
                {t('common.cancel')}
              </Button>
              <Button
                variant="primary"
                onClick={handleSendMessage}
                disabled={isSendingMessage || !messageText.trim()}
                leftIcon={<Send className="w-4 h-4" />}
              >
                {t('taskDetail.send')}
              </Button>
            </div>
          </div>
        </Modal>
      )}

      {/* Yakunlash — yechim izohi majburiy */}
      <SolveTaskModal
        task={task}
        isOpen={isSolveOpen}
        onClose={() => setIsSolveOpen(false)}
        onSuccess={() => {
          setIsSolveOpen(false);
          refetch();
        }}
      />

      {/* Baholash va qaytarish — so'rovchi uchun */}
      <RateTaskModal
        task={task}
        isOpen={isRateOpen}
        onClose={() => setIsRateOpen(false)}
        onSuccess={() => {
          setIsRateOpen(false);
          refetch();
        }}
      />

      <RejectTaskModal
        task={task}
        isOpen={isReturnOpen}
        onClose={() => setIsReturnOpen(false)}
        onSuccess={() => {
          setIsReturnOpen(false);
          refetch();
        }}
      />
    </div>
  );
};
