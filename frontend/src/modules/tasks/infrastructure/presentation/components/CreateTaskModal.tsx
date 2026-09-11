import React, { useState, useEffect, useRef } from 'react';
import { X, Send, AlertCircle, UsersRound, Mic, Square, Image, FileText, Trash2, FileText as TemplateIcon } from 'lucide-react';
import { useCreateTask } from '../hooks/useCreateTask';
import { axiosClient } from '@/shared/infrastructure/http/axiosClient';
import { useT } from '@/shared/presentation/i18n/i18n';
import { useAuthStore } from '@/shared/presentation/store/useAuthStore';
import { RequiredMark } from '@/shared/presentation/components/RequiredMark';
import type { User } from '@/modules/authentication/domain/entities/User';
import fixWebmDuration from 'fix-webm-duration';

interface CreateTaskModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

interface TeamItem {
  id: number;
  name: string;
  code: string;
  department_id?: number | null;
}

interface TicketTemplate {
  id: number;
  teamId: number | null;
  name: string;
  content: string;
}

/**
 * Biriktirilgan faylning hajmi chegarasi.
 *
 * Faqat foydalanuvchi o'zi tanlagan faylga tegishli. Ovoz va skrinshot
 * avtomatik olinadi — ular bu chegaraga qo'shilmaydi va cheklanmaydi.
 */
const MAX_FILE_BYTES = 60 * 1024 * 1024;

const formatMb = (bytes: number) => `${(bytes / 1024 / 1024).toFixed(1)} MB`;

interface ActiveSlaRule {
  id: number;
  name: string;
  description: string | null;
  is_default: boolean;
}

/**
 * "Default holat" — shablon tanlanmagan zayavka. Ro'yxatda birinchi turadi va
 * boshlang'ich tanlov shu: zayavkalarning ko'pi shablonsiz yuboriladi.
 */
const DEFAULT_KEY = 'default';

/**
 * Shablonsiz zayavka uchun so'rovchi ma'lumotlari shapkasi.
 *
 * Shablon tanlanganda bu ishni server bajaradi (matndagi tanish qatorlarni
 * to'ldiradi), lekin "Default holat" da to'ldiriladigan matnning o'zi yo'q —
 * shapka shu yerda yasaladi.
 */
const requesterHeader = (user: User | null, t: (key: string) => string): string => [
  `${t('createTask.fieldFullName')}: ${user?.fullName ?? ''}`,
  `${t('createTask.fieldDepartment')}: ${user?.department ?? ''}`,
  `${t('createTask.fieldPhone')}: ${user?.phone ?? ''}`,
  `${t('createTask.fieldPosition')}: ${user?.position ?? ''}`,
  '',
  '',
].join('\n');

export const CreateTaskModal: React.FC<CreateTaskModalProps> = ({ isOpen, onClose, onSuccess }) => {
  const t = useT();
  const currentUser = useAuthStore((s) => s.user);
  const [todo, setTodo] = useState('');

  // Group / Team state — to'liq dinamik (/teams dan keladi)
  const [teams, setTeams] = useState<TeamItem[]>([]);
  const [selectedTeamId, setSelectedTeamId] = useState<number | null>(null);
  const [teamsLoading, setTeamsLoading] = useState(false);

  // Templates (Shablonlar) — tanlangan guruhga qarab yuklanadi
  const [templates, setTemplates] = useState<TicketTemplate[]>([]);
  const [templatesLoading, setTemplatesLoading] = useState(false);
  // Tanlov ikki manbadan keladi (SLA qoidasi va shablon), shuning uchun kalit
  // matnli: "sla-3" / "tpl-7". Ilgari bu raqam edi va ikkala ro'yxatning
  // id lari to'qnashib ketardi.
  const [selectedTemplateKey, setSelectedTemplateKey] = useState<string>(DEFAULT_KEY);
  // Matnni oxirgi marta biz qo'ygan qiymat. Foydalanuvchi o'zi yozganini
  // shablon bilan bosib ketmaslik uchun kerak.
  const autoFilledRef = useRef<string>('');
  const [slaRules, setSlaRules] = useState<ActiveSlaRule[]>([]);

  // Media attachments & Voice Recording
  const [attachedFile, setAttachedFile] = useState<File | null>(null);
  const [filePreview, setFilePreview] = useState<string | null>(null);
  const [isRecording, setIsRecording] = useState(false);
  const [audioUrl, setAudioUrl] = useState<string | null>(null);
  const mediaRecorderRef = useRef<MediaRecorder | null>(null);
  const audioChunksRef = useRef<Blob[]>([]);
  // Yuboriladigan YAKUNIY blob — davomiyligi tuzatilgan holda shu yerda turadi.
  const audioBlobRef = useRef<Blob | null>(null);
  const recordStartRef = useRef<number>(0);
  // Yozuv uzunligi — yozilayotganda tirik hisoblagich, tugagach yakuniy qiymat.
  const [recordedMs, setRecordedMs] = useState(0);
  const streamRef = useRef<MediaStream | null>(null);
  // `onstop` asinxron ishlaydi (fixWebmDuration), shuning uchun "Yuborish"
  // bosilganda yakuniy blob hali tayyor bo'lmaydi. Kutayotganlar shu yerda
  // navbatda turadi va yozuv tugagach hammasi bir vaqtda uyg'otiladi.
  const finalizeResolversRef = useRef<((blob: Blob | null) => void)[]>([]);
  // Yozuvni yakunlash kutilayotgan payt — ikki marta yuborilib ketmasin.
  const [isFinalizing, setIsFinalizing] = useState(false);

  const [error, setError] = useState<string | null>(null);
  const [uploadPercent, setUploadPercent] = useState<number | null>(null);
  const createTaskMutation = useCreateTask(setUploadPercent);

  useEffect(() => {
    if (isOpen) {
      // Boshlang'ich holat — "Default holat": matn maydonida so'rovchi
      // ma'lumotlari tayyor turadi, xodim faqat muammosini yozadi.
      //
      // Oyna yopilganda matn saqlanib qoladi, shuning uchun foydalanuvchi
      // o'zi yozgan qoralama qayta ochilganda shapka bilan bosilmaydi.
      const header = requesterHeader(currentUser, t);
      const hasDraft = todo.trim() !== '' && todo !== autoFilledRef.current;
      if (!hasDraft) {
        setTodo(header);
        autoFilledRef.current = header;
      }

      setTeamsLoading(true);
      axiosClient.get<{ data: TeamItem[] }>('/teams', {
        params: { per_page: 100, is_active: 1 },
      })
        .then((res) => {
          const list = res.data.data || [];
          setTeams(list);
          // Guruhni foydalanuvchi o'zi tanlaydi — avtomatik tanlab qo'ymaymiz.
        })
        .catch(() => {
          setTeams([]);
        })
        .finally(() => setTeamsLoading(false));
    }
  }, [isOpen]);

  // Tanlangan guruhga mos shablonlarni yuklash
  useEffect(() => {
    if (!selectedTeamId) {
      setTemplates([]);
      setSelectedTemplateKey(DEFAULT_KEY);
      return;
    }

    setTemplatesLoading(true);
    setSelectedTemplateKey(DEFAULT_KEY);
    // `prefill=1` — shablondagi "Xodim F.I.Sh:" kabi bo'sh qatorlarni server
    // so'rovchi ma'lumoti bilan to'ldirib beradi.
    axiosClient.get<{ data: TicketTemplate[] }>('/ticket-templates', { params: { team_id: selectedTeamId, prefill: 1 } })
      .then((res) => {
        setTemplates(res.data.data || []);
      })
      .catch(() => {
        setTemplates([]);
      })
      .finally(() => setTemplatesLoading(false));
  }, [selectedTeamId]);

  // Guruhning BARCHA faol SLA qoidalari shablonlar qatorida ko'rsatiladi.
  //
  // Ilgari bu yerda `per_page: 1` turardi va faqat izohi bo'lgan bitta qoida
  // olinardi — guruhga qancha qoida qo'shilmasin, ro'yxatda bittasi (ba'zan
  // umuman hech nima) ko'rinardi.
  useEffect(() => {
    if (!selectedTeamId) {
      setSlaRules([]);
      return;
    }

    let cancelled = false;
    setSlaRules([]);
    axiosClient.get<{ data: ActiveSlaRule[] }>('/sla-rules', {
      params: { team_id: selectedTeamId, is_active: 1, per_page: 100, prefill: 1 },
    })
      .then((res) => {
        if (cancelled) return;
        // Guruhning "Default holat" qoidasi ro'yxatda takrorlanmaydi — u
        // yuqoridagi alohida qator sifatida turadi.
        setSlaRules((res.data.data ?? []).filter((rule) => !rule.is_default));
      })
      .catch(() => {
        if (cancelled) return;
        setSlaRules([]);
      });

    return () => {
      cancelled = true;
    };
  }, [selectedTeamId]);

  // Yozilayotganda uzunlikni tirik ko'rsatamiz — foydalanuvchi ham, biz ham
  // yozuv necha soniya davom etganini aniq bilamiz.
  useEffect(() => {
    if (!isRecording) return;

    const id = window.setInterval(
      () => setRecordedMs(Date.now() - recordStartRef.current),
      200,
    );

    return () => window.clearInterval(id);
  }, [isRecording]);

  if (!isOpen) return null;

  const formatDuration = (ms: number) => {
    const total = Math.round(ms / 1000);
    return `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
  };

  // Fayl qabul qilishning yagona nuqtasi — tugma orqali tanlash ham,
  // Ctrl+V bilan yopishtirish ham shu yerdan o'tadi.
  const acceptFile = (file: File) => {
    if (file.size > MAX_FILE_BYTES) {
      setError(t('createTask.fileTooLarge', {
        size: formatMb(file.size),
        limit: formatMb(MAX_FILE_BYTES),
      }));
      return;
    }

    setError(null);
    setAttachedFile(file);
    setFilePreview((old) => {
      if (old) URL.revokeObjectURL(old);
      return file.type.startsWith('image/') ? URL.createObjectURL(file) : null;
    });
  };

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files[0]) {
      acceptFile(e.target.files[0]);
    }
  };

  // Ctrl+V: ekran rasmini to'g'ridan-to'g'ri yopishtirish.
  // Clipboard'dan kelgan faylning nomi bo'lmaydi ("image.png" yoki bo'sh),
  // shuning uchun vaqt belgisi bilan tushunarli nom beramiz — zayavkada
  // biriktirma nomi shu ko'rinishda saqlanadi.
  const handlePaste = (e: React.ClipboardEvent) => {
    const items = Array.from(e.clipboardData?.items ?? []);
    const imageItem = items.find((item) => item.kind === 'file' && item.type.startsWith('image/'));

    if (!imageItem) {
      return; // oddiy matn yopishtirilyapti — aralashmaymiz
    }

    const file = imageItem.getAsFile();
    if (!file) {
      return;
    }

    e.preventDefault();

    const ext = (file.type.split('/')[1] || 'png').replace('jpeg', 'jpg');
    const stamp = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
    acceptFile(new File([file], `screenshot_${stamp}.${ext}`, { type: file.type }));
    setError(null);
  };

  const startVoiceRecording = async () => {
    // Brauzer mikrofonni faqat "secure context" da beradi: HTTPS yoki
    // localhost/127.0.0.1. Oddiy HTTP orqali LAN IP bilan ochilganda
    // (masalan http://172.28.201.27:5173) navigator.mediaDevices umuman
    // mavjud bo'lmaydi — bu ruxsat rad etilgani emas, boshqa muammo.
    if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
      setError(t('createTask.micInsecureContext'));
      return;
    }

    if (typeof MediaRecorder === 'undefined') {
      setError(t('createTask.micUnsupported'));
      return;
    }

    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      // Stream ref'da saqlanadi — yozuv tugaganda/modal yopilganda track'lar
      // to'xtatiladi (aks holda mikrofon brauzerda yoniq qoladi — privacy!)
      streamRef.current = stream;
      const mediaRecorder = new MediaRecorder(stream);
      mediaRecorderRef.current = mediaRecorder;
      audioChunksRef.current = [];

      mediaRecorder.ondataavailable = (event) => {
        if (event.data.size > 0) {
          audioChunksRef.current.push(event.data);
        }
      };

      mediaRecorder.onstop = async () => {
        try {
          const rawBlob = new Blob(audioChunksRef.current, { type: 'audio/webm' });

          // MediaRecorder WebM'ni "jonli oqim" sifatida yozadi va sarlavhaga
          // davomiylikni QO'YMAYDI. Natijada <audio> duration'ni Infinity deb
          // ko'radi va yozuv bir necha soniyada tugagandek eshitiladi —
          // fayl to'liq bo'lsa ham. Shu yerda haqiqiy davomiylikni yozib qo'yamiz.
          const durationMs = Date.now() - recordStartRef.current;
          setRecordedMs(durationMs);
          let finalBlob = rawBlob;
          try {
            finalBlob = await fixWebmDuration(rawBlob, durationMs, { logger: false });
          } catch (e) {
            console.error("WebM davomiyligini tuzatib bolmadi, xom yozuv ishlatiladi", e);
          }

          audioBlobRef.current = finalBlob;
          const url = URL.createObjectURL(finalBlob);
          setAudioUrl((old) => {
            if (old) URL.revokeObjectURL(old);
            return url;
          });
          stopStreamTracks();
        } finally {
          // Kutayotgan `finishRecording()` chaqiruvlari — xato bo'lsa ham
          // osilib qolmasin, aks holda "Yuborish" abadiy kutib turardi.
          const resolvers = finalizeResolversRef.current;
          finalizeResolversRef.current = [];
          resolvers.forEach((resolve) => resolve(audioBlobRef.current));
        }
      };

      recordStartRef.current = Date.now();
      // Timeslice: ma'lumot har soniyada `ondataavailable` ga tashlanadi.
      // Timeslice'siz butun yozuv faqat stop paytida bitta bo'lakda keladi va
      // biror uzilishda (sahifa qayta render bo'lishi, oqim uzilishi) hammasi
      // yo'qoladi yoki qirqilib qoladi.
      mediaRecorder.start(1000);
      setIsRecording(true);
      setRecordedMs(0);
    } catch (err) {
      // Asl sababni ko'rsatamiz — ilgari har qanday xato "ruxsat berilmadi"
      // deb chiqardi va muammoni topish imkonsiz edi.
      const name = (err as DOMException)?.name;
      if (name === 'NotAllowedError' || name === 'SecurityError') {
        setError(t('createTask.micPermission'));
      } else if (name === 'NotFoundError' || name === 'OverconstrainedError') {
        setError(t('createTask.micNotFound'));
      } else if (name === 'NotReadableError') {
        setError(t('createTask.micBusy'));
      } else {
        setError(t('createTask.micUnsupported'));
      }
      console.error('Mikrofonni ishga tushirib bo\'lmadi', err);
    }
  };

  const stopStreamTracks = () => {
    streamRef.current?.getTracks().forEach((track) => track.stop());
    streamRef.current = null;
  };

  /**
   * Yozuvni to'xtatadi va davomiyligi tuzatilgan YAKUNIY blobni qaytaradi.
   *
   * Yozuv ketayotgan bo'lsa `onstop` tugashini kutadi — shu sabab Promise:
   * ilgari "Yuborish" bosilganda blob hali tayyor bo'lmagani uchun zayavka
   * ovozsiz ketardi va mikrofon yoniq qolib ketardi.
   */
  const finishRecording = (): Promise<Blob | null> => {
    const recorder = mediaRecorderRef.current;

    if (!recorder || recorder.state === 'inactive') {
      setIsRecording(false);
      return Promise.resolve(audioBlobRef.current);
    }

    const pending = new Promise<Blob | null>((resolve) => {
      finalizeResolversRef.current.push(resolve);
    });

    setIsRecording(false);
    try {
      recorder.stop();
    } catch (e) {
      console.error("Yozuvni to'xtatib bo'lmadi", e);
      const resolvers = finalizeResolversRef.current;
      finalizeResolversRef.current = [];
      resolvers.forEach((resolve) => resolve(audioBlobRef.current));
      stopStreamTracks();
    }

    return pending;
  };

  const stopVoiceRecording = () => {
    void finishRecording();
  };

  // Modal yopilganda ham mikrofon va eski blob URL tozalanadi
  const handleClose = () => {
    void finishRecording();
    stopStreamTracks();
    onClose();
  };

  // Yozilgan ovozni butunlay olib tashlaydi. audioChunksRef ni ham tozalash SHART —
  // handleSubmit aynan shu ref uzunligiga qarab audio biriktiradi, ya'ni faqat
  // audioUrl ni null qilish "o'chirdim" degani emas: fayl baribir yuborilaverardi.
  const clearRecording = () => {
    audioChunksRef.current = [];
    audioBlobRef.current = null;
    setRecordedMs(0);
    setAudioUrl((old) => {
      if (old) URL.revokeObjectURL(old);
      return null;
    });
  };

  const removeAttachedFile = () => {
    setAttachedFile(null);
    setFilePreview((old) => {
      if (old) URL.revokeObjectURL(old);
      return null;
    });
  };

  /**
   * Matnni shablon bilan almashtirish.
   *
   * Foydalanuvchi O'ZI yozgan matn savolsiz bosilib ketmasligi kerak. Oldingi
   * shablondan qolgan matn esa savolsiz almashtiriladi — shablonni almashtirib
   * ko'rish odatiy harakat va har safar so'rash bezor qiladi.
   */
  const applyTemplateText = (text: string): boolean => {
    const typedByUser = todo.trim() !== '' && todo !== autoFilledRef.current;
    if (typedByUser && !window.confirm(t('createTask.replaceTextConfirm'))) return false;

    setTodo(text);
    autoFilledRef.current = text;
    return true;
  };

  const handleTemplateChange = (key: string) => {
    if (key === DEFAULT_KEY) {
      if (applyTemplateText(requesterHeader(currentUser, t))) setSelectedTemplateKey(key);
      return;
    }

    if (key.startsWith('sla-')) {
      const rule = slaRules.find((item) => `sla-${item.id}` === key);
      // Izohi bo'lmagan qoida uchun hech bo'lmasa nomi qo'yiladi:
      // foydalanuvchi bo'sh maydon bilan qolib ketmasin.
      if (rule && applyTemplateText(rule.description?.trim() || rule.name)) setSelectedTemplateKey(key);
      return;
    }

    const tmpl = templates.find((item) => `tpl-${item.id}` === key);
    if (tmpl && applyTemplateText(tmpl.content)) setSelectedTemplateKey(key);
  };

  const resetForm = () => {
    setTodo('');
    setSlaRules([]);
    removeAttachedFile();
    clearRecording();
    setSelectedTeamId(null);
    setTemplates([]);
    setSelectedTemplateKey(DEFAULT_KEY);
    autoFilledRef.current = '';
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (isFinalizing || createTaskMutation.isPending) return;

    if (!selectedTeamId) {
      setError(t('createTask.teamRequired'));
      return;
    }

    let fullDescription = todo.trim();

    if (!fullDescription) {
      setError(t('createTask.todoRequired'));
      return;
    }

    const selectedTeam = teams.find((t) => t.id === selectedTeamId);

    setError(null);

    // Ovoz yozib turgan bo'lsa — avval yozuvni to'xtatib, yakuniy blob
    // tayyor bo'lishini kutamiz. Aks holda zayavka ovozsiz ketardi.
    let audioBlob = audioBlobRef.current;
    if (mediaRecorderRef.current?.state === 'recording') {
      setIsFinalizing(true);
      try {
        audioBlob = await finishRecording();
      } finally {
        setIsFinalizing(false);
      }
    }

    // Faqat yuklash foizi uchun: cheklov acceptFile'da faylning o'ziga qo'yilgan.
    const totalBytes = (attachedFile?.size ?? 0) + (audioBlob?.size ?? 0);

    const formData = new FormData();
    formData.append('todo', fullDescription);
    if (selectedTeam?.name) formData.append('category', selectedTeam.name);
    formData.append('teamId', String(selectedTeamId));

    // Tanlangan shablon aslida SLA qoidasi — uning muddati shu zayavkaga
    // qo'llanadi. Tanlanmasa server "default holat" muddatini beradi.
    if (selectedTemplateKey.startsWith('sla-')) {
      formData.append('slaRuleId', selectedTemplateKey.replace('sla-', ''));
    }

    if (attachedFile) {
      if (attachedFile.type.startsWith('image/')) {
        formData.append('screenshot', attachedFile);
      } else if (attachedFile.type.startsWith('video/')) {
        formData.append('video', attachedFile);
      } else {
        formData.append('file', attachedFile);
      }
    }

    // Xom chunk'lardan emas, davomiyligi tuzatilgan blobdan yuboramiz.
    if (audioBlob) {
      const audioFile = new File([audioBlob], `voice_${Date.now()}.webm`, { type: 'audio/webm' });
      formData.append('audio', audioFile);
    }

    // Fayl bo'lmasa foiz oynasi ochilmaydi: matnli zayavka bir zumda ketadi.
    setUploadPercent(totalBytes > 0 ? 0 : null);

    createTaskMutation.mutate(
      formData,
      {
        onSuccess: () => {
          resetForm();
          onSuccess();
          onClose();
        },
        onError: (err: any) => {
          // 413 — serverning so'rov hajmi chegarasi. Javobda JSON bo'lmaydi
          // (nginx/PHP so'rovni umuman qabul qilmaydi), shuning uchun xom
          // "Request failed with status code 413" chiqib qolardi.
          const status = err?.statusCode ?? err?.response?.status;
          const msg = status === 413
            ? t('createTask.serverFileTooLarge', { size: formatMb(totalBytes) })
            : (err.response?.data?.message || err.message || t('createTask.createError'));
          setError(msg);
        },
        onSettled: () => {
          setUploadPercent(null);
        },
      }
    );
  };

  return (
    <div
      onPaste={handlePaste}
      className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-900/60 backdrop-blur-sm animate-fadeIn"
    >
      {/* Yuklash foizi — ekran o'rtasida. Katta ovoz/video bilan zayavka
          bir necha soniya ketadi va ilgari hech qanday belgi yo'q edi:
          foydalanuvchi tugmani qayta-qayta bosardi. */}
      {uploadPercent !== null && (
        <div className="fixed inset-0 z-[60] flex flex-col items-center justify-center gap-4 bg-slate-900/70 backdrop-blur-sm">
          <div className="relative w-32 h-32">
            <svg className="w-32 h-32 -rotate-90" viewBox="0 0 120 120">
              <circle cx="60" cy="60" r="52" strokeWidth="10" className="stroke-white/25" fill="none" />
              <circle
                cx="60"
                cy="60"
                r="52"
                strokeWidth="10"
                strokeLinecap="round"
                fill="none"
                className="stroke-brand-400 transition-[stroke-dashoffset] duration-200"
                strokeDasharray={2 * Math.PI * 52}
                strokeDashoffset={2 * Math.PI * 52 * (1 - uploadPercent / 100)}
              />
            </svg>
            <span className="absolute inset-0 flex items-center justify-center text-2xl font-black text-white tabular-nums">
              {uploadPercent}%
            </span>
          </div>
          <p className="text-xs font-bold text-white/90">{t('createTask.uploading')}</p>
        </div>
      )}
      {/* Balandlik ekran bilan cheklangan: sarlavha va tugmalar joyida
          qoladi, faqat maydonlar skrol bo'ladi. Ilgari butun karta skrol
          bo'lardi va uzun matnda "Yuborish" ekrandan chiqib ketardi. */}
      <div className="bg-white dark:bg-slate-800 rounded-2xl sm:rounded-3xl max-w-3xl w-full p-4 sm:p-6 shadow-2xl border border-slate-200 dark:border-slate-700 space-y-4 relative overflow-hidden max-h-[95vh] flex flex-col">
        {/* Header */}
        <div className="shrink-0 flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-700/80">
          <div>
            <h2 className="text-lg font-extrabold text-slate-900 dark:text-slate-100">{t('createTask.title')}</h2>
          </div>
          <button
            onClick={handleClose}
            className="p-1.5 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {error && (
          <div className="shrink-0 p-2.5 rounded-xl bg-error-50 dark:bg-error-700/20 border border-error-500/20 text-error-500 text-xs font-semibold flex items-center space-x-2">
            <AlertCircle className="w-4 h-4 flex-shrink-0" />
            <span>{error}</span>
          </div>
        )}

        <form onSubmit={handleSubmit} className="flex-1 min-h-0 flex flex-col">
          <div className="flex-1 min-h-0 overflow-y-auto space-y-3 pr-1">
          {/* Row 1: Service Group & Template side by side in 2 columns */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            {/* Service Group Selection */}
            <div>
              <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1 flex items-center space-x-1">
                <UsersRound className="w-3.5 h-3.5 text-brand-500" />
                <span>{t('createTask.teamLabel')}<RequiredMark /></span>
              </label>
              <select
                value={selectedTeamId ?? ''}
                onChange={(e) => {
                  const val = e.target.value;
                  setSelectedTeamId(val ? Number(val) : null);
                }}
                disabled={teamsLoading}
                className="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 text-slate-900 dark:text-slate-100 text-xs font-bold focus:ring-2 focus:ring-brand-500 focus:outline-none transition-all disabled:opacity-60"
              >
                <option value="">
                  {teamsLoading ? t('createTask.teamsLoading') : t('createTask.selectTeam')}
                </option>
                {teams.map((t) => (
                  <option key={t.id} value={t.id}>
                    {t.name}
                  </option>
                ))}
              </select>
              {!teamsLoading && teams.length === 0 && (
                <p className="mt-1 text-[10px] font-semibold text-amber-600 dark:text-amber-400">
                  {t('createTask.noTeams')}
                </p>
              )}
            </div>

            {/* Template Selection */}
            <div>
              <label className="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1 flex items-center space-x-1">
                <TemplateIcon className="w-3.5 h-3.5 text-brand-500" />
                <span>{t('createTask.templateLabel')}</span>
              </label>
              <select
                value={selectedTemplateKey}
                onChange={(e) => handleTemplateChange(e.target.value)}
                disabled={templatesLoading}
                className="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 text-slate-900 dark:text-slate-100 text-xs font-bold focus:ring-2 focus:ring-brand-500 focus:outline-none transition-all disabled:opacity-50"
              >
                {/* Shablonsiz zayavka — guruhning "Default holat" muddati. */}
                <option value={DEFAULT_KEY}>{t('createTask.defaultOption')}</option>
                {slaRules.map((rule) => (
                  // Ro'yxatda faqat qoida nomi turadi. Muhimlik ("O'rta") ham,
                  // "SLA —" old qo'shimchasi ham murojaatchiga hech narsa
                  // bermaydi: muhimlikni u tanlamaydi, u qoidadan olinadi.
                  <option key={`sla-${rule.id}`} value={`sla-${rule.id}`}>
                    {rule.name}
                  </option>
                ))}
                {templates.map((tmpl) => (
                  <option key={`tpl-${tmpl.id}`} value={`tpl-${tmpl.id}`}>
                    {tmpl.name}
                  </option>
                ))}
              </select>
            </div>
          </div>

          {/* Main Description */}
          <div>
            <div className="flex items-center justify-between mb-1">
              <label className="block text-xs font-bold text-slate-700 dark:text-slate-300">
                {t('createTask.todoLabel')}<RequiredMark />
              </label>
              <span className="text-[10px] font-semibold text-slate-400">
                {t('createTask.pasteHint')}
              </span>
            </div>
            {/* Balandligi chegaralangan: qo'lda cho'zish mumkin, lekin
                `max-h` tufayli karta ekrandan oshib ketmaydi. */}
            <textarea
              rows={4}
              value={todo}
              onChange={(e) => setTodo(e.target.value)}
              placeholder={t('createTask.todoPlaceholder')}
              className="w-full h-56 sm:h-64 max-h-[45vh] resize-y overflow-y-auto px-3.5 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 text-slate-900 dark:text-slate-100 text-xs sm:text-sm focus:ring-2 focus:ring-brand-500 focus:outline-none transition-all"
              required
            />
          </div>

          {/* Media Attachments (Photo/Video & Voice Recording Toolbar) */}
          <div className="flex flex-wrap items-center gap-2 p-2 rounded-xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700">
            {/* Image/File Input */}
            <label className="cursor-pointer inline-flex items-center space-x-1.5 px-2.5 py-1.5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
              <Image className="w-3.5 h-3.5 text-brand-500" />
              <span>{t('createTask.attachMedia')}</span>
              <input
                type="file"
                accept="image/*,video/*"
                onChange={handleFileChange}
                className="hidden"
              />
            </label>

            {/* Voice Record Button */}
            {!isRecording ? (
              <button
                type="button"
                onClick={startVoiceRecording}
                className="inline-flex items-center space-x-1.5 px-2.5 py-1.5 rounded-lg bg-rose-50 text-rose-600 dark:bg-rose-950/40 dark:text-rose-300 border border-rose-200 dark:border-rose-800 text-xs font-bold hover:bg-rose-100 dark:hover:bg-rose-900/40 transition-colors cursor-pointer"
              >
                <Mic className="w-3.5 h-3.5" />
                <span>{t('createTask.recordVoice')}</span>
              </button>
            ) : (
              <button
                type="button"
                onClick={stopVoiceRecording}
                className="inline-flex items-center space-x-1.5 px-2.5 py-1.5 rounded-lg bg-rose-600 text-white text-xs font-bold animate-pulse cursor-pointer"
              >
                <Square className="w-3.5 h-3.5" />
                <span>{t('createTask.stopRecording')} · {formatDuration(recordedMs)}</span>
              </button>
            )}

            {/* Attached File Preview */}
            {attachedFile && (
              <div className="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-300 text-xs font-bold max-w-[200px]">
                {filePreview ? (
                  <img src={filePreview} alt="Preview" className="w-4 h-4 object-cover rounded" />
                ) : (
                  <FileText className="w-3.5 h-3.5 flex-shrink-0" />
                )}
                <span className="truncate">{attachedFile.name}</span>
                <button
                  type="button"
                  onClick={removeAttachedFile}
                  title={t('createTask.removeFile')}
                  aria-label={t('createTask.removeFile')}
                  className="p-0.5 rounded text-slate-400 hover:text-rose-600 transition-colors flex-shrink-0"
                >
                  <Trash2 className="w-3 h-3" />
                </button>
              </div>
            )}

            {/* Audio Preview */}
            {audioUrl && (
              <div className="inline-flex items-center gap-1.5 px-2 py-1 rounded-lg bg-brand-50 dark:bg-brand-950/40 border border-brand-200 dark:border-brand-800 text-brand-700 dark:text-brand-300 text-xs font-bold">
                <Mic className="w-3.5 h-3.5 flex-shrink-0" />
                <span>{formatDuration(recordedMs) || '00:00'}</span>
                <audio src={audioUrl} controls className="h-6 w-32" />
                <button
                  type="button"
                  onClick={clearRecording}
                  title={t('createTask.removeRecording')}
                  aria-label={t('createTask.removeRecording')}
                  className="p-0.5 rounded text-slate-400 hover:text-rose-600 transition-colors flex-shrink-0"
                >
                  <Trash2 className="w-3 h-3" />
                </button>
              </div>
            )}
          </div>

          </div>

          {/* Bottom Row: Actions.
              Muhimlikni murojaatchi tanlamaydi — u tanlangan SLA qoidasidan
              olinadi, shablonsiz zayavkaga esa "O'rta" qo'yiladi. */}
          <div className="shrink-0 mt-3 pt-3 flex items-center justify-end gap-3 border-t border-slate-100 dark:border-slate-700/80">
            {/* Actions */}
            <div className="flex items-center justify-end space-x-2">
              <button
                type="button"
                onClick={onClose}
                className="px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 font-bold text-xs hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors cursor-pointer"
              >
                {t('common.cancel')}
              </button>

              <button
                type="submit"
                disabled={createTaskMutation.isPending || isFinalizing}
                className="inline-flex items-center space-x-1.5 px-5 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 active:bg-brand-700 text-white font-bold text-xs shadow-md transition-all disabled:opacity-50 cursor-pointer"
              >
                <span>{t('createTask.submit')}</span>
                <Send className="w-3.5 h-3.5" />
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  );
};
